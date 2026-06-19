<?php

declare(strict_types=1);

namespace App\Support\QuickBooks;

use App\Domain\Contact\Models\Contact;
use App\Domain\Vendor\Models\Vendor;

final class QuickBooksVendorContactLinker
{
    /**
     * Find an existing Maritime contact that likely corresponds to this QuickBooks vendor row.
     *
     * @param  array<string, mixed>  $vendorPayload  Output from {@see QuickBooksVendorMapper::mapVendorRow()}
     */
    public static function resolveContact(array $vendorPayload): ?Contact
    {
        $email = $vendorPayload['contact_email'] ?? null;
        if (is_string($email) && $email !== '') {
            $byEmail = Contact::findByEmailCaseInsensitive($email);
            if ($byEmail !== null) {
                return $byEmail;
            }
        }

        return self::resolveImportedContactByName($vendorPayload);
    }

    /**
     * Attach a resolved contact to the vendor (primary when unset, otherwise pivot only).
     */
    public static function link(Vendor $vendor, Contact $contact): void
    {
        $vendor->refresh();

        if ($vendor->primary_contact_id === null) {
            $vendor->primary_contact_id = $contact->id;
            $vendor->save();
            $vendor->syncPrimaryContactPivot();

            return;
        }

        if ((int) $vendor->primary_contact_id === (int) $contact->id) {
            $vendor->syncPrimaryContactPivot();

            return;
        }

        $alreadyLinked = $vendor->linkedContacts()
            ->where('contacts.id', $contact->id)
            ->exists();

        if (! $alreadyLinked) {
            $vendor->linkedContacts()->attach($contact->id, [
                'is_primary' => false,
                'portal_access' => false,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $vendorPayload
     */
    private static function resolveImportedContactByName(array $vendorPayload): ?Contact
    {
        $names = array_values(array_unique(array_filter([
            trim((string) ($vendorPayload['display_name'] ?? '')),
            trim((string) ($vendorPayload['company_name'] ?? '')),
            trim((string) ($vendorPayload['print_on_check_name'] ?? '')),
        ])));

        if ($names === []) {
            return null;
        }

        $query = Contact::query()->whereNotNull('quickbooks_customer_id');

        $query->where(function ($q) use ($names): void {
            foreach ($names as $name) {
                $normalized = mb_strtolower($name);
                $q->orWhereRaw('LOWER(TRIM(display_name)) = ?', [$normalized])
                    ->orWhereRaw('LOWER(TRIM(company)) = ?', [$normalized]);
            }
        });

        return $query->orderBy('id')->first();
    }
}
