<?php

namespace App\Services;

use Illuminate\Support\Collection;

class ActualizarCucMasivoService
{
    public function guardar(Collection $modelos): void
    {
        app(ActualizarAsignacionMasivaService::class)->guardar($modelos, ['cuc']);
    }
}
