<?php

declare(strict_types=1);

namespace App\Domain\AssetUnit\Support\InvoiceImport;

use App\Domain\AssetUnit\Models\AssetUnit;
use Illuminate\Support\Collection;

class InvoiceExistingUnitDetector
{
    /**
     * Flag rows whose HIN or serial number already exists on an asset unit.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public function apply(array $rows, bool $autoExclude = true): array
    {
        if ($rows === []) {
            return [];
        }

        [$hinList, $serialList] = $this->collectIdentifiers($rows);
        $existingByHin = $this->lookupByHins($hinList);
        $existingBySerial = $this->lookupBySerials($serialList);

        $out = [];
        foreach ($rows as $row) {
            $existing = $this->findExistingUnit($row, $existingByHin, $existingBySerial);

            if ($existing !== null) {
                $row['already_exists'] = true;
                $row['existing_asset_unit_id'] = $existing->id;
                $row['existing_asset_unit_label'] = $this->existingUnitLabel($existing);
                $row['existing_match_field'] = $this->matchedField($row, $existing);
                if ($autoExclude) {
                    $row['include'] = false;
                }
            } else {
                $row['already_exists'] = false;
                $row['existing_asset_unit_id'] = null;
                $row['existing_asset_unit_label'] = null;
                $row['existing_match_field'] = null;
            }

            $out[] = $row;
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{0: list<string>, 1: list<string>}
     */
    protected function collectIdentifiers(array $rows): array
    {
        $hins = [];
        $serials = [];

        foreach ($rows as $row) {
            $hin = $this->normalizeIdentifier($row['hin'] ?? null);
            if ($hin !== null) {
                $hins[] = $hin;
            }

            $serial = $this->normalizeIdentifier($row['serial_number'] ?? null);
            if ($serial !== null) {
                $serials[] = $serial;
            }
        }

        return [array_values(array_unique($hins)), array_values(array_unique($serials))];
    }

    /**
     * @param  list<string>  $hins
     * @return Collection<string, AssetUnit>
     */
    protected function lookupByHins(array $hins): Collection
    {
        if ($hins === []) {
            return collect();
        }

        return AssetUnit::query()
            ->whereNotNull('hin')
            ->where('hin', '!=', '')
            ->where(function ($query) use ($hins): void {
                foreach ($hins as $hin) {
                    $query->orWhereRaw('UPPER(TRIM(hin)) = ?', [$hin]);
                }
            })
            ->get(['id', 'hin', 'serial_number', 'asset_id'])
            ->keyBy(fn (AssetUnit $unit) => $this->normalizeIdentifier($unit->hin) ?? '');
    }

    /**
     * @param  list<string>  $serials
     * @return Collection<string, AssetUnit>
     */
    protected function lookupBySerials(array $serials): Collection
    {
        if ($serials === []) {
            return collect();
        }

        return AssetUnit::query()
            ->whereNotNull('serial_number')
            ->where('serial_number', '!=', '')
            ->where(function ($query) use ($serials): void {
                foreach ($serials as $serial) {
                    $query->orWhereRaw('UPPER(TRIM(serial_number)) = ?', [$serial]);
                }
            })
            ->get(['id', 'hin', 'serial_number', 'asset_id'])
            ->keyBy(fn (AssetUnit $unit) => $this->normalizeIdentifier($unit->serial_number) ?? '');
    }

    /**
     * @param  Collection<string, AssetUnit>  $existingByHin
     * @param  Collection<string, AssetUnit>  $existingBySerial
     */
    protected function findExistingUnit(array $row, Collection $existingByHin, Collection $existingBySerial): ?AssetUnit
    {
        $hin = $this->normalizeIdentifier($row['hin'] ?? null);
        if ($hin !== null && $existingByHin->has($hin)) {
            return $existingByHin->get($hin);
        }

        $serial = $this->normalizeIdentifier($row['serial_number'] ?? null);
        if ($serial !== null && $existingBySerial->has($serial)) {
            return $existingBySerial->get($serial);
        }

        return null;
    }

    protected function matchedField(array $row, AssetUnit $existing): string
    {
        $hin = $this->normalizeIdentifier($row['hin'] ?? null);
        $existingHin = $this->normalizeIdentifier($existing->hin);

        if ($hin !== null && $existingHin === $hin) {
            return 'hin';
        }

        return 'serial_number';
    }

    protected function existingUnitLabel(AssetUnit $unit): string
    {
        if (is_string($unit->hin) && trim($unit->hin) !== '') {
            return 'HIN: '.trim($unit->hin);
        }

        if (is_string($unit->serial_number) && trim($unit->serial_number) !== '') {
            return 'SN: '.trim($unit->serial_number);
        }

        return 'Unit #'.$unit->id;
    }

    protected function normalizeIdentifier(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = strtoupper(trim((string) $value));

        return $normalized !== '' ? $normalized : null;
    }
}
