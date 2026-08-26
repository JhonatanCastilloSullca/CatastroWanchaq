<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CantidadFichasSectorExport implements
    FromCollection,
    WithHeadings,
    WithMapping,
    ShouldAutoSize,
    WithStyles,
    WithTitle
{
    protected string $sector;

    public function __construct(string $sector)
    {
        $this->sector = $sector;
    }

    public function collection(): Collection
    {
        $filas = DB::table('catastro.tf_manzanas as m')
            ->join(
                'catastro.tf_sectores as s',
                's.id_sector',
                '=',
                'm.id_sector'
            )
            ->leftJoin(
                'catastro.tf_lotes as l',
                'l.id_mzna',
                '=',
                'm.id_mzna'
            )
            ->leftJoin('catastro.tf_fichas as f', function ($join) {
                $join->on('f.id_lote', '=', 'l.id_lote')
                    ->where('f.activo', '=', 1);
            })
            ->whereRaw(
                'TRIM(s.codi_sector) = ?',
                [trim($this->sector)]
            )
            ->selectRaw("
                TRIM(m.nume_mzna) AS manzana,

                COUNT(f.id_ficha) FILTER (
                    WHERE TRIM(f.tipo_ficha) = '01'
                ) AS individuales,

                COUNT(f.id_ficha) FILTER (
                    WHERE TRIM(f.tipo_ficha) = '02'
                ) AS cotitulares,

                COUNT(f.id_ficha) FILTER (
                    WHERE TRIM(f.tipo_ficha) = '03'
                ) AS economicas,

                COUNT(f.id_ficha) FILTER (
                    WHERE TRIM(f.tipo_ficha) = '04'
                ) AS bienes_comunes,

                COUNT(f.id_ficha) FILTER (
                    WHERE TRIM(f.tipo_ficha) = '05'
                ) AS bienes_culturales,

                COUNT(f.id_ficha) FILTER (
                    WHERE TRIM(f.tipo_ficha) IN ('01', '02', '03', '04', '05')
                ) AS total
            ")
            ->groupBy('m.id_mzna', 'm.nume_mzna')
            ->orderByRaw("
                CASE
                    WHEN TRIM(m.nume_mzna) ~ '^[0-9]+$'
                    THEN TRIM(m.nume_mzna)::INTEGER
                    ELSE 999999
                END
            ")
            ->orderByRaw('TRIM(m.nume_mzna)')
            ->get();

        /*
        * Agregamos una última fila con los totales
        * de cada tipo de ficha.
        */
        $filas->push((object) [
            'manzana'          => 'TOTAL GENERAL',
            'individuales'     => $filas->sum('individuales'),
            'cotitulares'      => $filas->sum('cotitulares'),
            'economicas'       => $filas->sum('economicas'),
            'bienes_comunes'   => $filas->sum('bienes_comunes'),
            'bienes_culturales'=> $filas->sum('bienes_culturales'),
            'total'            => $filas->sum('total'),
        ]);

        return $filas;
    }

    public function headings(): array
    {
        return [
            'MANZANA',
            'F. INDIVIDUAL',
            'F. COTITULAR',
            'F. ECONÓMICA',
            'F. BIEN COMÚN',
            'F. BIEN CULTURAL',
            'TOTAL',
        ];
    }

    public function map($fila): array
    {
        return [
            $fila->manzana,
            (int) $fila->individuales,
            (int) $fila->cotitulares,
            (int) $fila->economicas,
            (int) $fila->bienes_comunes,
            (int) $fila->bienes_culturales,
            (int) $fila->total,
        ];
    }

    public function title(): string
    {
        return 'Sector ' . trim($this->sector);
    }

    public function styles(Worksheet $sheet): array
    {
        $ultimaFila = $sheet->getHighestRow();

        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:G{$ultimaFila}");

        // Encabezados
        $sheet->getStyle('A1:G1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['ARGB' => 'FFFFFFFF'],
            ],
            'fill' => [
                'fillType' => 'solid',
                'startColor' => ['ARGB' => 'FF292929'],
            ],
            'alignment' => [
                'horizontal' => 'center',
                'vertical' => 'center',
            ],
        ]);

        // Bordes de toda la tabla
        $sheet->getStyle("A1:G{$ultimaFila}")
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle('thin');

        // Contenido centrado
        $sheet->getStyle("A2:G{$ultimaFila}")
            ->getAlignment()
            ->setHorizontal('center')
            ->setVertical('center');

        // Colores por tipo de ficha
        if ($ultimaFila > 2) {
            // No coloreamos aquí la última fila de totales
            $ultimaFilaDetalle = $ultimaFila - 1;

            $sheet->getStyle("B2:B{$ultimaFilaDetalle}")
                ->getFill()
                ->setFillType('solid')
                ->getStartColor()
                ->setARGB('FFD9EAF7');

            $sheet->getStyle("C2:C{$ultimaFilaDetalle}")
                ->getFill()
                ->setFillType('solid')
                ->getStartColor()
                ->setARGB('FFF8CBAD');

            $sheet->getStyle("D2:D{$ultimaFilaDetalle}")
                ->getFill()
                ->setFillType('solid')
                ->getStartColor()
                ->setARGB('FFC6E0B4');

            $sheet->getStyle("E2:E{$ultimaFilaDetalle}")
                ->getFill()
                ->setFillType('solid')
                ->getStartColor()
                ->setARGB('FFF4B6B6');

            $sheet->getStyle("F2:F{$ultimaFilaDetalle}")
                ->getFill()
                ->setFillType('solid')
                ->getStartColor()
                ->setARGB('FFE4D4F4');
        }

        // Total por manzana en negrita
        $sheet->getStyle("G2:G{$ultimaFila}")
            ->getFont()
            ->setBold(true);

        // Fila total general
        $sheet->getStyle("A{$ultimaFila}:G{$ultimaFila}")
            ->applyFromArray([
                'font' => [
                    'bold' => true,
                    'color' => ['ARGB' => 'FFFFFFFF'],
                ],
                'fill' => [
                    'fillType' => 'solid',
                    'startColor' => ['ARGB' => 'FF198754'],
                ],
                'alignment' => [
                    'horizontal' => 'center',
                    'vertical' => 'center',
                ],
            ]);

        return [];
    }
}