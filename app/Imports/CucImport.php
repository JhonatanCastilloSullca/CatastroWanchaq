<?php

namespace App\Imports;

use App\Models\Ficha;
use App\Models\UniCat;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class CucImport implements ToCollection, WithHeadingRow
{
    private array $resultado = ['actualizadas' => 0, 'sin_cambios' => 0, 'sin_cuc' => 0, 'no_encontradas' => 0];

    public function collection(Collection $rows): void
    {
        $asignaciones = [];
        $errores = [];
        foreach ($rows as $indice => $row) {
            // Mantener las filas vacías en la lectura conserva el número real del Excel.
            if ($row->every(fn ($valor) => $valor === null || (is_string($valor) && trim($valor) === ''))) {
                continue;
            }
            if (! $row->has('cod_referencia') || ! $row->has('cuc')) {
                throw ValidationException::withMessages(['archivo' => 'El Excel debe contener las columnas cod_referencia y cuc.']);
            }
            $valor = trim((string) $row['cuc']);
            if ($valor === '') {
                $this->resultado['sin_cuc']++;
                continue;
            }
            $referencia = $row['cod_referencia'];
            $cuc = preg_replace('/\D/', '', $valor);
            if (! is_string($referencia) || trim($referencia) === '' || $cuc === '') {
                throw ValidationException::withMessages(['archivo' => 'Revise los códigos: cod_referencia debe conservarse como texto y el CUC debe contener dígitos.']);
            }
            if (strlen($cuc) > 12) {
                $fila = $indice + 2;
                $errores[] = "Fila {$fila}, referencia {$referencia}: el CUC «{$valor}» contiene ".strlen($cuc).' dígitos; el máximo permitido es 12. Corrija el código en el Excel.';
                continue;
            }
            $asignaciones[] = ['id' => trim($referencia), 'cuc' => $cuc];
        }
        if ($errores !== []) {
            throw ValidationException::withMessages(['archivo' => $errores]);
        }
        if ($asignaciones === []) {
            return;
        }
        DB::transaction(function () use ($asignaciones) {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement("SET LOCAL lock_timeout = '5s'");
                DB::statement("SET LOCAL statement_timeout = '20s'");
            }
            $inicio = microtime(true);
            // El Excel se lee una sola vez; solo se fraccionan las consultas a la base.
            foreach (array_chunk($asignaciones, 500) as $bloque) {
                $this->procesarBloque($bloque, $inicio);
            }
        });
    }

    private function procesarBloque(array $asignaciones, float $inicio): void
    {
        $ids = array_unique(array_column($asignaciones, 'id'));
        // Lecturas y escrituras por bloque, con auditoría individual de cada cambio.
        $unidades = UniCat::whereIn('id_uni_cat', $ids)->orderBy('id_uni_cat')->lockForUpdate()->get()->keyBy('id_uni_cat');
        $fichas = Ficha::whereIn('id_uni_cat', $ids)->orderBy('id_ficha')->lockForUpdate()->get()->groupBy('id_uni_cat');
        foreach ($asignaciones as $asignacion) {
            if (microtime(true) - $inicio > 90) {
                throw ValidationException::withMessages(['archivo' => 'La base de datos excedió el tiempo de importación. Se revirtieron los cambios; revise la carga y los bloqueos del servidor.']);
            }
            $unidad = $unidades->get($asignacion['id']);
            if (! $unidad) {
                $this->resultado['no_encontradas']++;
                continue;
            }
            $cambio = false;
            foreach (collect([$unidad])->concat($fichas->get($asignacion['id'], collect())) as $modelo) {
                $modelo->cuc = $asignacion['cuc'];
                if ($modelo->isDirty('cuc')) {
                    $cambio = true;
                }
            }
            $this->resultado[$cambio ? 'actualizadas' : 'sin_cambios']++;
        }
        app(\App\Services\ActualizarCucMasivoService::class)->guardar(
            $unidades->values()->concat($fichas->flatMap(fn ($grupo) => $grupo))
        );
        \Log::info('CUC: bloque procesado', ['segundos' => round(microtime(true) - $inicio, 2), 'resultado' => $this->resultado]);
    }

    public function resultado(): array
    {
        return $this->resultado;
    }

}
