<?php

namespace App\Domain\Delivery\Actions;

use App\Domain\Contact\Models\ContactAddress;
use App\Domain\DeliveryLocation\Models\DeliveryLocation;

/**
 * Shared helper for CreateDelivery / UpdateDelivery: when delivery_to_type points at a
 * known source (a contact address or a common delivery location), copy its address
 * fields into the delivery's snapshot columns. Any value the caller explicitly
 * provided in $data wins over the sourced value.
 */
class DeliveryAddressFiller
{
    public static function fill(array &$data): void
    {
        $type = $data['delivery_to_type'] ?? null;
        $address = null;

        if ($type === 'delivery_location' && ! empty($data['delivery_location_id'])) {
            $address = DeliveryLocation::find($data['delivery_location_id']);
        } elseif ($type === 'contact_address' && ! empty($data['contact_address_id'])) {
            $address = ContactAddress::find($data['contact_address_id']);
        }

        if (! $address) {
            return;
        }

        $fields = [
            'address_line_1',
            'address_line_2',
            'city',
            'state',
            'postal_code',
            'country',
            'latitude',
            'longitude',
        ];

        foreach ($fields as $field) {
            if (empty($data[$field]) && ! empty($address->$field)) {
                $data[$field] = $address->$field;
            }
        }
    }
}
