<?php

declare(strict_types=1);

namespace App\Domain\ConsignmentAgreement\Support;

use App\Domain\AssetUnit\Models\AssetUnit;
use App\Domain\Contact\Models\Contact;
use App\Domain\Customer\Models\Customer;
use Illuminate\Validation\ValidationException;

class SyncAssetUnitForConsignmentMarking
{
    /**
     * Mark the unit as consignment, customer-owned, and assign the owner as the unit customer.
     *
     * @return array{unit: AssetUnit, owner_name: string}
     */
    public static function apply(AssetUnit $unit, ?int $ownerContactId = null): array
    {
        $unit->loadMissing('customer.contact');

        $contactId = $ownerContactId ?? $unit->customer?->contact_id;

        if ($contactId === null) {
            throw ValidationException::withMessages([
                'owner_contact_id' => [
                    'Assign an owner on the consignment agreement, or link a customer with a contact to this unit first.',
                ],
            ]);
        }

        $customer = Customer::query()->where('contact_id', $contactId)->first();
        if ($customer === null) {
            throw ValidationException::withMessages([
                'owner_contact_id' => [
                    'The owner must have a customer profile before this unit can be marked as consignment.',
                ],
            ]);
        }

        $ownerName = Contact::query()->whereKey($contactId)->value('display_name') ?? 'Owner';

        $unit->forceFill([
            'is_consignment' => true,
            'is_customer_owned' => true,
            'customer_id' => $customer->id,
        ])->save();

        return [
            'unit' => $unit->fresh() ?? $unit,
            'owner_name' => (string) $ownerName,
        ];
    }
}
