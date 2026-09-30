<?php

namespace App\Imports;

class VerificadorImport extends AsignacionPersonalImport
{
    protected int $funcion = 4;
    protected string $responsable = 'id_verificador';
    protected string $fecha = 'fecha_verificacion';
}
