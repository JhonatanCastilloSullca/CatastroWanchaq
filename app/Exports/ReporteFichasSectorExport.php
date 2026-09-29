<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReporteFichasSectorExport extends DefaultValueBinder implements FromArray, WithColumnWidths, WithStrictNullComparison, WithStyles, WithTitle, WithCustomValueBinder
{
    public function __construct(private Collection $filas, private string $sector)
    {
    }

    public function title(): string
    {
        return 'Sector '.preg_replace('/[^a-zA-Z0-9_-]/', '', trim($this->sector));
    }

    public function bindValue(Cell $cell, $value)
    {
        if (in_array($cell->getColumn(), ['A', 'B'], true) && $value !== null) {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);
            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function array(): array
    {
        $rows = [['MZ', 'LOTE', 'UUCC', 'COTITULARES', 'ECONÓMICAS', 'BIEN COMÚN', 'NÚMERO DE FICHAS']];
        foreach ($this->filas as $fila) {
            $rows[] = [trim($fila->codi_mzna), trim($fila->codi_lote ?? ''),
                (int) $fila->uucc ?: null, (int) $fila->cotitulares ?: null,
                (int) $fila->economicas ?: null, (int) $fila->bien_comun ?: null, (int) $fila->total];
        }
        $last = count($rows);
        $totals = ['TOTAL', null];
        foreach (range('C', 'G') as $column) {
            $totals[] = $last > 1 ? "=SUM({$column}2:{$column}{$last})" : 0;
        }
        $rows[] = $totals;

        return $rows;
    }

    public function columnWidths(): array
    {
        return ['A' => 12, 'B' => 12, 'C' => 14, 'D' => 20, 'E' => 18, 'F' => 18, 'G' => 20];
    }

    public function styles(Worksheet $sheet)
    {
        $last = $this->filas->count() + 2;
        $sheet->getStyle("A1:G{$last}")->applyFromArray([
            'font' => ['name' => 'Arial', 'size' => 11],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
        ]);
        $sheet->getStyle('A1:G1')->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'B97DA3']],
            'alignment' => ['wrapText' => true],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(40);
        $row = 2;
        foreach ($this->filas->groupBy('id_mzna') as $grupo) {
            $end = $row + $grupo->count() - 1;
            if ($end > $row) {
                $sheet->mergeCells("A{$row}:A{$end}");
            }
            $sheet->getStyle("A{$end}:G{$end}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM);
            $row = $end + 1;
        }
        $sheet->mergeCells("A{$last}:B{$last}");
        $sheet->getStyle("A{$last}:G{$last}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EADCE6']],
        ]);
        $sheet->getStyle("C2:G{$last}")->getNumberFormat()->setFormatCode('0');
        $sheet->freezePane('C2');
        $sheet->getPageSetup()->setOrientation('landscape')->setPaperSize(9)->setFitToWidth(1)->setFitToHeight(0);
        $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 1);
        $sheet->getHeaderFooter()->setOddHeader('&CReporte de fichas - Sector '.trim($this->sector));
    }
}
