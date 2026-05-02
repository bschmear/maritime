<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Contact\Models\Contact;
use App\Domain\WarrantyClaim\Models\WarrantyClaim;
use App\Models\User;

class WarrantyClaimPolicy
{
    /**
     * Tenant staff may send warranty claims to manufacturer contacts.
     */
    public function sendToVendor(User $user, WarrantyClaim $claim): bool
    {
        return true;
    }

    /**
     * Manufacturer portal contact may view/respond only for their vendors' claims.
     */
    public function vendorRespond(Contact $contact, WarrantyClaim $claim): bool
    {
        if (! $claim->vendor_id) {
            return false;
        }

        return $contact->vendors()->where('vendors.id', (int) $claim->vendor_id)->exists();
    }
}
