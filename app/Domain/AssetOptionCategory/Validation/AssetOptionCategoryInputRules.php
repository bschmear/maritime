<?php

declare(strict_types=1);

namespace App\Domain\AssetOptionCategory\Validation;

class AssetOptionCategoryInputRules
{
    /**
     * @return array<string, mixed>
     */
    public static function create(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function update(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
