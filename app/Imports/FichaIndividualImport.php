<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class FichaIndividualImport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            'Ficha' => new FichaImport(),
            'Puertas' => new PuertasImport(),
            'Titular' => new TitularImport(),
            'Predio' => new PredioImport(),
            'Servicios' => new ServiciosImport(),
            'Construcciones' => new ConstruccionesImport(),
            'Obras' => new ObrasImport(),
            'Documentos' => new DocumentosImport(),
            'Inscripcion' => new InscripcionImport(),
        ];
    }
}
