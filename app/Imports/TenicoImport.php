<?php

namespace App\Imports;

use App\Models\Ficha;
use App\Models\Persona;
use App\Models\UniCat;
use Maatwebsite\Excel\Row;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class TenicoImport implements OnEachRow, WithHeadingRow, WithBatchInserts, WithChunkReading, WithValidation
{
   public function __construct()
    {
    }

    public function onRow(Row $row)
    {
        $uni_cat = UniCat::find($row['cod_referencia']);
        if($uni_cat)
        {  
            $fichas = Ficha::where('id_uni_cat',$row['cod_referencia'])->get();
            $supervisor = Persona::where('nume_doc',$row['nume_doc'])->where('tipo_funcion',3)->first();
            if($fichas && $supervisor){
                foreach($fichas as $ficha){
                    $ficha->id_tecnico = $supervisor?->id_persona;
                    $ficha->fecha_levantamiento = $row['fecha_levantamiento'];
                    $ficha->save();
                }
            }
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
            '*.cod_referencia' => 'required',
        ];
    }
}
