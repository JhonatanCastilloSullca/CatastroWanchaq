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
    public function test_una_consulta_devuelve_solo_links_de_todos_los_documentos(): void
    {
        DB::table('tf_fichas')->insert([
            ['id_ficha'=>'C1','id_lote'=>'001','tipo_ficha'=>'02','activo'=>'1'],
            ['id_ficha'=>'E1','id_lote'=>'001','tipo_ficha'=>'03','activo'=>'1'],
            ['id_ficha'=>'C2','id_lote'=>'otro','tipo_ficha'=>'02','activo'=>'1'],
            ['id_ficha'=>'E2','id_lote'=>'001','tipo_ficha'=>'03','activo'=>'0'],
        ]);
        DB::table('archivos')->insert(['id'=>1,'id_ficha'=>'F1','rentas'=>'prueba.pdf']);
        Storage::put('img/archivos/prueba.pdf', '%PDF-prueba');
        $response = $this->getJson('/api/rentas/lotes/001/enlaces')->assertOk();
        $this->assertSame(['individuales','cotitulares','economicas','pu','archivos'], array_keys($response->json()));
        foreach (['individuales'=>2,'cotitulares'=>1,'economicas'=>1,'pu'=>2,'archivos'=>1] as $key=>$count) {
            $response->assertJsonCount($count, $key);
            foreach ($response->json($key) as $url) {
                $this->assertIsString($url);
                $this->assertStringContainsString('/api/rentas/lotes/001/', $url);
            }
        }
        $this->get($response->json('archivos.0'))->assertOk()->assertDownload('prueba.pdf');
        $this->getJson('/api/rentas/lotes/001/fichas/F2/archivos/1/rentas')->assertNotFound();
        $this->getJson('/api/rentas/lotes/001/fichas/C2/pdf')->assertNotFound();
        $this->getJson('/api/rentas/lotes/001/fichas/E2/pdf')->assertNotFound();
        $this->getJson('/api/rentas/lotes/001/fichas/F1/archivos/1/id_ficha')->assertNotFound();
        DB::table('archivos')->update(['rentas'=>'../privado.pdf']);
        $this->getJson('/api/rentas/lotes/001/fichas/F1/archivos/1/rentas')->assertNotFound();
    }

    public function test_enlaces_sin_documentos_son_listas_vacias(): void
    {
        DB::table('tf_fichas')->update(['activo'=>'0']);
        $this->getJson('/api/rentas/lotes/001/enlaces')->assertOk()->assertExactJson([
            'individuales'=>[], 'cotitulares'=>[], 'economicas'=>[], 'pu'=>[], 'archivos'=>[],
        ]);
        $this->getJson('/api/rentas/lotes/999/enlaces')->assertNotFound();
    }

    public function test_json_publico_lista_todas_las_individuales_y_sus_enlaces(): void
    {
        DB::table('tf_fichas')->insert([
            ['id_ficha' => 'otra', 'id_lote' => '002', 'tipo_ficha' => '01', 'activo' => '1'],
            ['id_ficha' => 'baja', 'id_lote' => '001', 'tipo_ficha' => '01', 'activo' => '0'],
            ['id_ficha' => 'comun', 'id_lote' => '001', 'tipo_ficha' => '04', 'activo' => '1'],
        ]);
        $respuesta = $this->get('/api/rentas/lotes/001/fichas-individuales');
        $respuesta->assertOk()->assertJsonPath('id_lote', '001')->assertJsonPath('total', 2)
            ->assertJsonCount(2, 'fichas')->assertJsonPath('fichas.0.id_ficha', 'F1');
        foreach (['ficha_individual', 'archivo_rentas', 'predio_urbano'] as $tipo) {
            $this->assertStringContainsString('/api/rentas/lotes/001/', $respuesta->json('enlaces.'.$tipo));
            $this->assertStringContainsString('/fichas-individuales/F1/', $respuesta->json('fichas.0.enlaces.'.$tipo));
        }
    }

    public function test_json_vacio_y_lote_inexistente(): void
    {
        DB::table('tf_fichas')->update(['activo' => '0']);
        $this->getJson('/api/rentas/lotes/001/fichas-individuales')->assertOk()
            ->assertJsonPath('total', 0)->assertJsonPath('fichas', []);
        $this->getJson('/api/rentas/lotes/999/fichas-individuales')->assertNotFound();
    }

    public function test_enlaces_por_ficha_generan_solo_la_ficha_solicitada_y_validan_pertenencia(): void
    {
        $this->mock(RentasPdfService::class, function ($mock) {
            foreach (['individual', 'archivo-rentas', 'predio-urbano'] as $tipo) {
                $mock->shouldReceive('generar')->once()->withArgs(fn ($lote, $actual, $ficha) =>
                    $lote->id_lote === '001' && $actual === $tipo && $ficha === 'F1')->andReturn('%PDF-prueba');
            }
        });
        foreach (['ficha-individual', 'archivo-rentas', 'predio-urbano'] as $tipo) {
            $this->get('/api/rentas/lotes/001/fichas-individuales/F1/'.$tipo)->assertOk()
                ->assertHeader('Content-Type', 'application/pdf');
        }
        DB::table('tf_fichas')->where('id_ficha', 'F1')->update(['id_lote' => 'otro']);
        $this->getJson('/api/rentas/lotes/001/fichas-individuales/F1/ficha-individual')->assertNotFound();
        DB::table('tf_fichas')->where('id_ficha', 'F2')->update(['activo' => '0']);
        $this->getJson('/api/rentas/lotes/001/fichas-individuales/F2/ficha-individual')->assertNotFound();
    }

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
