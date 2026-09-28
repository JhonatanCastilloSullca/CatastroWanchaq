<?php

namespace Tests\Feature;

use App\Http\Controllers\ReporteFichasSectorController;
use App\Services\ReporteFichasSectorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ReporteFichasSectorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'reporte_test', 'database.connections.reporte_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]]);
        DB::statement("ATTACH DATABASE ':memory:' AS catastro");
        DB::statement('CREATE TABLE catastro.tf_sectores (id_sector TEXT, codi_sector TEXT)');
        DB::statement('CREATE TABLE catastro.tf_manzanas (id_mzna TEXT, id_sector TEXT, codi_mzna TEXT)');
        DB::statement('CREATE TABLE catastro.tf_lotes (id_lote TEXT, id_mzna TEXT, codi_lote TEXT)');
        DB::statement('CREATE TABLE catastro.tf_fichas (id_ficha TEXT, id_lote TEXT, tipo_ficha TEXT, activo TEXT, nume_ficha TEXT, id_uni_cat TEXT)');
        DB::table('catastro.tf_sectores')->insert([
            ['id_sector' => 'S1', 'codi_sector' => '01'], ['id_sector' => 'S2', 'codi_sector' => '02'],
        ]);
        DB::table('catastro.tf_manzanas')->insert([
            ['id_mzna' => 'M1', 'id_sector' => 'S1', 'codi_mzna' => '2'],
            ['id_mzna' => 'M2', 'id_sector' => 'S1', 'codi_mzna' => '10'],
            ['id_mzna' => 'M3', 'id_sector' => 'S2', 'codi_mzna' => '1'],
        ]);
        DB::table('catastro.tf_lotes')->insert([
            ['id_lote' => 'L1', 'id_mzna' => 'M1', 'codi_lote' => '001'],
            ['id_lote' => 'L2', 'id_mzna' => 'M1', 'codi_lote' => '002'],
            ['id_lote' => 'L3', 'id_mzna' => 'M3', 'codi_lote' => '001'],
        ]);
        foreach (['01', '02', '03', '04', '05', '01 '] as $i => $tipo) {
            DB::table('catastro.tf_fichas')->insert([
                'id_ficha' => 'F'.$i, 'id_lote' => 'L1', 'tipo_ficha' => $tipo,
                'activo' => '1', 'nume_ficha' => (string) $i, 'id_uni_cat' => 'U1',
            ]);
        }
        DB::table('catastro.tf_fichas')->insert([
            ['id_ficha' => 'inactiva', 'id_lote' => 'L1', 'tipo_ficha' => '01', 'activo' => '0'],
            ['id_ficha' => 'otro-sector', 'id_lote' => 'L3', 'tipo_ficha' => '01', 'activo' => '1'],
        ]);
    }

    protected function tearDown(): void
    {
        DB::purge('reporte_test');
        parent::tearDown();
    }

    public function test_totales_excluyen_inactivas_y_otros_sectores_y_conservan_manzanas_vacias(): void
    {
        $filas = (new ReporteFichasSectorService)->resumen('S1');

        $this->assertSame(['M1', 'M2'], $filas->pluck('id')->all());
        $this->assertEquals([2, 1, 1, 1, 5], array_map(fn ($campo) => $filas[0]->$campo,
            ['uucc', 'cotitulares', 'economicas', 'bien_comun', 'total']));
        $this->assertEquals(0, $filas[1]->total);
    }

    public function test_totales_coinciden_al_navegar_hasta_las_fichas(): void
    {
        $reporte = new ReporteFichasSectorService;
        $lotes = $reporte->resumen('S1', 'M1');

        $this->assertCount(2, $lotes);
        $this->assertEquals(0, $lotes[1]->total);
        $this->assertEquals($reporte->resumen('S1')->sum('total'), $lotes->sum('total'));
        $this->assertEquals($lotes[0]->total, $reporte->fichas('S1')->where('f.id_lote', 'L1')->count());
        $this->assertCount(0, $reporte->resumen('S1', 'M3'));
    }

    public function test_rechaza_manzana_de_otro_sector(): void
    {
        $this->expectException(HttpException::class);
        (new ReporteFichasSectorController)->index(
            Request::create('/', 'GET', ['sector' => 'S1', 'manzana' => 'M3']),
            new ReporteFichasSectorService
        );
    }

    public function test_rechaza_lote_de_otra_manzana(): void
    {
        try {
            (new ReporteFichasSectorController)->index(
                Request::create('/', 'GET', ['sector' => 'S1', 'manzana' => 'M1', 'lote' => 'L3']),
                new ReporteFichasSectorService
            );
            $this->fail('Se aceptó un lote fuera de la manzana seleccionada.');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
    }

    public function test_detalle_muestra_las_fichas_del_lote(): void
    {
        $view = (new ReporteFichasSectorController)->index(
            Request::create('/', 'GET', ['sector' => 'S1', 'manzana' => 'M1', 'lote' => 'L1']),
            new ReporteFichasSectorService
        );
        $this->assertSame(5, $view->getData()['fichas']->total());
        $this->assertSame('L1', $view->getData()['lote']->id_lote);
    }
}
