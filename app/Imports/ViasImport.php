<?php

namespace App\Imports;

use App\Models\Via;
use Maatwebsite\Excel\Row;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class ViasImport implements OnEachRow, WithHeadingRow, WithBatchInserts, WithChunkReading, WithValidation
{
    public function __construct()
    {
    }

    public function onRow(Row $row)
    {
        $via = Via::find($row['id_via']);
        $codevia = Via::where('codi_via',$row['codi_via'])->first();
        if(!$via && !$codevia){
            $via = new Via();
            $via->id_via = $row['id_via'];
            $via->nomb_via = $row['nomb_via'];
            $via->tipo_via = $row['tipo_via'];
            $via->codi_via = $row['codi_via'];
            $via->id_ubi_geo = $row['id_ubi_geo'];
            $via->fecha_via = $row['fecha_via'] == 'NULL' ? null : $row['fecha_via'];
            $via->estado = $row['estado'];
            $via->save();
        }
    }

    public function batchSize(): int
    {
        return 4000;
    }
    
    public function chunkSize(): int
    {
        return 4000;
    }

    public function rules(): array
    {
        return [
            '*.id_via' => 'required',
            '*.nomb_via' => 'required',
            '*.tipo_via' => 'required',
            '*.codi_via' => 'required',
            '*.id_ubi_geo' => 'required',
        ];
    }
}
