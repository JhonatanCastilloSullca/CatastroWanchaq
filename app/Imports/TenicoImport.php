<?php

namespace App\Imports;

class TenicoImport extends AsignacionPersonalImport
{
    protected int $funcion = 3;
    protected string $responsable = 'id_tecnico';
    protected string $fecha = 'fecha_levantamiento';
}
