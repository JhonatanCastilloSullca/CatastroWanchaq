<?php

namespace App\Services;

use App\Models\Ficha;
use App\Models\UniCat;
use Illuminate\Support\Collection;

class ActualizarCucMasivoService
{
    /** Los modelos deben haberse leído con bloqueo dentro de la transacción de importación. */
    public function guardar(Collection $modelos): void
    {
        foreach ($modelos->filter(fn ($modelo) => $modelo->isDirty('cuc'))->groupBy(fn ($modelo) => get_class($modelo)) as $clase => $grupo) {
            if (! in_array($clase, [UniCat::class, Ficha::class], true)) {
                throw new \InvalidArgumentException('Modelo no permitido para asignación CUC.');
            }
            $conexion = $grupo->first()->getConnection();
            if ($conexion->transactionLevel() < 1) {
                throw new \LogicException('La asignación CUC requiere una transacción.');
            }
            foreach ($grupo->chunk(200) as $bloque) {
                // Conservar el flujo habitual si se configura un controlador de auditoría especial.
                if ($bloque->contains(fn ($modelo) => $modelo->getAuditDriver() !== 'database' || $modelo->getAuditThreshold() > 0)
                    || config('audit.queue.enable', false)
                    || app('events')->hasListeners(\OwenIt\Auditing\Events\Auditing::class)
                    || app('events')->hasListeners(\OwenIt\Auditing\Events\Audited::class)) {
                    foreach ($bloque as $modelo) {
                        $modelo->save();
                    }
                    continue;
                }
                $auditorias = [];
                $modeloAudit = null;
                foreach ($bloque as $modelo) {
                    if (array_diff(array_keys($modelo->getDirty()), ['cuc']) !== []) {
                        throw new \LogicException('La asignación masiva solo puede modificar CUC.');
                    }
                    $modelo->setAuditEvent('updated');
                    if ($modelo::isAuditingEnabled() && $modelo->readyForAuditing()) {
                        // Se reutilizan las exclusiones, valores previos, usuario y resolutores del sistema.
                        $claseAudit = config('audit.implementation');
                        $audit = new $claseAudit;
                        if ($audit->getConnection()->getName() !== $conexion->getName()) {
                            throw new \LogicException('La auditoría CUC debe usar la misma conexión transaccional.');
                        }
                        $audit->fill($modelo->toAudit());
                        $audit->updateTimestamps();
                        $auditorias[] = $audit->getAttributes();
                        $modeloAudit = $audit;
                    }
                }
                $ejemplo = $bloque->first();
                $gramatica = $conexion->getQueryGrammar();
                $tabla = $gramatica->wrapTable($ejemplo->getTable());
                $clave = $gramatica->wrap($ejemplo->getKeyName());
                $columna = $gramatica->wrap('cuc');
                $casos = [];
                $valores = [];
                foreach ($bloque as $modelo) {
                    $casos[] = 'WHEN ? THEN ?';
                    $valores[] = $modelo->getKey();
                    $valores[] = $modelo->cuc;
                }
                $ids = $bloque->map(fn ($modelo) => $modelo->getKey())->all();
                $marcadores = implode(',', array_fill(0, count($ids), '?'));
                $conexion->update("UPDATE {$tabla} SET {$columna} = CASE {$clave} ".implode(' ', $casos).
                    " ELSE {$columna} END WHERE {$clave} IN ({$marcadores})", array_merge($valores, $ids));
                if ($auditorias !== []) {
                    $modeloAudit->newQuery()->insert($auditorias);
                }
                foreach ($bloque as $modelo) {
                    $modelo->syncOriginal();
                }
            }
        }
    }
}
