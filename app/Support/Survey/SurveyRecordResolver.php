<?php

namespace App\Support\Survey;

use App\Domain\Contact\Models\Contact;
use App\Domain\Customer\Models\Customer;
use App\Domain\Lead\Models\Lead;

class SurveyRecordResolver
{
    public function resolve(string $recordType, int $recordId): ?SurveyRecordTarget
    {
        return match ($recordType) {
            'contact' => $this->fromContact(Contact::query()->find($recordId)),
            'lead' => $this->fromLead(Lead::query()->with('contact')->find($recordId)),
            'customer' => $this->fromCustomer(Customer::query()->with('contact')->find($recordId)),
            default => null,
        };
    }

    protected function fromContact(?Contact $contact): ?SurveyRecordTarget
    {
        if (! $contact) {
            return null;
        }

        return new SurveyRecordTarget(
            recordType: 'contact',
            recordId: (int) $contact->id,
            contactId: (int) $contact->id,
            email: $this->trimEmail($contact->email),
            mobile: $this->trimPhone($contact->mobile ?? $contact->phone),
            assignedUserId: $contact->assigned_user_id ? (int) $contact->assigned_user_id : null,
            signedRecipientType: 'contact',
            signedRecipientId: (int) $contact->id,
            displayName: $this->displayName($contact->display_name, $contact->first_name, $contact->last_name),
        );
    }

    protected function fromLead(?Lead $lead): ?SurveyRecordTarget
    {
        if (! $lead) {
            return null;
        }

        return new SurveyRecordTarget(
            recordType: 'lead',
            recordId: (int) $lead->id,
            contactId: (int) $lead->contact_id,
            email: $this->trimEmail($lead->email),
            mobile: $this->trimPhone($lead->mobile ?? $lead->phone),
            assignedUserId: $lead->assigned_user_id ? (int) $lead->assigned_user_id : null,
            signedRecipientType: 'lead',
            signedRecipientId: (int) $lead->id,
            displayName: $this->displayName($lead->display_name, $lead->first_name, $lead->last_name),
        );
    }

    protected function fromCustomer(?Customer $customer): ?SurveyRecordTarget
    {
        if (! $customer || ! $customer->contact_id) {
            return null;
        }

        return new SurveyRecordTarget(
            recordType: 'customer',
            recordId: (int) $customer->id,
            contactId: (int) $customer->contact_id,
            email: $this->trimEmail($customer->email),
            mobile: $this->trimPhone($customer->mobile ?? $customer->phone),
            assignedUserId: $customer->assigned_user_id ? (int) $customer->assigned_user_id : null,
            signedRecipientType: 'contact',
            signedRecipientId: (int) $customer->contact_id,
            displayName: $this->displayName($customer->display_name, $customer->first_name, $customer->last_name),
        );
    }

    protected function trimEmail(?string $email): ?string
    {
        $email = trim((string) ($email ?? ''));

        return $email !== '' ? $email : null;
    }

    protected function trimPhone(?string $phone): ?string
    {
        $phone = trim((string) ($phone ?? ''));

        return $phone !== '' ? $phone : null;
    }

    protected function displayName(?string $displayName, ?string $first, ?string $last): ?string
    {
        $name = trim((string) ($displayName ?? ''));
        if ($name !== '') {
            return $name;
        }

        $name = trim(trim((string) ($first ?? '')).' '.trim((string) ($last ?? '')));

        return $name !== '' ? $name : null;
    }
}
