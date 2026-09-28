<?php

namespace App\Imports;

use App\Models\Ficha;
use App\Models\UniCat;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class CucImport implements ToCollection, WithHeadingRow, WithChunkReading, SkipsEmptyRows
{
    private array $resultado = ['actualizadas' => 0, 'sin_cambios' => 0, 'sin_cuc' => 0, 'no_encontradas' => 0];

    public function collection(Collection $rows): void
    {
        $asignaciones = [];
        foreach ($rows as $row) {
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
            $asignaciones[] = ['id' => trim($referencia), 'cuc' => $cuc];
        }
        if ($asignaciones === []) {
            return;
        }
        DB::transaction(function () use ($asignaciones) {
            $ids = array_unique(array_column($asignaciones, 'id'));
            // Dos lecturas por bloque; guardar modelos conserva los eventos y la auditoría.
            $unidades = UniCat::whereIn('id_uni_cat', $ids)->orderBy('id_uni_cat')->lockForUpdate()->get()->keyBy('id_uni_cat');
            $fichas = Ficha::whereIn('id_uni_cat', $ids)->orderBy('id_ficha')->lockForUpdate()->get()->groupBy('id_uni_cat');
            foreach ($asignaciones as $asignacion) {
                $unidad = $unidades->get($asignacion['id']);
                if (! $unidad) {
                    $this->resultado['no_encontradas']++;
                    continue;
                }
                $cambio = false;
                foreach (collect([$unidad])->concat($fichas->get($asignacion['id'], collect())) as $modelo) {
                    $modelo->cuc = $asignacion['cuc'];
                    if ($modelo->isDirty('cuc')) {
                        $modelo->save();
                        $cambio = true;
                    }
                }
                $this->resultado[$cambio ? 'actualizadas' : 'sin_cambios']++;
            }
        });
    }

    public function resultado(): array
    {
        return $this->resultado;
    }

    public function chunkSize(): int
    {
        return 500;
    }
}
