<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class ReporteConsultaExport implements
    FromCollection,
    WithHeadings,
    ShouldAutoSize
{
    protected $datos;
    protected $cabeceras;

    public function __construct($datos, $cabeceras)
    {
        $this->datos = collect($datos)->map(function ($fila) {
            return (array) $fila;
        });

        $this->cabeceras = $cabeceras;
    }

    public function collection()
    {
        return $this->datos;
    }

    public function headings(): array
    {
        return $this->cabeceras;
    }
}