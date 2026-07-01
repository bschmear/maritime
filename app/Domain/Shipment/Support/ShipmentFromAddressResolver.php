<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Support;

use App\Domain\Location\Models\Location;
use App\Domain\Subsidiary\Models\Subsidiary;
use Illuminate\Validation\ValidationException;

final class ShipmentFromAddressResolver
{
    public static function fromLocation(int $locationId, ?int $subsidiaryId = null): array
    {
        $location = Location::query()->find($locationId);
        if ($location === null) {
            throw ValidationException::withMessages([
                'location_id' => 'Selected location was not found.',
            ]);
        }

        if ($subsidiaryId !== null) {
            $belongs = $location->subsidiaries()
                ->where('subsidiaries.id', $subsidiaryId)
                ->exists();

            if (! $belongs) {
                throw ValidationException::withMessages([
                    'location_id' => 'Selected location does not belong to the chosen subsidiary.',
                ]);
            }
        }

        $street1 = trim((string) ($location->address_line_1 ?? ''));
        if ($street1 === '') {
            throw ValidationException::withMessages([
                'location_id' => 'The selected location does not have a complete street address.',
            ]);
        }

        $subsidiary = $subsidiaryId !== null
            ? Subsidiary::query()->find($subsidiaryId)
            : null;

        return array_filter([
            'name' => $location->display_name,
            'company' => $subsidiary?->display_name,
            'street1' => $street1,
            'street2' => filled($location->address_line_2) ? $location->address_line_2 : null,
            'city' => $location->city,
            'state' => $location->state,
            'zip' => $location->postal_code,
            'country' => filled($location->country) ? $location->country : 'US',
            'phone' => $location->phone,
            'email' => $location->email,
        ], fn ($value) => $value !== null && $value !== '');
    }
}
