<?php

namespace Tests\Feature;

use App\Services\RecuperarPlanoFichaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RecuperarPlanosTest extends TestCase
{
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9ZlS8AAAAASUVORK5CYII=';

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default'=>'planos_test','database.connections.planos_test'=>[
            'driver'=>'sqlite','database'=>':memory:','prefix'=>'',
        ], 'audit.enabled'=>false, 'planos.url_map'=>'http://mapa.test']);
        Storage::fake('public');
        Http::preventStrayRequests();
        DB::statement('CREATE TABLE tf_fichas (id_ficha TEXT PRIMARY KEY, id_lote TEXT, tipo_ficha TEXT)');
        DB::statement('CREATE TABLE tf_fichas_individuales (id_ficha TEXT PRIMARY KEY, imagen_plano TEXT)');
        foreach(['F1','F2'] as $id) {
            DB::table('tf_fichas')->insert(['id_ficha'=>$id,'id_lote'=>'L1','tipo_ficha'=>'01']);
            DB::table('tf_fichas_individuales')->insert(['id_ficha'=>$id,'imagen_plano'=>'perdido-'.$id.'.png']);
        }
    }

    private function simularMapa(?string $respuesta = null): void
    {
        $geo=\Mockery::mock(\Illuminate\Database\Connection::class)->makePartial();
        $geo->shouldReceive('selectOne')->once()->withArgs(fn($sql,$params)=>$params===['L1'] && str_contains($sql,'id_lote = ?'))
            ->andReturn((object)['xmin'=>100,'ymin'=>200,'xmax'=>110,'ymax'=>210]);
        config(['database.connections.pgsqlgeo' => ['driver' => 'geo_test']]);
        DB::extend('geo_test', fn() => $geo);
        Http::fake(['mapa.test/*'=>Http::response($respuesta ?? base64_decode(self::PNG),200,['Content-Type'=>'image/png'])]);
    }

    public function test_recupera_reutilizando_mapa_por_lote_y_se_puede_reanudar(): void
    {
        $this->simularMapa();
        $service=new RecuperarPlanoFichaService;
        $this->assertSame('recuperada',$service->recuperar('F1','L1'));
        $this->assertSame('recuperada',$service->recuperar('F2','L1'));
        $this->assertSame('omitida',$service->recuperar('F1','L1'));
        Storage::disk('public')->assertExists(['img/imagenesplanos/F1.png','img/imagenesplanos/F2.png']);
        $this->assertSame('F1.png',DB::table('tf_fichas_individuales')->where('id_ficha','F1')->value('imagen_plano'));
        Http::assertSentCount(1);
        Http::assertSent(fn($r)=>$r['id']==='L1' && $r['SRS']==='EPSG:32719' && $r['BBOX']==='100,200,110,210');
    }

    public function test_preserva_plano_existente_y_reconecta_archivo_sin_consultar_wms(): void
    {
        Http::fake();
        Storage::disk('public')->put('img/imagenesplanos/perdido-F1.png','plano manual existente');
        Storage::disk('public')->put('img/imagenesplanos/F2.png',base64_decode(self::PNG));
        $service=new RecuperarPlanoFichaService;
        $this->assertSame('omitida',$service->recuperar('F1','L1'));
        $this->assertSame('recuperada',$service->recuperar('F2','L1'));
        $this->assertSame('plano manual existente',Storage::disk('public')->get('img/imagenesplanos/perdido-F1.png'));
        Http::assertNothingSent();
    }

    public function test_no_guarda_respuesta_de_error_xml_como_imagen(): void
    {
        $this->simularMapa('<ServiceException>Error</ServiceException>');
        try {
            (new RecuperarPlanoFichaService)->recuperar('F1','L1');
            $this->fail('Debe rechazar el XML.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('PNG válida',$e->getMessage());
        }
        Storage::disk('public')->assertMissing('img/imagenesplanos/F1.png');
        $this->assertSame('perdido-F1.png',DB::table('tf_fichas_individuales')->where('id_ficha','F1')->value('imagen_plano'));
    }

    public function test_vista_previa_no_modifica_archivos_ni_consulta_mapas(): void
    {
        Http::fake();
        $this->artisan('planos:recuperar',['--limite'=>1])->assertExitCode(0);
        $this->assertSame([],Storage::disk('public')->allFiles());
        $this->assertSame('perdido-F1.png',DB::table('tf_fichas_individuales')->where('id_ficha','F1')->value('imagen_plano'));
        Http::assertNothingSent();
    }

    public function test_comando_respeta_limite_y_continua_con_la_siguiente_ficha(): void
    {
        $this->simularMapa();
        // Compartir el servicio solo en esta prueba permite verificar también su caché por lote.
        $this->app->singleton(RecuperarPlanoFichaService::class);
        $this->artisan('planos:recuperar', ['--aplicar'=>true, '--limite'=>1])->assertExitCode(0);
        Storage::disk('public')->assertExists('img/imagenesplanos/F1.png');
        Storage::disk('public')->assertMissing('img/imagenesplanos/F2.png');
        $this->artisan('planos:recuperar', ['--aplicar'=>true, '--limite'=>1])->assertExitCode(0);
        Storage::disk('public')->assertExists('img/imagenesplanos/F2.png');
        Http::assertSentCount(1);
    }

    public function test_fallo_al_actualizar_referencia_permite_reanudar_sin_descargar_otra_vez(): void
    {
        $this->simularMapa();
        DB::statement("CREATE TRIGGER fallo BEFORE UPDATE ON tf_fichas_individuales BEGIN SELECT RAISE(ABORT, 'fallo'); END");
        $service=new RecuperarPlanoFichaService;
        try {
            $service->recuperar('F1','L1');
            $this->fail('Debe fallar');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertSame('perdido-F1.png',DB::table('tf_fichas_individuales')->where('id_ficha','F1')->value('imagen_plano'));
        }
        DB::statement('DROP TRIGGER fallo');
        $this->assertSame('recuperada',$service->recuperar('F1','L1'));
        Http::assertSentCount(1);
    }
}


