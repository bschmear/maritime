<?php

declare(strict_types=1);

namespace App\Domain\AssetUnit\Support;

use App\Domain\AssetUnit\Models\AssetUnit;

final class AssetUnitGoogleSheetUnitResolver
{
    /**
     * @param  array<string, string|null>  $row
     * @return array{0: ?AssetUnit, 1: ?string}
     */
    public function resolve(array $row, int $sheetRowNumber): array
    {
        $hin = $this->identifierFromRow($row, [
            AssetUnitGoogleSheetColumnRegistry::HEADER_HIN,
            'Hull ID (HIN)',
            'HIN',
        ]);

        if ($hin !== '') {
            $matches = $this->findByField('hin', $hin);
            if (count($matches) === 1) {
                return [$matches[0], null];
            }

            if (count($matches) > 1) {
                return [null, 'Row '.$sheetRowNumber.": Multiple units match HID \"{$hin}\"."];
            }
        }

        $serial = $this->identifierFromRow($row, [
            AssetUnitGoogleSheetColumnRegistry::HEADER_SERIAL,
            'Serial Number',
            'Serial',
        ]);

        if ($serial !== '') {
            $matches = $this->findByField('serial_number', $serial);
            if (count($matches) === 1) {
                return [$matches[0], null];
            }

            if (count($matches) > 1) {
                return [null, 'Row '.$sheetRowNumber.": Multiple units match serial number \"{$serial}\"."];
            }
        }

        if ($hin !== '' && $serial !== '') {
            return [null, 'Row '.$sheetRowNumber.": No unit found for HID \"{$hin}\" or serial \"{$serial}\"."];
        }

        if ($hin !== '') {
            return [null, 'Row '.$sheetRowNumber.": No unit found for HID \"{$hin}\"."];
        }

        if ($serial !== '') {
            return [null, 'Row '.$sheetRowNumber.": No unit found for serial number \"{$serial}\"."];
        }

        return [null, null];
    }

    /**
     * @param  array<string, string|null>  $row
     * @param  list<string>  $headers
     */
    private function identifierFromRow(array $row, array $headers): string
    {
        foreach ($headers as $header) {
            $value = $row[$header] ?? null;
            if ($value !== null && trim($value) !== '') {
                return trim($value);
            }
        }

        return '';
    }

    /**
     * @return list<AssetUnit>
     */
    private function findByField(string $column, string $value): array
    {
        $normalized = $this->normalizeIdentifier($value);
        if ($normalized === '') {
            return [];
        }

        $sql = "UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE({$column}, ''), ' ', ''), '-', ''), '/', ''), '.', ''), ':', ''))";

        return AssetUnit::query()
            ->whereRaw("{$sql} = ?", [$normalized])
            ->get()
            ->all();
    }

    private function normalizeIdentifier(string $value): string
    {
        $clean = strtoupper(trim($value));

        return preg_replace('/[\s\-\/\.:]/', '', $clean) ?? '';
    }
}
