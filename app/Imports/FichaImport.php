<?php

namespace App\Imports;

use App\Models\Edificaciones;
use App\Models\Ficha;
use App\Models\HabUrbana;
use App\Models\Institucion;
use App\Models\Lote;
use App\Models\Manzana;
use App\Models\UniCat;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class FichaImport implements ToCollection, WithHeadingRow
{
    public function headingRow(): int
    {
        return 2;
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            if (empty($row['nume_ficha'])) {
                continue;
            }

            $ubigeo = Institucion::first();
            $mznabuscar = str_pad($ubigeo->id_institucion, 6, '0', STR_PAD_LEFT) . '' . str_pad($row['sector'], 2, '0', STR_PAD_LEFT) . '' . str_pad($row['manzana'], 3, '0', STR_PAD_LEFT);

            $mzna= Manzana::updateOrCreate([
                'id_mzna' => $mznabuscar,
            ],[
                'id_sector' => str_pad($ubigeo->id_institucion, 6, '0', STR_PAD_LEFT) . '' . str_pad($row['sector'], 2, '0', STR_PAD_LEFT),
                'codi_mzna' => str_pad($row['manzana'], 3, '0', STR_PAD_LEFT),
                'nume_mzna' => str_pad($row['manzana'], 3, '0', STR_PAD_LEFT),
            ]);

            $lotebuscar = str_pad($ubigeo->id_institucion, 6, '0', STR_PAD_LEFT) . '' . str_pad($row['sector'], 2, '0', STR_PAD_LEFT) . '' . str_pad($row['manzana'], 3, '0', STR_PAD_LEFT) . '' . str_pad($row['lote'], 3, '0', STR_PAD_LEFT);

            $habilitacion = HabUrbana::where('codi_hab_urba',$codigo = strtok($row['codigo_hurbano'], '_'))->first();

            $lote= Lote::updateOrCreate([
                'id_lote' => $lotebuscar,
            ],[
                'id_mzna' => $mzna->id_mzna,
                'codi_lote' => str_pad($row['lote'], 3, '0', STR_PAD_LEFT),
                'id_hab_urba' => $habilitacion->id_hab_urba,
                'mzna_dist' => $row['manzana_ubiacion'],
                'lote_dist' => $row['lote_dist'],
                'sub_lote_dist' => $row['sublote'],
                'zona_dist' => $row['zona_sector_etapa'],
            ]);

            $edificacionbuscar = str_pad($lote->id_lote, 14, '0', STR_PAD_LEFT) . '' . str_pad($row['edifica'], 2, '0', STR_PAD_LEFT);
            $edificacion= Edificaciones::updateOrCreate([
                'id_edificacion' => $edificacionbuscar,
            ],[
                'id_lote' => $lote->id_lote,
                'codi_edificacion' => str_pad($row['edifica'], 2, '0', STR_PAD_LEFT),
                'tipo_edificacion' => $row['tipo_edificacion'],
            ]);

            $unicatbuscar = str_pad($edificacion->id_edificacion, 16, '0', STR_PAD_LEFT) . '' . str_pad($row['entrada'], 2, '0', STR_PAD_LEFT) . '' . str_pad($row['piso'], 2, '0', STR_PAD_LEFT) . '' . str_pad($row['unidad'], 3, '0', STR_PAD_LEFT);

            $unicat= UniCat::updateOrCreate([
                'id_uni_cat' => $unicatbuscar,
            ],[
                'id_lote' => $lote->id_lote,
                'id_edificacion' => $edificacion->id_edificacion,
                'codi_entrada' => str_pad($row['entrada'], 2, '0', STR_PAD_LEFT),
                'codi_piso' => str_pad($row['piso'], 2, '0', STR_PAD_LEFT),
                'codi_unidad' => str_pad($row['unidad'], 3, '0', STR_PAD_LEFT),
                'tipo_interior' => $row['tipo_interior'],
                'cuc' => str_pad($row['cuc'], 12, '0', STR_PAD_LEFT),
                'codi_pred_rentas' => $row['codi_pred_rentas'],
                'nume_interior' => $row['n_interior'],
                'codi_cont_rentas' => $row['codi_cont_rentas'],
            ]);

            if($row['declarante_dni'] || $row['declarante_nombre']){

            }

            $ficha = Ficha::updateOrCreate(
                [
                    'nume_ficha' => trim($row['nume_ficha']),
                ],
                [
                    'nume_ficha_lote' => $row['nume_ficha_lote'] ?? null,
                    'cuc' => $row['cuc'] ?? null,
                    'sector' => $row['sector'] ?? null,
                    'manzana' => $row['manzana'] ?? null,
                    'lote' => $row['lote'] ?? null,
                    'edifica' => $row['edifica'] ?? null,
                    'entrada' => $row['entrada'] ?? null,
                    'piso' => $row['piso'] ?? null,
                    'unidad' => $row['unidad'] ?? null,
                    'dc' => $row['dc'] ?? null,
                ]
            );
        }
    }
}
