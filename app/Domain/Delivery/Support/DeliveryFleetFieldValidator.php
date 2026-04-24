<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Support;

use App\Domain\Fleet\Models\Fleet;
use App\Enums\Fleet\FleetType;
use Illuminate\Validation\Validator;

final class DeliveryFleetFieldValidator
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function validateFleetRows(Validator $validator, array $data): void
    {
        $truckId = self::nullablePositiveInt($data['fleet_truck_id'] ?? null);
        $trailerId = self::nullablePositiveInt($data['fleet_trailer_id'] ?? null);
        $locationId = self::nullablePositiveInt($data['location_id'] ?? null);

        if ($truckId !== null && $trailerId !== null && $truckId === $trailerId) {
            $validator->errors()->add('fleet_trailer_id', 'Trailer must be a different unit than the truck.');
        }

        foreach (
            [
                'fleet_truck_id' => [$truckId, FleetType::Truck],
                'fleet_trailer_id' => [$trailerId, FleetType::Trailer],
            ] as $field => [$id, $expected]
        ) {
            if ($id === null) {
                continue;
            }
            $fleet = Fleet::query()->find($id);
            if (! $fleet) {
                $validator->errors()->add($field, 'The selected fleet unit was not found.');

                continue;
            }
            if ($fleet->type !== $expected) {
                $validator->errors()->add($field, $expected === FleetType::Truck
                    ? 'Selected fleet must be a truck.'
                    : 'Selected fleet must be a trailer.');
            }
            if ($locationId !== null && $fleet->location_id !== null && (int) $fleet->location_id !== $locationId) {
                $validator->errors()->add($field, 'Fleet unit must belong to the same depart-from location as this delivery.');
            }
        }
    }

    private static function nullablePositiveInt(mixed $v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (! is_numeric($v)) {
            return null;
        }
        $i = (int) $v;

        return $i > 0 ? $i : null;
    }
}
