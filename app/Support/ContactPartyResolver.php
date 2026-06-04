<?php

declare(strict_types=1);

namespace App\Support;

use App\Domain\Contact\Models\Contact;
use App\Domain\Customer\Models\Customer;
use App\Domain\Lead\Models\Lead;

/**
 * Resolve contact ↔ customer/lead profile rows for contact-first CRM flows.
 */
final class ContactPartyResolver
{
    public static function ensureCustomerProfile(Contact $contact): Customer
    {
        $existing = Customer::query()->where('contact_id', $contact->id)->first();
        if ($existing) {
            return $existing;
        }

        return Customer::create([
            'contact_id' => $contact->id,
            'account_status' => 'active',
        ]);
    }

    public static function ensureLeadProfile(int $contactId): void
    {
        Lead::firstOrCreate(
            ['contact_id' => $contactId],
            []
        );
    }

    /**
     * @return list<string> e.g. ["Contact", "Lead", "Customer"]
     */
    public static function partyLabelsForContact(Contact $contact): array
    {
        $labels = ['Contact'];

        $hasLead = $contact->relationLoaded('leads')
            ? $contact->leads->isNotEmpty()
            : $contact->leads()->exists();

        $hasCustomer = $contact->relationLoaded('customer')
            ? $contact->customer !== null
            : $contact->customer()->exists();

        if ($hasLead) {
            $labels[] = 'Lead';
        }

        if ($hasCustomer) {
            $labels[] = 'Customer';
        }

        return $labels;
    }
}
