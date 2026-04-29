<?php

declare(strict_types=1);

namespace App\Domain\AssetSpec\Support;

use App\Domain\Asset\Models\Asset;
use App\Domain\AssetSpec\Models\AssetSpecDefinition;
use App\Domain\AssetSpec\Models\AssetSpecValue;
use App\Domain\AssetVariant\Models\AssetVariant;

final class SpecValueDisplayFormatter
{
    /**
     * @return list<array{label: string, value: string|null}>
     */
    public static function labeledRowsFromAsset(Asset $asset): array
    {
        $asset->loadMissing(['specValues.definition', 'make']);

        $rows = [];

        foreach (['length' => 'Length', 'width' => 'Width', 'beam' => 'Beam'] as $field => $label) {
            $mm = $asset->{$field};
            if ($mm !== null && (int) $mm > 0) {
                $rows[] = ['label' => $label, 'value' => self::formatLengthMmToImperial((int) $mm)];
            }
        }

        $defs = AssetSpecDefinition::query()
            ->whereJsonContains('asset_types', (int) $asset->type)
            ->orderBy('position')
            ->get();

        foreach ($defs as $def) {
            $sv = $asset->specValues->firstWhere('asset_spec_definition_id', $def->id);
            $display = self::formatSpecValue($sv instanceof AssetSpecValue ? $sv : null, $def);
            if ($display !== null && $display !== '') {
                $rows[] = ['label' => $def->label, 'value' => $display];
            }
        }

        return $rows;
    }

    /**
     * @return list<array{label: string, value: string|null}>
     */
    public static function labeledRowsFromVariant(AssetVariant $variant): array
    {
        $variant->loadMissing(['asset.make', 'specValues.definition']);

        $rows = [];

        foreach (['length' => 'Length', 'width' => 'Width'] as $field => $label) {
            $mm = $variant->{$field};
            if ($mm !== null && (int) $mm > 0) {
                $rows[] = ['label' => $label, 'value' => self::formatLengthMmToImperial((int) $mm)];
            }
        }

        $asset = $variant->asset;
        $type = $asset ? (int) $asset->type : 0;

        $defs = AssetSpecDefinition::query()
            ->whereJsonContains('asset_types', $type)
            ->orderBy('position')
            ->get();

        foreach ($defs as $def) {
            $sv = $variant->specValues->firstWhere('asset_spec_definition_id', $def->id);
            $display = self::formatSpecValue($sv instanceof AssetSpecValue ? $sv : null, $def);
            if ($display !== null && $display !== '') {
                $rows[] = ['label' => $def->label, 'value' => $display];
            }
        }

        return $rows;
    }

    public static function formatLengthMmToImperial(?int $mm): ?string
    {
        if ($mm === null || $mm <= 0) {
            return null;
        }

        $totalInches = $mm / 25.4;
        $feet = (int) floor($totalInches / 12);
        $inches = $totalInches - ($feet * 12);
        if ($inches >= 11.95) {
            $feet++;
            $inches = 0;
        }

        $inStr = rtrim(rtrim(number_format($inches, 1), '0'), '.');

        return $feet."' ".$inStr.'"';
    }

    private static function formatSpecValue(?AssetSpecValue $sv, AssetSpecDefinition $def): ?string
    {
        if ($sv === null) {
            return null;
        }

        return match ($def->type) {
            'boolean' => $sv->value_boolean === null
                ? null
                : (((bool) $sv->value_boolean) ? 'Yes' : 'No'),
            'number' => self::formatNumber($sv, $def),
            'select' => self::formatSelect($sv, $def),
            'text' => ($sv->value_text !== null && $sv->value_text !== '') ? $sv->value_text : null,
            default => $sv->value_text ?? ($sv->value_number !== null ? (string) $sv->value_number : null),
        };
    }

    private static function formatNumber(AssetSpecValue $sv, AssetSpecDefinition $def): ?string
    {
        if ($sv->value_number === null) {
            return null;
        }

        $n = (float) $sv->value_number;
        $str = fmod($n, 1.0) === 0.0 ? (string) (int) $n : rtrim(rtrim(sprintf('%.6F', $n), '0'), '.');
        $unit = $sv->unit ?? $def->unit;

        return $unit !== null && $unit !== '' ? $str.' '.$unit : $str;
    }

    private static function formatSelect(AssetSpecValue $sv, AssetSpecDefinition $def): ?string
    {
        $raw = $sv->value_text;
        if ($raw === null || $raw === '') {
            return null;
        }

        $options = $def->options ?? [];
        foreach ($options as $opt) {
            if (! is_array($opt)) {
                continue;
            }
            if (array_key_exists('value', $opt) && (string) $opt['value'] === (string) $raw) {
                return isset($opt['label']) ? (string) $opt['label'] : (string) $raw;
            }
        }

        return (string) $raw;
    }
}
