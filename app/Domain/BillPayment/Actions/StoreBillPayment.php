<?php

declare(strict_types=1);

namespace App\Domain\BillPayment\Actions;

use App\Domain\Bill\Models\Bill;
use App\Domain\BillPayment\Models\BillPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StoreBillPayment
{
    /**
     * Apply a bill payment to linked bills (update balances/status).
     *
     * @param  array{
     *     bill_payment_id?: int,
     *     lines: list<array{bill_id: int, amount: numeric-string|float|int}>,
     *     apply_to_bills?: bool|int|string|null
     * }  $validated
     */
    public function __invoke(array $validated): void
    {
        $apply = filter_var($validated['apply_to_bills'] ?? true, FILTER_VALIDATE_BOOLEAN);
        if (! $apply) {
            return;
        }

        $lines = $validated['lines'] ?? [];
        if (! is_array($lines) || $lines === []) {
            throw ValidationException::withMessages([
                'lines' => ['At least one bill line is required.'],
            ]);
        }

        DB::transaction(function () use ($lines): void {
            foreach ($lines as $line) {
                if (! is_array($line)) {
                    continue;
                }
                $billId = (int) ($line['bill_id'] ?? 0);
                $amount = round((float) ($line['amount'] ?? 0), 2);
                if ($billId <= 0 || $amount <= 0) {
                    continue;
                }

                $bill = Bill::query()->lockForUpdate()->find($billId);
                if ($bill === null || $bill->status === 'void' || $bill->status === 'paid') {
                    continue;
                }

                if ($amount > (float) $bill->balance + 0.01) {
                    throw ValidationException::withMessages([
                        'lines' => ["Payment amount exceeds balance for bill {$bill->display_name}."],
                    ]);
                }

                $bill->applyPayment($amount);
            }
        });
    }

    public function applyFromPayment(BillPayment $payment): void
    {
        $payment->loadMissing('lines.bill');
        $lines = $payment->lines->map(fn ($line) => [
            'bill_id' => $line->bill_id,
            'amount' => $line->amount,
        ])->filter(fn ($row) => $row['bill_id'] !== null)->values()->all();

        if ($lines === []) {
            return;
        }

        $this([
            'lines' => $lines,
            'apply_to_bills' => true,
        ]);
    }
}
