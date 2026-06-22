<?php

declare(strict_types=1);

namespace App\Domain\AssetUnit\Support;

use App\Enums\Inventory\BoatType;
use App\Enums\Inventory\HullMaterial;
use App\Enums\Inventory\HullType;
use App\Enums\Inventory\UnitCondition;
use App\Enums\Inventory\UnitStatus;

final class GoogleSheetEnumLabels
{
    /**
     * @return list<string>
     */
    public static function statusLabels(): array
    {
        return array_map(fn (array $o) => (string) $o['name'], UnitStatus::options());
    }

    /**
     * @return list<string>
     */
    public static function conditionLabels(): array
    {
        return array_map(fn (array $o) => (string) $o['name'], UnitCondition::options());
    }

    /**
     * @return list<string>
     */
    public static function hullTypeLabels(): array
    {
        return array_map(fn (array $o) => (string) $o['name'], HullType::options());
    }

    /**
     * @return list<string>
     */
    public static function hullMaterialLabels(): array
    {
        return array_map(fn (array $o) => (string) $o['name'], HullMaterial::options());
    }

    /**
     * @return list<string>
     */
    public static function boatTypeLabels(): array
    {
        return array_map(fn (array $o) => (string) $o['name'], BoatType::options());
    }

    public static function enumLabel(?int $id, string $enumClass): string
    {
        if ($id === null || ! class_exists($enumClass) || ! method_exists($enumClass, 'options')) {
            return '';
        }

        foreach ($enumClass::options() as $option) {
            if ((int) ($option['id'] ?? 0) === (int) $id) {
                return (string) ($option['name'] ?? '');
            }
        }

        return '';
    }

    public static function formatLengthMm(?int $mm): string
    {
        if ($mm === null || $mm <= 0) {
            return '';
        }

        $feet = round($mm / 304.8, 2);

        return $feet.' ft';
    }
}
