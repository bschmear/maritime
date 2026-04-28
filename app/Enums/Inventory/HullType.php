<?php

declare(strict_types=1);

namespace App\Enums\Inventory;

/**
 * Slugs and order align with {@see base_path('app/Domain/BoatMake/Schema/hull_types.json')}.
 * {@see self::id()} is 1-based index in declaration order (keep in sync with JSON key order).
 */
enum HullType: string
{
    case Catamaran = 'catamaran';
    case DeepVee = 'deep-vee';
    case Displacement = 'displacement';
    case Flat = 'flat';
    case Foldable = 'foldable';
    case ModifiedVee = 'modified-vee';
    case Monohull = 'monohull';
    case Other = 'other';
    case Planing = 'planing';
    case Pontoon = 'pontoon';
    case Rib = 'rib';
    case RollUp = 'roll-up';
    case SeaV2 = 'sea-v2';
    case SemiDisplacement = 'semi-displacement';
    case Trimaran = 'trimaran';
    case Tunnel = 'tunnel';

    public function id(): int
    {
        static $slugToOrdinal = null;
        if ($slugToOrdinal === null) {
            $slugToOrdinal = [];
            foreach (self::cases() as $i => $case) {
                $slugToOrdinal[$case->value] = $i + 1;
            }
        }

        return $slugToOrdinal[$this->value] ?? 0;
    }

    public function label(): string
    {
        static $labels = null;
        if ($labels === null) {
            $path = base_path('app/Domain/BoatMake/Schema/hull_types.json');
            if (! is_readable($path)) {
                $labels = [];
            } else {
                $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
                $labels = is_array($decoded) ? $decoded : [];
            }
        }

        return $labels[$this->value] ?? $this->value;
    }

    public static function options(): array
    {
        return array_map(fn (self $case) => [
            'id' => $case->id(),
            'value' => $case->value,
            'name' => $case->label(),
        ], self::cases());
    }
}
