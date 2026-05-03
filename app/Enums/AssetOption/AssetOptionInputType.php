<?php

declare(strict_types=1);

namespace App\Enums\AssetOption;

enum AssetOptionInputType: string
{
    case Select = 'select';
    case Color = 'color';
    case MultiSelect = 'multi_select';
    case Toggle = 'toggle';

    /**
     * @return array<int, array{id: string, name: string}>
     */
    public static function options(): array
    {
        return [
            ['id' => self::Select->value, 'name' => 'Single select'],
            ['id' => self::Color->value, 'name' => 'Color'],
            ['id' => self::MultiSelect->value, 'name' => 'Multi select'],
            ['id' => self::Toggle->value, 'name' => 'Toggle'],
        ];
    }
}
