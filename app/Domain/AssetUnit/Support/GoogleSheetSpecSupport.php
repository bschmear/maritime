<?php

declare(strict_types=1);

namespace App\Domain\AssetUnit\Support;

use App\Domain\Asset\Models\Asset;
use App\Domain\AssetSpec\Models\AssetSpecDefinition;
use App\Domain\AssetSpec\Models\AssetSpecValue;
use App\Domain\AssetVariant\Models\AssetVariant;
use Illuminate\Database\Eloquent\Model;

final class GoogleSheetSpecSupport
{
    public const SPEC_PREFIX = 'Spec: ';

    /**
     * @return list<AssetSpecDefinition>
     */
    public function specDefinitions(): array
    {
        return AssetSpecDefinition::query()
            ->where('is_visible', true)
            ->orderBy('position')
            ->orderBy('label')
            ->get()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function specHeaders(): array
    {
        return array_map(
            fn (AssetSpecDefinition $def) => self::SPEC_PREFIX.$def->label,
            $this->specDefinitions(),
        );
    }

    /**
     * @param  list<AssetSpecDefinition>  $specDefinitions
     * @param  list<int>  $specIds
     * @param  list<array{0: string, 1: int}>  $specables
     * @return array<string, array<int, AssetSpecValue>>
     */
    public function loadSpecValues(array $specDefinitions, array $specIds, array $specables): array
    {
        if ($specIds === [] || $specables === []) {
            return [];
        }

        $values = AssetSpecValue::query()
            ->whereIn('asset_spec_definition_id', $specIds)
            ->where(function ($q) use ($specables) {
                foreach ($specables as [$type, $id]) {
                    $q->orWhere(function ($inner) use ($type, $id) {
                        $inner->where('specable_type', $type)->where('specable_id', $id);
                    });
                }
            })
            ->get();

        $grouped = [];
        foreach ($values as $value) {
            $key = $value->specable_type.':'.$value->specable_id;
            $grouped[$key][$value->asset_spec_definition_id] = $value;
        }

        return $grouped;
    }

    public function specableKey(Asset|AssetVariant $specable): string
    {
        return $specable->getMorphClass().':'.$specable->getKey();
    }

    /**
     * @param  list<AssetSpecDefinition>  $specDefinitions
     * @param  array<int, AssetSpecValue>  $specValues
     * @return list<mixed>
     */
    public function specCells(array $specDefinitions, array $specValues): array
    {
        $cells = [];
        foreach ($specDefinitions as $definition) {
            $cells[] = $this->formatSpecValue($specValues[$definition->id] ?? null, $definition);
        }

        return $cells;
    }

    public function formatSpecValue(?AssetSpecValue $value, AssetSpecDefinition $definition): string
    {
        if ($value === null) {
            return '';
        }

        return match ($definition->type) {
            'boolean' => $value->value_boolean ? 'Yes' : 'No',
            'number' => $value->value_number !== null ? (string) $value->value_number : '',
            default => (string) ($value->value_text ?? ''),
        };
    }

  /**
     * @return array{spec_id: int, value_number?: mixed, value_text?: mixed, value_boolean?: bool}
     */
    public function specPayload(AssetSpecDefinition $definition, string $raw): array
    {
        $entry = ['spec_id' => $definition->id];

        if ($definition->type === 'boolean') {
            $entry['value_boolean'] = in_array(strtolower(trim($raw)), ['1', 'true', 'yes', 'y'], true);
        } elseif ($definition->type === 'number') {
            $clean = str_replace([',', '$'], '', trim($raw));
            $entry['value_number'] = is_numeric($clean) ? (float) $clean : null;
        } else {
            $entry['value_text'] = $raw;
        }

        return $entry;
    }

    public function resolveSpecable(Asset $asset, ?AssetVariant $variant): Asset|AssetVariant
    {
        return $variant ?? $asset;
    }
}
