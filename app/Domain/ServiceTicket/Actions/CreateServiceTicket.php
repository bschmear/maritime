<?php

namespace App\Domain\ServiceTicket\Actions;

use App\Domain\Contact\Models\Contact;
use App\Domain\Customer\Models\Customer;
use App\Domain\ServiceTicket\Models\ServiceTicket as RecordModel;
use App\Support\ContactPartyResolver;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

class CreateServiceTicket
{
    public function __invoke(array $data): array
    {
        Validator::make($data, [
            'contact_id' => 'nullable|integer|exists:contacts,id',
            'customer_id' => 'nullable|integer|exists:customer_profiles,id',
            'subsidiary_id' => 'required|exists:subsidiaries,id',
            'location_id' => 'required|exists:locations,id',
            'transaction_id' => 'nullable|integer|exists:transactions,id',
        ])->validate();

        if (! empty($data['contact_id']) && empty($data['customer_id'])) {
            $contact = Contact::query()->findOrFail((int) $data['contact_id']);
            $data['customer_id'] = ContactPartyResolver::ensureCustomerProfile($contact)->id;
        }

        Validator::make($data, [
            'customer_id' => 'required|exists:customer_profiles,id',
        ])->validate();

        $contactId = ! empty($data['contact_id'])
            ? (int) $data['contact_id']
            : (int) (Customer::query()->whereKey($data['customer_id'])->value('contact_id') ?? 0);

        if ($contactId > 0) {
            ContactPartyResolver::ensureLeadProfile($contactId);
        }

        unset($data['contact_id']);

        // Generate UUID if not provided
        if (empty($data['uuid'])) {
            $data['uuid'] = (string) Str::uuid();
        }

        try {
            $record = RecordModel::create($data);

            return [
                'success' => true,
                'record' => $record,
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in CreateServiceTicket', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in CreateServiceTicket', [
                'error' => $e->getMessage(),
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
