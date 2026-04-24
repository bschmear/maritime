<?php

declare(strict_types=1);

namespace App\Domain\MaintenanceType\Validation;

use App\Enums\Fleet\MaintenanceTypeAppliesTo;
use Illuminate\Validation\Rule;

class MaintenanceTypeInputRules
{
    /**
     * @return array<string, mixed>
     */
    public static function create(): array
    {
        return [
            'display_name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'applies_to' => ['required', Rule::enum(MaintenanceTypeAppliesTo::class)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function update(): array
    {
        return [
            'display_name' => ['sometimes', 'required', 'string', 'max:255'],
            'category' => ['sometimes', 'nullable', 'string', 'max:255'],
            'applies_to' => ['sometimes', 'required', Rule::enum(MaintenanceTypeAppliesTo::class)],
            'sort_order' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100000'],
        ];
    }
}
