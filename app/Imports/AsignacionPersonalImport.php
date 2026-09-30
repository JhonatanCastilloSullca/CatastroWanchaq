<?php

namespace App\Imports;

use App\Models\Ficha;
use App\Models\Persona;
use App\Models\UniCat;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

abstract class AsignacionPersonalImport implements ToCollection, WithHeadingRow
{
    protected int $funcion;
    protected string $responsable;
    protected string $fecha;
    private array $resultado = ['actualizadas' => 0, 'sin_cambios' => 0, 'omitidas' => 0];

    public function collection(Collection $rows): void
    {
        $errores = [];
        $datos = [];
        $columnas = ['cod_referencia', 'nume_doc', $this->fecha];
        if ($this->funcion === 4) {
            $columnas[] = 'nume_registro';
        }
        foreach ($rows as $indice => $row) {
            if ($row->every(fn ($v) => $v === null || (is_string($v) && trim($v) === ''))) {
                continue;
            }
            $fila = $indice + 2;
            foreach ($columnas as $columna) {
                if (! $row->has($columna)) {
                    throw ValidationException::withMessages(['archivo' => "Falta la columna {$columna}. Use la plantilla de esta opción."]);
                }
            }
            $doc = trim((string) $row['nume_doc']);
            $registro = trim((string) ($row['nume_registro'] ?? ''));
            $valorFecha = $row[$this->fecha];
            if ($doc === '' && blank($valorFecha) && $registro === '') {
                $this->resultado['omitidas']++;
                continue;
            }
            $referencia = $row['cod_referencia'];
            if (! is_string($referencia) || trim($referencia) === '' || mb_strlen(trim($referencia)) > 23) {
                $errores[] = "Fila {$fila}: cod_referencia debe ser texto de hasta 23 caracteres. Conserve el código exportado.";
            }
            if ($doc === '' || mb_strlen($doc) > 17) {
                $errores[] = "Fila {$fila}: nume_doc es obligatorio y admite hasta 17 caracteres.";
            }
            if ($this->funcion === 4 && mb_strlen($registro) > 10) {
                $errores[] = "Fila {$fila}: nume_registro admite hasta 10 caracteres; se recibieron ".mb_strlen($registro).'.';
            }
            try {
                $fecha = $this->normalizarFecha($valorFecha);
            } catch (\InvalidArgumentException $e) {
                $errores[] = "Fila {$fila}: {$this->fecha} no es válida. Use una fecha de Excel, AAAA-MM-DD o DD/MM/AAAA.";
                $fecha = null;
            }
            $datos[] = ['fila' => $fila, 'referencia' => trim((string) $referencia), 'documento' => $doc,
                'fecha' => $fecha, 'registro' => $registro === '' ? null : $registro];
        }
        if ($errores !== []) {
            throw ValidationException::withMessages(['archivo' => $errores]);
        }

        DB::transaction(function () use ($datos) {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement("SET LOCAL lock_timeout = '5s'");
                DB::statement("SET LOCAL statement_timeout = '20s'");
            }
            $inicio = microtime(true);
            $preparadas = [];
            $errores = [];
            foreach (array_chunk($datos, 500) as $bloque) {
                $unidades = UniCat::whereIn('id_uni_cat', array_column($bloque, 'referencia'))
                    ->orderBy('id_uni_cat')->lockForUpdate()->get()->keyBy('id_uni_cat');
                $personas = Persona::whereIn('nume_doc', array_column($bloque, 'documento'))
                    ->where('tipo_funcion', $this->funcion)->orderBy('id_persona')->lockForUpdate()->get()->groupBy('nume_doc');
                foreach ($bloque as $dato) {
                    if (! $unidades->has($dato['referencia'])) {
                        $errores[] = "Fila {$dato['fila']}: no existe la unidad {$dato['referencia']}.";
                    }
                    $coincidencias = $personas->get($dato['documento'], collect());
                    if ($coincidencias->count() !== 1) {
                        $errores[] = "Fila {$dato['fila']}: documento {$dato['documento']} sin responsable único para esta función. Revise el documento y la función registrada.";
                    } else {
                        $dato['persona'] = $coincidencias->first()->id_persona;
                        $preparadas[] = $dato;
                    }
                }
            }
            if ($errores !== []) {
                throw ValidationException::withMessages(['archivo' => $errores]);
            }
            foreach (array_chunk($preparadas, 500) as $bloque) {
                $fichas = Ficha::whereIn('id_uni_cat', array_column($bloque, 'referencia'))
                    ->orderBy('id_ficha')->lockForUpdate()->get()->groupBy('id_uni_cat');
                foreach ($bloque as $dato) {
                    $asociadas = $fichas->get($dato['referencia'], collect());
                    if ($asociadas->isEmpty()) {
                        throw ValidationException::withMessages(['archivo' => "Fila {$dato['fila']}: la unidad no tiene fichas para actualizar."]);
                    }
                    $cambio = false;
                    foreach ($asociadas as $ficha) {
                        if (microtime(true) - $inicio > 90) {
                            throw ValidationException::withMessages(['archivo' => 'La base excedió el tiempo de importación. Se revirtieron los cambios. Revise la carga del servidor.']);
                        }
                        $ficha->{$this->responsable} = $dato['persona'];
                        $ficha->{$this->fecha} = $dato['fecha'];
                        if ($this->funcion === 4) {
                            $ficha->nume_registro = $dato['registro'];
                        }
                        if ($ficha->isDirty()) {
                            $ficha->save();
                            $cambio = true;
                        }
                    }
                    $this->resultado[$cambio ? 'actualizadas' : 'sin_cambios']++;
                }
            }
        });
    }

    private function normalizarFecha($valor): ?string
    {
        if (blank($valor)) {
            return null;
        }
        if ((is_int($valor) || is_float($valor)) && is_finite((float) $valor) && $valor >= 1 && $valor < 2958466) {
            return Date::excelToDateTimeObject($valor)->format('Y-m-d');
        }
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $formato) {
            $texto = trim((string) $valor);
            $fecha = \DateTimeImmutable::createFromFormat('!'.$formato, $texto);
            if ($fecha && $fecha->format($formato) === $texto && (int) $fecha->format('Y') > 0) {
                return $fecha->format('Y-m-d');
            }
        }
        throw new \InvalidArgumentException('Fecha inválida');
    }

    public function resultado(): array
    {
        return $this->resultado;
    }
}
