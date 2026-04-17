<?php

namespace App\Domain\Contract\Actions;

use App\Domain\Contract\Models\Contract as RecordModel;
use App\Domain\Transaction\Models\Transaction;
use App\Enums\Payments\Terms;
use App\Models\AccountSettings;
use App\Support\ContractEnumMapper;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

class CreateContract
{
    /** Mirrors account_settings / migration defaults when DB values are null. */
    private const FALLBACK_CONTRACT_TERMS = 'This agreement outlines the terms and conditions of the sale, including product details, payment obligations, and delivery expectations.';

    private const FALLBACK_PAYMENT_TERMS = 'Payment is due as specified in the contract. Please remit promptly.';

    private const FALLBACK_DELIVERY_TERMS = 'Delivery will be scheduled according to contract terms. Customer will be notified in advance.';

    public function __invoke(array $data): array
    {
        $settings = AccountSettings::getCurrent();

        $validated = Validator::make($data, [
            'customer_id' => ['required', 'integer', 'exists:customer_profiles,id'],
            'estimate_id' => ['nullable', 'integer', 'exists:estimates,id'],
            'transaction_id' => ['nullable', 'integer', 'exists:transactions,id'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'status' => ['nullable'],
            'payment_status' => ['nullable'],
            'payment_terms' => ['nullable', 'string'],
            'delivery_terms' => ['nullable', 'string'],
            'contract_terms' => ['nullable', 'string'],
            'payment_term' => ['nullable', Rule::enum(Terms::class)],
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

        // Long text defaults: request → account settings → hardcoded fallbacks (never rely on null DB alone)
        $payload['payment_terms'] = $this->resolveTermsText(
            $payload['payment_terms'] ?? null,
            $settings->default_payment_terms,
            self::FALLBACK_PAYMENT_TERMS,
        );
        $payload['delivery_terms'] = $this->resolveTermsText(
            $payload['delivery_terms'] ?? null,
            $settings->default_delivery_terms,
            self::FALLBACK_DELIVERY_TERMS,
        );
        $payload['contract_terms'] = $this->resolveTermsText(
            $payload['contract_terms'] ?? null,
            $settings->default_contract_terms,
            self::FALLBACK_CONTRACT_TERMS,
        );

        if (($payload['payment_term'] ?? null) === null || $payload['payment_term'] === '') {
            $def = $settings->default_payment_term;
            $payload['payment_term'] = $def instanceof Terms
                ? $def->value
                : (is_string($def) && $def !== '' ? $def : Terms::DueOnReceipt->value);
        } elseif ($payload['payment_term'] instanceof Terms) {
            $payload['payment_term'] = $payload['payment_term']->value;
        }

        // Billing snapshot from linked deal when contract payload has no address (e.g. transaction modal create)
        if (! empty($payload['transaction_id'])) {
            $tx = Transaction::query()->find((int) $payload['transaction_id']);
            if ($tx !== null) {
                foreach ([
                    'billing_address_line1', 'billing_address_line2', 'billing_city',
                    'billing_state', 'billing_postal', 'billing_country',
                ] as $field) {
                    if ($this->isBlankString($payload[$field] ?? null) && is_string($tx->{$field}) && trim($tx->{$field}) !== '') {
                        $payload[$field] = $tx->{$field};
                    }
                }
                foreach (['billing_latitude', 'billing_longitude'] as $field) {
                    if (($payload[$field] ?? null) === null && $tx->{$field} !== null) {
                        $payload[$field] = $tx->{$field};
                    }
                }
            }
        }

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
                    'contract_number' => 'CT-'.str_pad((string) $record->id, 4, '0', STR_PAD_LEFT),
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

    private function isBlankString(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        return is_string($value) && trim($value) === '';
    }

    private function resolveTermsText(mixed $incoming, mixed $fromAccount, string $fallback): string
    {
        if (is_string($incoming) && trim($incoming) !== '') {
            return $incoming;
        }

        if (is_string($fromAccount) && trim($fromAccount) !== '') {
            return $fromAccount;
        }

        return $fallback;
    }
}
