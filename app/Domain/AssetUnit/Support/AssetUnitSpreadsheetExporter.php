<?php

declare(strict_types=1);

namespace App\Domain\AssetUnit\Support;

use App\Domain\AssetUnit\Models\AssetUnit;
use App\Enums\Inventory\UnitCondition;
use App\Enums\Inventory\UnitStatus;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssetUnitSpreadsheetExporter
{
    /** @var list<string> */
    public const HEADERS = [
        'ID',
        'Asset',
        'Serial Number',
        'HIN',
        'SKU',
        'Status',
        'Condition',
        'Cost',
        'Asking Price',
    ];

    /**
     * @param  Collection<int, AssetUnit>  $units
     */
    public function toCsv(Collection $units): StreamedResponse
    {
        $filename = 'asset-units-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($units): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, self::HEADERS);

            foreach ($units as $unit) {
                fputcsv($handle, $this->rowValues($unit));
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * @param  Collection<int, AssetUnit>  $units
     */
    public function toXlsx(Collection $units): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Asset Units');

        foreach (self::HEADERS as $colIndex => $header) {
            $sheet->setCellValue([$colIndex + 1, 1], $header);
        }

        $rowIndex = 2;
        foreach ($units as $unit) {
            foreach ($this->rowValues($unit) as $colIndex => $value) {
                $sheet->setCellValue([$colIndex + 1, $rowIndex], $value);
            }
            $rowIndex++;
        }

        $this->applyEnumDropdowns($sheet, max(2, $rowIndex - 1));
        $this->addReferenceSheet($spreadsheet);

        $filename = 'asset-units-'.now()->format('Y-m-d').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet): void {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @return list<string|int|float|null>
     */
    private function rowValues(AssetUnit $unit): array
    {
        return [
            $unit->id,
            $unit->asset?->display_name ?? '',
            $unit->serial_number ?? '',
            $unit->hin ?? '',
            $unit->sku ?? '',
            $this->statusLabel($unit->status),
            $this->conditionLabel($unit->condition),
            $unit->cost,
            $unit->asking_price,
        ];
    }

    private function statusLabel(?int $statusId): string
    {
        foreach (UnitStatus::options() as $option) {
            if ((int) $option['id'] === (int) $statusId) {
                return (string) $option['name'];
            }
        }

        return '';
    }

    private function conditionLabel(?int $conditionId): string
    {
        foreach (UnitCondition::options() as $option) {
            if ((int) $option['id'] === (int) $conditionId) {
                return (string) $option['name'];
            }
        }

        return '';
    }

    private function applyEnumDropdowns(Worksheet $sheet, int $lastDataRow): void
    {
        if ($lastDataRow < 2) {
            $lastDataRow = 500;
        }

        $statusList = '"'.implode(',', array_map(
            fn (array $o) => str_replace('"', '""', (string) $o['name']),
            UnitStatus::options(),
        )).'"';

        $conditionList = '"'.implode(',', array_map(
            fn (array $o) => str_replace('"', '""', (string) $o['name']),
            UnitCondition::options(),
        )).'"';

        for ($row = 2; $row <= $lastDataRow; $row++) {
            $this->setListValidation($sheet, "F{$row}", $statusList);
            $this->setListValidation($sheet, "G{$row}", $conditionList);
        }
    }

    private function setListValidation(Worksheet $sheet, string $cellCoordinate, string $formula): void
    {
        $validation = $sheet->getCell($cellCoordinate)->getDataValidation();
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setAllowBlank(true);
        $validation->setShowDropDown(true);
        $validation->setFormula1($formula);
    }

    private function addReferenceSheet(Spreadsheet $spreadsheet): void
    {
        $ref = $spreadsheet->createSheet();
        $ref->setTitle('Reference');

        $ref->setCellValue('A1', 'Status options');
        foreach (UnitStatus::options() as $index => $option) {
            $ref->setCellValue('A'.($index + 2), $option['name']);
        }

        $ref->setCellValue('B1', 'Condition options');
        foreach (UnitCondition::options() as $index => $option) {
            $ref->setCellValue('B'.($index + 2), $option['name']);
        }
    }
}
