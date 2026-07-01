<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Actions;

use App\Domain\Contact\Models\Contact;
use App\Domain\Shipment\Models\Shipment;
use App\Domain\Shipment\Support\ShipmentFromAddressResolver;
use App\Domain\Vendor\Models\Vendor;
use App\Enums\Shipment\Status;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CreateShipment
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __invoke(array $data, ?int $createdByUserId = null): Shipment
    {
        $validated = Validator::make($data, $this->rules())->validate();
        $recipient = $this->resolveRecipient($validated);

        return Shipment::query()->create([
            'contact_id' => $validated['recipient_type'] === 'contact' ? $validated['contact_id'] : null,
            'vendor_id' => $validated['recipient_type'] === 'vendor' ? $validated['vendor_id'] : null,
            'recipient_name' => $recipient['name'],
            'recipient_email' => $recipient['email'],
            'subsidiary_id' => $validated['subsidiary_id'],
            'location_id' => $validated['location_id'],
            'created_by_user_id' => $createdByUserId,
            'status' => Status::Draft,
            'from_address' => ShipmentFromAddressResolver::fromLocation(
                (int) $validated['location_id'],
                (int) $validated['subsidiary_id'],
            ),
            'to_address' => $validated['to_address'],
            'parcel' => $validated['parcel'],
            'notes' => $validated['notes'] ?? null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'recipient_type' => ['required', Rule::in(['contact', 'vendor'])],
            'contact_id' => ['required_if:recipient_type,contact', 'nullable', 'integer', 'exists:contacts,id'],
            'vendor_id' => ['required_if:recipient_type,vendor', 'nullable', 'integer', 'exists:vendors,id'],
            'subsidiary_id' => ['required', 'integer', 'exists:subsidiaries,id'],
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'to_address' => ['required', 'array'],
            'to_address.name' => ['nullable', 'string', 'max:255'],
            'to_address.company' => ['nullable', 'string', 'max:255'],
            'to_address.street1' => ['required', 'string', 'max:255'],
            'to_address.street2' => ['nullable', 'string', 'max:255'],
            'to_address.city' => ['required', 'string', 'max:255'],
            'to_address.state' => ['required', 'string', 'max:32'],
            'to_address.zip' => ['required', 'string', 'max:32'],
            'to_address.country' => ['nullable', 'string', 'max:2'],
            'to_address.phone' => ['nullable', 'string', 'max:32'],
            'to_address.email' => ['nullable', 'email', 'max:255'],
            'parcel' => ['required', 'array'],
            'parcel.length' => ['required', 'numeric', 'min:0.1'],
            'parcel.width' => ['required', 'numeric', 'min:0.1'],
            'parcel.height' => ['required', 'numeric', 'min:0.1'],
            'parcel.weight' => ['required', 'numeric', 'min:0.1'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{name: string|null, email: string|null}
     */
    private function resolveRecipient(array $validated): array
    {
        if ($validated['recipient_type'] === 'contact') {
            $contact = Contact::query()->findOrFail($validated['contact_id']);

            return [
                'name' => trim($contact->first_name.' '.$contact->last_name) ?: $contact->display_name,
                'email' => $contact->email ?: $contact->secondary_email,
            ];
        }

        $vendor = Vendor::query()->with(['linkedContacts', 'primaryContact'])->findOrFail($validated['vendor_id']);
        $contact = $vendor->primaryContact ?? $vendor->linkedContacts->first();

        return [
            'name' => $vendor->display_name,
            'email' => $contact?->email ?: $contact?->secondary_email,
        ];
    }
}
