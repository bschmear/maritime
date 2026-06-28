<?php

declare(strict_types=1);

namespace App\Support\Asset;

use App\Domain\InventoryCatalog\Support\CatalogImportSpecSync;
use App\Enums\Inventory\BoatType;
use App\Enums\Inventory\HullMaterial;
use App\Enums\Inventory\HullType;
use App\Support\ManufacturerCatalog;
use JsonException;

/**
 * Resolve specs from app/AssetInformation/{brand}/meta.json before calling OpenAI.
 */
final class AssetInformationSpecLookup
{
    /**
     * @param  array{
     *   tenant_id: string,
     *   model_name: string,
     *   make_label?: string|null,
     *   model_label?: string|null,
     *   spec_fields: list<array{name: string, type: string, unit: string|null, required: bool}>
     * }  $context
     * @return array<string, mixed>|null AI-shaped payload for BoatSpecFillerService::finalize()
     */
    public static function resolve(array $context): ?array
    {
        $makeLabel = trim((string) ($context['make_label'] ?? ''));
        if ($makeLabel === '') {
            return null;
        }

        $brandSlug = ManufacturerCatalog::slugByDisplayName($makeLabel);
        if ($brandSlug === null) {
            return null;
        }

        $metaPath = base_path('app/AssetInformation/'.$brandSlug.'/meta.json');
        if (! is_readable($metaPath)) {
            return null;
        }

        try {
            $catalog = self::decodeJsonFile($metaPath);
        } catch (JsonException) {
            return null;
        }

        if (! is_array($catalog) || $catalog === []) {
            return null;
        }

        $match = self::findBestMatch(
            $catalog,
            (string) ($context['model_label'] ?? ''),
            (string) $context['model_name'],
            $makeLabel,
        );

        if ($match === null) {
            return null;
        }

        $specs = self::mapToSpecFields($match, $context['spec_fields']);

        if (! self::hasAnyValue($specs)) {
            return null;
        }

        return [
            'tenant_id' => (string) $context['tenant_id'],
            'model_name' => (string) $context['model_name'],
            'specs' => $specs,
            'confidence' => 0.92,
            'data_source_type' => 'manufacturer_verified',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $catalog
     * @return array<string, mixed>|null
     */
    private static function findBestMatch(
        array $catalog,
        string $modelLabel,
        string $modelName,
        string $makeLabel,
    ): ?array {
        $candidates = array_filter([
            $modelLabel,
            $modelName,
            self::stripMakePrefix($modelName, $makeLabel),
            self::stripMakePrefix($modelLabel, $makeLabel),
        ], fn (string $v) => trim($v) !== '');

        $best = null;
        $bestScore = 0;

        foreach ($catalog as $row) {
            if (! is_array($row)) {
                continue;
            }

            $score = self::scoreRow($row, $candidates);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $row;
            }
        }

        return $bestScore >= 70 ? $best : null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $candidates
     */
    private static function scoreRow(array $row, array $candidates): int
    {
        $name = self::normalize((string) ($row['name'] ?? ''));
        $id = self::normalize(str_replace('-', ' ', (string) ($row['id'] ?? '')));

        if ($name === '' && $id === '') {
            return 0;
        }

        $best = 0;
        foreach ($candidates as $candidate) {
            $needle = self::normalize($candidate);
            if ($needle === '') {
                continue;
            }

            if ($name !== '' && $needle === $name) {
                $best = max($best, 100);
            }
            if ($id !== '' && $needle === $id) {
                $best = max($best, 95);
            }
            if ($name !== '' && (str_contains($needle, $name) || str_contains($name, $needle))) {
                $best = max($best, 85);
            }
            if ($id !== '' && (str_contains($needle, $id) || str_contains($id, $needle))) {
                $best = max($best, 80);
            }
        }

        return $best;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<array{name: string, type: string, unit: string|null, required: bool}>  $specFields
     * @return array<string, mixed>
     */
    private static function mapToSpecFields(array $row, array $specFields): array
    {
        /** @var array<string, mixed> $specs */
        $specs = [];
        foreach ($specFields as $field) {
            $specs[(string) $field['name']] = null;
        }

        $layer = is_array($row['specifications'] ?? null) ? $row['specifications'] : [];

        if (array_key_exists('length', $specs) && isset($layer['length_mm']) && is_numeric($layer['length_mm'])) {
            $specs['length'] = (int) round((float) $layer['length_mm']);
        }

        if (array_key_exists('width', $specs) && isset($layer['width_mm']) && is_numeric($layer['width_mm'])) {
            $specs['width'] = (int) round((float) $layer['width_mm']);
        }

        if (array_key_exists('hull_type', $specs) && is_string($row['hull_type_key'] ?? null)) {
            $specs['hull_type'] = HullType::tryFrom($row['hull_type_key'])?->id();
        }

        if (array_key_exists('hull_material', $specs) && is_string($row['hull_material_key'] ?? null)) {
            $specs['hull_material'] = HullMaterial::tryFrom($row['hull_material_key'])?->id();
        }

        if (array_key_exists('boat_type', $specs) && is_string($row['boat_type_key'] ?? null)) {
            $specs['boat_type'] = BoatType::tryFrom($row['boat_type_key'])?->id();
        }

        if (array_key_exists('boat_weight', $specs) && isset($layer['weight_kg']) && is_numeric($layer['weight_kg'])) {
            $specs['boat_weight'] = CatalogImportSpecSync::kilogramsToPounds((int) round((float) $layer['weight_kg']));
        }

        if (array_key_exists('max_people', $specs) && isset($layer['capacity_persons']) && is_numeric($layer['capacity_persons'])) {
            $specs['max_people'] = (int) round((float) $layer['capacity_persons']);
        }

        if (array_key_exists('max_hp', $specs) && isset($layer['max_hp']) && is_numeric($layer['max_hp'])) {
            $specs['max_hp'] = (int) round((float) $layer['max_hp']);
        }

        return $specs;
    }

    /**
     * @param  array<string, mixed>  $specs
     */
    public static function hasAnyValue(array $specs): bool
    {
        foreach ($specs as $value) {
            if ($value !== null && $value !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $aiResult
     */
    public static function resultHasValues(array $aiResult): bool
    {
        $specs = $aiResult['specs'] ?? null;

        return is_array($specs) && self::hasAnyValue($specs);
    }

    private static function stripMakePrefix(string $value, string $makeLabel): string
    {
        $value = trim($value);
        $makeLabel = trim($makeLabel);
        if ($value === '' || $makeLabel === '') {
            return $value;
        }

        $pattern = '/^'.preg_quote($makeLabel, '/').'\s+/i';

        return trim((string) preg_replace($pattern, '', $value));
    }

    private static function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = (string) preg_replace('/[^a-z0-9]+/u', ' ', $value);

        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    private static function decodeJsonFile(string $path): mixed
    {
        $raw = (string) file_get_contents($path);
        $raw = preg_replace('/}\s*,\s*\/\/[^\r\n]*/m', '},', $raw) ?? $raw;
        $raw = preg_replace('/}\s*\/\/[^\r\n]*/m', '}', $raw) ?? $raw;

        return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    }
}
