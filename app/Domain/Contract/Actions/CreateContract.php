<?php

namespace App\Domain\Contract\Actions;

use App\Domain\Contract\Models\Contract as RecordModel;
use App\Models\AccountSettings;
use App\Support\ContractEnumMapper;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class CreateContract
{
    public function __invoke(array $data): array
    {
        $settings = AccountSettings::getCurrent();

        $validated = Validator::make($data, [
            'contact_id' => ['required', 'integer', 'exists:contacts,id'],
            'estimate_id' => ['nullable', 'integer', 'exists:estimates,id'],
            'transaction_id' => ['nullable', 'integer', 'exists:transactions,id'],
            'total_amount' => ['required', 'numeric', 'min:0'],
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

        $payload['account_settings_id'] = $settings->id;
        $payload['status'] = ContractEnumMapper::statusToValue($payload['status'] ?? null);
        $payload['payment_status'] = ContractEnumMapper::paymentStatusToValue($payload['payment_status'] ?? null);
        $payload['currency'] = $payload['currency'] ?? 'USD';

        if (isset($payload['signature_required'])) {
            $payload['signature_required'] = filter_var($payload['signature_required'], FILTER_VALIDATE_BOOLEAN);
        } else {
            $payload['signature_required'] = true;
        }

        try {
            $record = RecordModel::create($payload);

            if (empty($record->contract_number)) {
                $record->update([
                    'contract_number' => 'CT-'.str_pad((string) $record->id, 5, '0', STR_PAD_LEFT),
                ]);
                $record->refresh();
            }

            return [
                'success' => true,
                'record' => $record,
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in CreateContract', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in CreateContract', [
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
