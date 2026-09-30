<?php

namespace App\Imports;

class SupervisorImport extends AsignacionPersonalImport
{
    protected int $funcion = 2;
    protected string $responsable = 'id_supervisor';
    protected string $fecha = 'fecha_supervision';
}
