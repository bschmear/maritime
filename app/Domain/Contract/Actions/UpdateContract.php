<?php

namespace App\Domain\Contract\Actions;

use App\Domain\Contract\Models\Contract as RecordModel;
use App\Support\ContractEnumMapper;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class UpdateContract
{
    public function __invoke(int $id, array $data): array
    {
        $validated = Validator::make($data, [
            'contact_id' => ['sometimes', 'required', 'integer', 'exists:contacts,id'],
            'estimate_id' => ['nullable', 'integer', 'exists:estimates,id'],
            'transaction_id' => ['nullable', 'integer', 'exists:transactions,id'],
            'total_amount' => ['sometimes', 'required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'status' => ['nullable'],
            'payment_status' => ['nullable'],
            'payment_terms' => ['nullable', 'string'],
            'delivery_terms' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'billing_address_line1' => ['nullable', 'string', 'max:255'],
            'billing_address_line2' => ['nullable', 'string', 'max:255'],
            'billing_city' => ['nullable', 'string', 'max:255'],
            'billing_state' => ['nullable', 'string', 'max:255'],
            'billing_postal' => ['nullable', 'string', 'max:255'],
            'billing_country' => ['nullable', 'string', 'max:255'],
            'billing_latitude' => ['nullable', 'numeric'],
            'billing_longitude' => ['nullable', 'numeric'],
            'signature_required' => ['nullable', 'boolean'],
            'paper_signature_document_id' => ['nullable', 'integer', 'exists:documents,id'],
        ])->validate();

        $payload = $validated;
        unset($payload['account_settings_id'], $payload['created_at'], $payload['updated_at']);

        if (array_key_exists('status', $payload)) {
            $payload['status'] = ContractEnumMapper::statusToValue($payload['status']);
        }
        if (array_key_exists('payment_status', $payload)) {
            $payload['payment_status'] = ContractEnumMapper::paymentStatusToValue($payload['payment_status']);
        }
        if (array_key_exists('signature_required', $payload)) {
            $payload['signature_required'] = filter_var($payload['signature_required'], FILTER_VALIDATE_BOOLEAN);
        }

        try {
            $record = RecordModel::findOrFail($id);
            $record->update($payload);

            return [
                'success' => true,
                'record' => $record->fresh(),
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in UpdateContract', [
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
            Log::error('Unexpected error in UpdateContract', [
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
