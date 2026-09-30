<?php

namespace Tests\Feature;

use App\Imports\CucImport;
use App\Http\Controllers\ReporteController;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class CucImportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'cuc_test', 'database.connections.cuc_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ], 'audit.console' => true, 'audit.drivers.database.connection' => 'cuc_test']);
        DB::statement('CREATE TABLE tf_uni_cat (id_uni_cat TEXT PRIMARY KEY, cuc TEXT)');
        DB::statement('CREATE TABLE tf_fichas (id_ficha TEXT PRIMARY KEY, id_uni_cat TEXT, cuc TEXT)');
        require_once database_path('migrations/2023_07_12_113515_create_audits_table.php');
        (new \CreateAuditsTable)->up();
        DB::table('tf_uni_cat')->insert([['id_uni_cat'=>'001', 'cuc'=>'0011'], ['id_uni_cat'=>'002','cuc'=>'0022']]);
        DB::table('tf_fichas')->insert([
            ['id_ficha'=>'F1','id_uni_cat'=>'001','cuc'=>'0011'],
            ['id_ficha'=>'F2','id_uni_cat'=>'001','cuc'=>'old'],
            ['id_ficha'=>'F3','id_uni_cat'=>'002','cuc'=>'0022'],
        ]);
    }

    protected function tearDown(): void
    {
        DB::purge('cuc_test');
        parent::tearDown();
    }

    public function test_preserva_vacios_sincroniza_fichas_y_conserva_auditoria(): void
    {
        $import = new CucImport;
        $import->collection(collect([
            collect(['cod_referencia'=>'001','cuc'=>'0011']),
            collect(['cod_referencia'=>'002','cuc'=>null]),
            collect(['cod_referencia'=>'missing','cuc'=>'0011']),
        ]));
        $this->assertSame('0011',DB::table('tf_fichas')->where('id_ficha','F2')->value('cuc'));
        $this->assertSame('0022',DB::table('tf_uni_cat')->where('id_uni_cat','002')->value('cuc'));
        $this->assertSame(1,DB::table('audits')->count());
        $this->assertSame(['actualizadas'=>1,'sin_cambios'=>0,'sin_cuc'=>1,'no_encontradas'=>1],$import->resultado());
        $import->collection(collect([collect(['cod_referencia'=>'001','cuc'=>'0011'])]));
        $this->assertSame(1,$import->resultado()['sin_cambios']);
        $this->assertSame(1,DB::table('audits')->count());
    }

    public function test_consulta_unidades_y_fichas_una_vez_por_bloque(): void
    {
        DB::enableQueryLog();
        (new CucImport)->collection(collect(range(1,100))->map(fn()=>collect(['cod_referencia'=>'002','cuc'=>'0022'])));
        $selects=array_filter(DB::getQueryLog(),fn($q)=>str_starts_with($q['query'],'select'));
        $this->assertCount(2,$selects);
        DB::disableQueryLog();
    }

    public function test_rechaza_columnas_incorrectas(): void
    {
        $this->expectException(ValidationException::class);
        (new CucImport)->collection(collect([collect(['cod_referencia'=>'001','otro'=>'123'])]));
    }

    public function test_escritura_por_lotes_conserva_valores_previos_y_auditoria(): void
    {
        $rows = collect();
        foreach (range(1, 100) as $numero) {
            $id = 'U'.$numero;
            DB::table('tf_uni_cat')->insert(['id_uni_cat'=>$id, 'cuc'=>'ANTERIOR']);
            DB::table('tf_fichas')->insert(['id_ficha'=>$id, 'id_uni_cat'=>$id, 'cuc'=>'ANTERIOR']);
            $rows->push(collect(['cod_referencia'=>$id, 'cuc'=>'000000000123']));
        }
        DB::enableQueryLog();
        (new CucImport)->collection($rows);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertCount(2, array_filter($queries, fn ($q) => str_starts_with(strtolower($q['query']), 'update')));
        $this->assertCount(2, array_filter($queries, fn ($q) => str_starts_with(strtolower($q['query']), 'insert')));
        $this->assertSame(200, DB::table('audits')->count());
        $audit = DB::table('audits')->first();
        $this->assertSame(['cuc'=>'ANTERIOR'], json_decode($audit->old_values, true));
        $this->assertSame(['cuc'=>'000000000123'], json_decode($audit->new_values, true));
        $this->assertSame('updated', $audit->event);
        $this->assertSame(100, DB::table('tf_fichas')->where('cuc','000000000123')->count());
    }

    public function test_detecta_todos_los_cuc_largos_antes_de_escribir_y_respeta_filas_vacias(): void
    {
        DB::enableQueryLog();
        try {
            (new CucImport)->collection(collect([
                collect(['cod_referencia'=>'001', 'cuc'=>'27240027-0001']),
                collect(['cod_referencia'=>null, 'cuc'=>null]),
                collect(['cod_referencia'=>'001', 'cuc'=>'27240027-00000']),
                collect(['cod_referencia'=>'002', 'cuc'=>'27240117 00001']),
            ]));
            $this->fail('Debe rechazar ambos códigos de 13 dígitos.');
        } catch (ValidationException $exception) {
            $this->assertCount(2, $exception->errors()['archivo']);
            $this->assertStringContainsString('Fila 4', $exception->errors()['archivo'][0]);
            $this->assertStringContainsString('Fila 5', $exception->errors()['archivo'][1]);
            $this->assertCount(0, DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
        $this->assertSame('0011', DB::table('tf_uni_cat')->where('id_uni_cat','001')->value('cuc'));
        (new CucImport)->collection(collect([collect(['cod_referencia'=>'001','cuc'=>'27240027-0001'])]));
        $this->assertSame('272400270001', DB::table('tf_uni_cat')->where('id_uni_cat','001')->value('cuc'));
    }

    public function test_fallo_de_base_en_segundo_bloque_revierte_tambien_la_auditoria(): void
    {
        DB::statement("CREATE TRIGGER fallo_cuc BEFORE UPDATE ON tf_uni_cat WHEN NEW.id_uni_cat = '002' BEGIN SELECT RAISE(ABORT, 'fallo simulado'); END");
        $rows = collect(range(1, 500))->map(fn () => collect(['cod_referencia' => '001', 'cuc' => '9999']));
        $rows->push(collect(['cod_referencia' => '002', 'cuc' => '8888']));
        try {
            (new CucImport)->collection($rows);
            $this->fail('La segunda actualización debe fallar.');
        } catch (\Illuminate\Database\QueryException $exception) {
            $this->assertSame('0011', DB::table('tf_uni_cat')->where('id_uni_cat', '001')->value('cuc'));
            $this->assertSame('old', DB::table('tf_fichas')->where('id_ficha', 'F2')->value('cuc'));
            $this->assertSame(0, DB::table('audits')->count());
        }
    }

    public function test_error_en_bloque_posterior_revierte_archivo_completo(): void
    {
        $book=new Spreadsheet;
        $sheet=$book->getActiveSheet();
        $sheet->fromArray(['cod_referencia','cuc'],null,'A1');
        for($i=2;$i<=501;$i++) {
            $sheet->setCellValueExplicit('A'.$i,'001',\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue('B'.$i,'0099');
        }
        $sheet->setCellValueExplicit('A502','001',\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValue('B502','invalid');
        $path=tempnam(sys_get_temp_dir(),'cuc').'.xlsx';
        try {
            (new Xlsx($book))->save($path);
            $request=Request::create('/masivo/importarcuc','POST',[],[],['archivo'=>new UploadedFile($path,'test.xlsx',null,null,true)]);
            $request->headers->set('Accept','application/json');
            try {
                (new ReporteController)->importarcuc($request);
                $this->fail('Debe rechazar el CUC inválido.');
            } catch (ValidationException $e) {
                $this->assertSame('0011',DB::table('tf_uni_cat')->where('id_uni_cat','001')->value('cuc'));
                $this->assertSame('old',DB::table('tf_fichas')->where('id_ficha','F2')->value('cuc'));
                $this->assertSame(0,DB::table('audits')->count());
            }
        } finally { @unlink($path); $book->disconnectWorksheets(); }
    }
}
