<?php

namespace Tests\Feature;

use App\Services\RentasPdfService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;
use setasign\Fpdi\PdfParser\StreamReader;
use Tests\TestCase;

class RentasPdfApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'rentas_test', 'database.connections.rentas_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ], 'filesystems.default' => 'rentas_test']);
        Storage::fake('rentas_test');
        DB::statement('CREATE TABLE tf_lotes (id_lote TEXT PRIMARY KEY)');
        DB::statement('CREATE TABLE tf_fichas (id_ficha TEXT PRIMARY KEY, id_lote TEXT, tipo_ficha TEXT, activo TEXT, nume_ficha TEXT, id_uni_cat TEXT)');
        DB::statement('CREATE TABLE archivos (id INTEGER PRIMARY KEY, id_ficha TEXT, rentas TEXT)');
        DB::table('tf_lotes')->insert(['id_lote' => '001']);
        foreach (['F1', 'F2'] as $id) {
            DB::table('tf_fichas')->insert(['id_ficha' => $id, 'id_lote' => '001', 'tipo_ficha' => '01', 'activo' => '1', 'id_uni_cat' => $id, 'nume_ficha' => $id]);
        }
    }

    protected function tearDown(): void
    {
        DB::purge('rentas_test');
        parent::tearDown();
    }

    public function test_navegador_abre_los_tres_pdfs_sin_sesion_ni_token(): void
    {
        $this->mock(RentasPdfService::class, function ($mock) {
            $mock->shouldReceive('generar')->times(3)->andReturn('%PDF-prueba');
        });

        foreach (['ficha-individual', 'archivo-rentas', 'predio-urbano'] as $ruta) {
            $this->get('/api/rentas/lotes/001/'.$ruta, ['Accept' => 'text/html'])
                ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        }
    }

    public function test_lote_inexistente_y_lote_sin_fichas(): void
    {
        $this->getJson('/api/rentas/lotes/999/archivo-rentas')->assertNotFound();
        DB::table('tf_fichas')->update(['activo' => '0']);
        $this->getJson('/api/rentas/lotes/001/archivo-rentas')->assertNotFound();
    }

    public function test_tres_endpoints_entregan_pdf_binario(): void
    {
        $this->mock(RentasPdfService::class, function ($mock) {
            foreach (['individual', 'archivo-rentas', 'predio-urbano'] as $tipo) {
                $mock->shouldReceive('generar')->once()->withArgs(fn ($lote, $actual) => $lote->id_lote === '001' && $actual === $tipo)->andReturn('%PDF-prueba');
            }
        });
        foreach (['ficha-individual', 'archivo-rentas', 'predio-urbano'] as $ruta) {
            $this->getJson('/api/rentas/lotes/001/'.$ruta)->assertOk()
                ->assertHeader('Content-Type', 'application/pdf')->assertSee('%PDF-prueba', false);
        }
    }

    public function test_union_conserva_todas_las_paginas_y_orientaciones(): void
    {
        foreach (['F1' => 'P', 'F2' => 'L'] as $id => $orientacion) {
            $pdf = new Mpdf(['orientation' => $orientacion, 'tempDir' => storage_path('app/mpdf-temp')]);
            $pdf->WriteHTML('Documento '.$id);
            $pdf->AddPage();
            $pdf->WriteHTML('Segunda página '.$id);
            Storage::put('img/archivos/'.$id.'.pdf', $pdf->Output('', 'S'));
            DB::table('archivos')->insert(['id_ficha' => $id, 'rentas' => $id.'.pdf']);
        }
        $response = $this->getJson('/api/rentas/lotes/001/archivo-rentas')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $lector = new Mpdf(['tempDir' => storage_path('app/mpdf-temp')]);
        $this->assertSame(4, $lector->setSourceFile(StreamReader::createByString($response->getContent())));
        $this->assertSame('P', $lector->getTemplateSize($lector->importPage(1))['orientation']);
        $this->assertSame('L', $lector->getTemplateSize($lector->importPage(3))['orientation']);
    }

    public function test_archivo_faltante_no_entrega_pdf_parcial(): void
    {
        $this->getJson('/api/rentas/lotes/001/archivo-rentas')->assertNotFound();
        DB::table('archivos')->insert(['id_ficha' => 'F1', 'rentas' => 'faltante.pdf']);
        $this->getJson('/api/rentas/lotes/001/archivo-rentas')->assertNotFound();
    }

    public function test_rechaza_archivos_invalidos_y_rutas_fuera_del_directorio(): void
    {
        DB::table('archivos')->insert(['id_ficha' => 'F1', 'rentas' => '../privado.pdf']);
        $this->getJson('/api/rentas/lotes/001/archivo-rentas')->assertUnprocessable();
        DB::table('archivos')->update(['rentas' => 'invalido.pdf']);
        Storage::put('img/archivos/invalido.pdf', 'Esto no es un PDF');
        $this->getJson('/api/rentas/lotes/001/archivo-rentas')->assertUnprocessable();
        Storage::put('img/archivos/invalido.pdf', '%PDF-1.4 contenido dañado');
        $this->getJson('/api/rentas/lotes/001/archivo-rentas')->assertUnprocessable();
    }
}
