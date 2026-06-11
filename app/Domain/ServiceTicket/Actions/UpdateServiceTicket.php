<?php

namespace App\Domain\ServiceTicket\Actions;

use App\Domain\Contact\Models\Contact;
use App\Domain\Customer\Models\Customer;
use App\Domain\ServiceTicket\Models\ServiceTicket as RecordModel;
use App\Support\ContactPartyResolver;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class UpdateServiceTicket
{
    public function __invoke(int $id, array $data): array
    {
        Validator::make($data, [
            'contact_id' => 'sometimes|nullable|integer|exists:contacts,id',
            'customer_id' => 'sometimes|nullable|exists:customer_profiles,id',
            'subsidiary_id' => 'sometimes|exists:subsidiaries,id',
            'location_id' => 'sometimes|exists:locations,id',
            'transaction_id' => 'nullable|integer|exists:transactions,id',
        ])->validate();

        if (array_key_exists('contact_id', $data) && ! empty($data['contact_id'])) {
            $contact = Contact::query()->findOrFail((int) $data['contact_id']);
            $data['customer_id'] = ContactPartyResolver::ensureCustomerProfile($contact)->id;
            ContactPartyResolver::ensureLeadProfile((int) $contact->id);
        } elseif (array_key_exists('customer_id', $data) && ! empty($data['customer_id'])) {
            $customer = Customer::query()->find((int) $data['customer_id']);
            if ($customer?->contact_id) {
                ContactPartyResolver::ensureLeadProfile((int) $customer->contact_id);
            }
        }

        unset($data['contact_id']);

        try {
            $record = RecordModel::findOrFail($id);
            $record->update($data);

            return [
                'success' => true,
                'record' => $record,
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in UpdateServiceTicket', [
                'error' => $e->getMessage(),
                'id' => $id,
                'data' => $data,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in UpdateServiceTicket', [
                'error' => $e->getMessage(),
                'id' => $id,
                'data' => $data,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        }
    }
}
