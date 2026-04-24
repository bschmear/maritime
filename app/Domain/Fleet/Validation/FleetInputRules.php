<?php

declare(strict_types=1);

namespace App\Domain\Fleet\Validation;

use App\Enums\Fleet\FleetStatus;
use App\Enums\Fleet\FleetType;
use Illuminate\Validation\Rule;

class FleetInputRules
{
    /**
     * @return array<string, mixed>
     */
    public static function create(): array
    {
        $currentYear = (int) date('Y');

        return [
            'display_name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(FleetType::class)],
            'license_plate' => ['nullable', 'string', 'max:255'],
            'size' => ['nullable', 'string', 'max:255'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'last_maintenance_at' => ['nullable', 'date'],
            'next_maintenance_due_at' => ['nullable', 'date', 'after_or_equal:last_maintenance_at'],
            'maintenance_interval_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'status' => ['required', Rule::enum(FleetStatus::class)],
            'vin' => ['nullable', 'string', 'max:64'],
            'make' => ['nullable', 'string', 'max:128'],
            'model' => ['nullable', 'string', 'max:128'],
            'year' => ['nullable', 'integer', 'min:1950', 'max:'.($currentYear + 1)],
            'mileage' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'hours' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'notes' => ['nullable', 'string', 'max:65000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function update(): array
    {
        $currentYear = (int) date('Y');

        return [
            'display_name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', Rule::enum(FleetType::class)],
            'license_plate' => ['sometimes', 'nullable', 'string', 'max:255'],
            'size' => ['sometimes', 'nullable', 'string', 'max:255'],
            'location_id' => ['sometimes', 'nullable', 'integer', 'exists:locations,id'],
            'last_maintenance_at' => ['sometimes', 'nullable', 'date'],
            'next_maintenance_due_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:last_maintenance_at'],
            'maintenance_interval_days' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:3650'],
            'status' => ['required', Rule::enum(FleetStatus::class)],
            'vin' => ['sometimes', 'nullable', 'string', 'max:64'],
            'make' => ['sometimes', 'nullable', 'string', 'max:128'],
            'model' => ['sometimes', 'nullable', 'string', 'max:128'],
            'year' => ['sometimes', 'nullable', 'integer', 'min:1950', 'max:'.($currentYear + 1)],
            'mileage' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:10000000'],
            'hours' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:1000000'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:65000'],
        ];
    }
}
