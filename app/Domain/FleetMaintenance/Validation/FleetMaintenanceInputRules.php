<?php

declare(strict_types=1);

namespace App\Domain\FleetMaintenance\Validation;

class FleetMaintenanceInputRules
{
    /**
     * @return array<string, mixed>
     */
    public static function create(): array
    {
        return [
            'fleet_id' => ['required', 'integer', 'exists:fleets,id'],
            'performed_at' => ['required', 'date'],
            'type_ids' => ['nullable', 'array'],
            'type_ids.*' => ['integer', 'exists:maintenance_types,id'],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'mileage' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'hours' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'notes' => ['nullable', 'string', 'max:65000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function update(): array
    {
        return [
            'performed_at' => ['sometimes', 'required', 'date'],
            'type_ids' => ['sometimes', 'array'],
            'type_ids.*' => ['integer', 'exists:maintenance_types,id'],
            'cost' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'mileage' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100000000'],
            'hours' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100000000'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:65000'],
        ];
    }
}
