<?php

namespace Tests\Feature;

use App\Imports\SupervisorImport;
use App\Imports\TenicoImport;
use App\Imports\VerificadorImport;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AsignacionPersonalImportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default'=>'personal_test', 'database.connections.personal_test'=>[
            'driver'=>'sqlite', 'database'=>':memory:', 'prefix'=>'',
        ], 'audit.console'=>true, 'audit.drivers.database.connection'=>'personal_test']);
        DB::statement('CREATE TABLE tf_uni_cat (id_uni_cat TEXT PRIMARY KEY)');
        DB::statement('CREATE TABLE tf_personas (id_persona TEXT PRIMARY KEY, nume_doc TEXT, tipo_funcion TEXT)');
        DB::statement('CREATE TABLE tf_fichas (id_ficha TEXT PRIMARY KEY, id_uni_cat TEXT, id_supervisor TEXT, id_tecnico TEXT, id_verificador TEXT, fecha_supervision TEXT, fecha_levantamiento TEXT, fecha_verificacion TEXT, nume_registro TEXT)');
        require_once database_path('migrations/2023_07_12_113515_create_audits_table.php');
        (new \CreateAuditsTable)->up();
        DB::table('tf_uni_cat')->insert([['id_uni_cat'=>'001'],['id_uni_cat'=>'002']]);
        DB::table('tf_fichas')->insert([['id_ficha'=>'F1','id_uni_cat'=>'001'],['id_ficha'=>'F2','id_uni_cat'=>'001'],['id_ficha'=>'F3','id_uni_cat'=>'002']]);
        foreach ([2,3,4] as $role) DB::table('tf_personas')->insert(['id_persona'=>'P'.$role,'nume_doc'=>'0000000'.$role,'tipo_funcion'=>(string)$role]);
    }

    protected function tearDown(): void
    {
        DB::purge('personal_test');
        parent::tearDown();
    }

    public static function opciones(): array
    {
        return [[SupervisorImport::class,2,'id_supervisor','fecha_supervision'],[TenicoImport::class,3,'id_tecnico','fecha_levantamiento'],[VerificadorImport::class,4,'id_verificador','fecha_verificacion']];
    }

    /** @dataProvider opciones */
    public function test_actualiza_todas_las_fichas_con_fecha_excel_y_auditoria($clase,$rol,$campo,$fecha): void
    {
        $import = new $clase;
        $row=['cod_referencia'=>'001','nume_doc'=>'0000000'.$rol,$fecha=>46254,'nume_registro'=>'0123456789'];
        $import->collection(collect([collect($row)]));
        $this->assertSame('P'.$rol, DB::table('tf_fichas')->where('id_ficha','F1')->value($campo));
        $esperada = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject(46254)->format('Y-m-d');
        $this->assertSame($esperada, DB::table('tf_fichas')->where('id_ficha','F2')->value($fecha));
        $this->assertNull(DB::table('tf_fichas')->where('id_ficha','F3')->value($campo));
        $this->assertSame(2,DB::table('audits')->count());
        if ($rol===4) $this->assertSame('0123456789',DB::table('tf_fichas')->where('id_ficha','F1')->value('nume_registro'));
        $row[$fecha]=\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject(46254)->format('d/m/Y');
        $import->collection(collect([collect($row)]));
        $this->assertSame(1,$import->resultado()['sin_cambios']);
    }

    public function test_reporta_filas_y_campos_sin_escrituras(): void
    {
        try {
            (new VerificadorImport)->collection(collect([
                collect(['cod_referencia'=>'001','nume_doc'=>'00000004','fecha_verificacion'=>'2026-09-30','nume_registro'=>'123']),
                collect(['cod_referencia'=>null,'nume_doc'=>null,'fecha_verificacion'=>null,'nume_registro'=>null]),
                collect(['cod_referencia'=>'002','nume_doc'=>str_repeat('1',18),'fecha_verificacion'=>'31/02/2026','nume_registro'=>'12345678901']),
            ]));
            $this->fail('Debe fallar la validación');
        } catch (ValidationException $e) {
            $this->assertCount(3,$e->errors()['archivo']);
            foreach($e->errors()['archivo'] as $error) $this->assertStringContainsString('Fila 4',$error);
        }
        $this->assertSame(0,DB::table('audits')->count());
        $this->assertNull(DB::table('tf_fichas')->where('id_ficha','F1')->value('id_verificador'));
    }

    /** @dataProvider opciones */
    public function test_escrituras_agrupadas_preservan_fechas_nulas_y_auditoria($clase, $rol, $campo, $fecha): void
    {
        $rows = collect();
        foreach (range(1, 100) as $numero) {
            $id = 'U'.$numero;
            DB::table('tf_uni_cat')->insert(['id_uni_cat'=>$id]);
            DB::table('tf_fichas')->insert(['id_ficha'=>$id,'id_uni_cat'=>$id,$fecha=>'2025-01-01']);
            $rows->push(collect(['cod_referencia'=>$id,'nume_doc'=>'0000000'.$rol,
                $fecha=>$numero % 2 === 0 ? null : 46254,'nume_registro'=>'008562']));
        }
        DB::enableQueryLog();
        (new $clase)->collection($rows);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertCount(1, array_filter($queries, fn($q)=>str_starts_with(strtolower($q['query']), 'update')));
        $this->assertCount(1, array_filter($queries, fn($q)=>str_starts_with(strtolower($q['query']), 'insert')));
        $this->assertSame(100, DB::table('audits')->count());
        $this->assertSame('2026-08-20', DB::table('tf_fichas')->where('id_ficha','U1')->value($fecha));
        $this->assertNull(DB::table('tf_fichas')->where('id_ficha','U2')->value($fecha));
        $audit = DB::table('audits')->where('auditable_id','U1')->first();
        $this->assertSame('2025-01-01', json_decode($audit->old_values,true)[$fecha]);
        $this->assertSame('P'.$rol, json_decode($audit->new_values,true)[$campo]);
        if ($rol === 4) $this->assertSame('008562',DB::table('tf_fichas')->where('id_ficha','U1')->value('nume_registro'));
    }

    public function test_persona_de_otra_funcion_y_unidad_inexistente_no_se_omiten_silenciosamente(): void
    {
        try {
            (new SupervisorImport)->collection(collect([
                collect(['cod_referencia'=>'001','nume_doc'=>'00000002','fecha_supervision'=>null]),
                collect(['cod_referencia'=>'999','nume_doc'=>'00000003','fecha_supervision'=>null]),
            ]));
            $this->fail('Debe informar ambas referencias inválidas');
        } catch (ValidationException $e) {
            $this->assertCount(2,$e->errors()['archivo']);
        }
        $this->assertSame(0,DB::table('audits')->count());
    }

    public function test_error_de_base_revierte_fichas_y_auditoria(): void
    {
        DB::statement("CREATE TRIGGER fallo BEFORE UPDATE ON tf_fichas WHEN NEW.id_ficha = 'F3' BEGIN SELECT RAISE(ABORT, 'fallo'); END");
        try {
            (new TenicoImport)->collection(collect(['001','002'])->map(fn($id)=>collect(['cod_referencia'=>$id,'nume_doc'=>'00000003','fecha_levantamiento'=>'2026-09-30'])));
            $this->fail('Debe fallar');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertSame(0,DB::table('audits')->count());
            $this->assertNull(DB::table('tf_fichas')->where('id_ficha','F1')->value('id_tecnico'));
        }
    }

    public function test_excel_verificador_completo_regresa_a_su_pantalla(): void
    {
        $book = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->fromArray(['cod_referencia', 'nume_doc', 'fecha_verificacion', 'nume_registro'], null, 'A1');
        $sheet->setCellValueExplicit('A2', '001', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('B2', '00000004', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValue('C2', '30/09/2026');
        $sheet->setCellValue('D2', 'REG-123');
        $path = tempnam(sys_get_temp_dir(), 'personal');
        try {
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($path);
            $request = \Illuminate\Http\Request::create('/', 'POST', [], [], [
                'archivo' => new \Illuminate\Http\UploadedFile($path, 'verificador.xlsx', null, null, true),
            ]);
            $response = (new \App\Http\Controllers\ReporteController)->importarverificador($request);
            $this->assertSame(route('reporte.exportarverificador'), $response->getTargetUrl());
            $this->assertSame('P4', DB::table('tf_fichas')->where('id_ficha','F1')->value('id_verificador'));
        } finally {
            unlink($path);
            $book->disconnectWorksheets();
        }
    }
}
