<?php

declare(strict_types=1);

namespace App\Domain\Bill\Actions;

use App\Domain\Bill\Models\Bill;
use App\Domain\BillPayment\Actions\CreateBillPayment;
use App\Domain\BillPayment\Models\BillPayment;
use App\Support\QuickBooks\QuickBooksPaymentAccountResolver;
use Illuminate\Validation\ValidationException;
use Throwable;

class PayBill
{
    /**
     * @return array{
     *     success: bool,
     *     record?: BillPayment,
     *     message?: string,
     *     quickbooks_sync?: array{success: bool, message?: string}|null
     * }
     */
    public function __invoke(Bill $bill): array
    {
        $bill->refresh();
        $bill->loadMissing('vendor');

        if ($bill->isPaid() || (float) $bill->balance <= 0.009) {
            throw ValidationException::withMessages([
                'bill' => ['This bill has no outstanding balance.'],
            ]);
        }

        if ($bill->vendor_id === null) {
            throw ValidationException::withMessages([
                'vendor_id' => ['A vendor is required before paying this bill.'],
            ]);
        }

        $amount = round((float) $bill->balance, 2);
        $bankAccount = QuickBooksPaymentAccountResolver::resolveBankAccount();

        $payload = [
            'vendor_id' => $bill->vendor_id,
            'quickbooks_vendor_id' => $bill->quickbooks_vendor_id ?: $bill->vendor?->quickbooks_id,
            'txn_date' => now()->format('Y-m-d'),
            'total_amt' => $amount,
            'pay_type' => 'Check',
            'currency_code' => $bill->currency_code ?: 'USD',
            'ap_account_ref_id' => $bill->ap_account_ref_id,
            'ap_account_ref_name' => $bill->ap_account_ref_name,
            'lines' => [
                [
                    'bill_id' => $bill->id,
                    'quickbooks_bill_id' => $bill->quickbooks_bill_id,
                    'amount' => $amount,
                    'position' => 0,
                ],
            ],
        ];

        if ($bankAccount !== null) {
            $payload['bank_account_ref_id'] = $bankAccount->quickbooks_account_id;
            $payload['bank_account_ref_name'] = $bankAccount->fully_qualified_name ?: $bankAccount->name;
        }

        try {
            $createResult = app(CreateBillPayment::class)($payload);

            if (! ($createResult['success'] ?? false) || ! ($createResult['record'] ?? null) instanceof BillPayment) {
                return [
                    'success' => false,
                    'message' => $createResult['message'] ?? 'Could not create bill payment.',
                ];
            }

            $payment = $createResult['record'];

            return [
                'success' => true,
                'record' => $payment->fresh(['lines.bill', 'vendor']),
                'quickbooks_sync' => $createResult['quickbooks_sync'] ?? null,
            ];
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
