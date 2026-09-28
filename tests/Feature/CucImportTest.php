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
