<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Contact\Models\Contact;
use App\Domain\WarrantyClaim\Models\WarrantyClaim;
use App\Enums\WarrantyClaim\Status;
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
     * Tenant staff may submit a draft claim (mark submitted and notify contacts).
     */
    public function submit(User $user, WarrantyClaim $claim): bool
    {
        return true;
    }

    /**
     * Manufacturer portal contact may view/respond only for their vendors' claims.
     * Draft claims are internal to the dealership and are not visible in the vendor portal.
     */
    public function vendorRespond(Contact $contact, WarrantyClaim $claim): bool
    {
        if (! $claim->vendor_id) {
            return false;
        }

        $status = $claim->status instanceof Status
            ? $claim->status
            : Status::tryFrom((string) $claim->getRawOriginal('status')) ?? Status::Draft;

        if ($status === Status::Draft) {
            return false;
        }

        return $contact->vendorsWithPortalAccess()
            ->where('vendors.id', (int) $claim->vendor_id)
            ->exists();
    }
}
