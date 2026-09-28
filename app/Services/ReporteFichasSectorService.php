<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReporteFichasSectorService
{
    public const TIPOS = [
        '01' => ['nombre' => 'Individual', 'columna' => 'uucc', 'ruta' => 'pdf.individual'],
        '02' => ['nombre' => 'Cotitularidad', 'columna' => 'cotitulares', 'ruta' => 'pdf.cotitularidad'],
        '03' => ['nombre' => 'Económica', 'columna' => 'economicas', 'ruta' => 'pdf.economica'],
        '04' => ['nombre' => 'Bien común', 'columna' => 'bien_comun', 'ruta' => 'pdf.bienescomunes'],
    ];

    public function fichas(string $sector): Builder
    {
        return DB::table('catastro.tf_fichas as f')
            ->join('catastro.tf_lotes as l', 'l.id_lote', '=', 'f.id_lote')
            ->join('catastro.tf_manzanas as m', 'm.id_mzna', '=', 'l.id_mzna')
            ->where('m.id_sector', $sector)
            ->where('f.activo', '1')
            ->whereIn(DB::raw('TRIM(f.tipo_ficha)'), array_keys(self::TIPOS));
    }

    public function resumen(string $sector, ?string $manzana = null): Collection
    {
        $query = DB::table('catastro.tf_manzanas as m')
            ->leftJoin('catastro.tf_lotes as l', 'l.id_mzna', '=', 'm.id_mzna')
            ->leftJoin('catastro.tf_fichas as f', function ($join) {
                $join->on('f.id_lote', '=', 'l.id_lote')
                    ->where('f.activo', '1')
                    ->whereIn(DB::raw('TRIM(f.tipo_ficha)'), array_keys(self::TIPOS));
            })
            ->where('m.id_sector', $sector);

        if ($manzana !== null) {
            $query->where('m.id_mzna', $manzana)
                ->whereNotNull('l.id_lote')
                ->select('l.id_lote as id', 'l.codi_lote as codigo')
                ->groupBy('l.id_lote', 'l.codi_lote');
        } else {
            $query->select('m.id_mzna as id', 'm.codi_mzna as codigo')
                ->groupBy('m.id_mzna', 'm.codi_mzna');
        }

        foreach (self::TIPOS as $tipo => $config) {
            $query->selectRaw("COUNT(CASE WHEN TRIM(f.tipo_ficha) = ? THEN f.id_ficha END) AS {$config['columna']}", [$tipo]);
        }

        return $query->selectRaw('COUNT(f.id_ficha) AS total')->get()
            ->sort(fn ($a, $b) => strnatcasecmp(trim($a->codigo), trim($b->codigo)))
            ->values();
    }
}
