<?php

declare(strict_types=1);

namespace App\Domain\Invoice\Support;

use App\Domain\Transaction\Models\Transaction;
use App\Enums\Contract\ContractStatus;
use Illuminate\Validation\ValidationException;

final class AssertTransactionAllowsInvoiceCreation
{
    public static function validate(?int $transactionId): void
    {
        if ($transactionId === null || $transactionId <= 0) {
            return;
        }

        $transaction = Transaction::query()
            ->with('contract:id,transaction_id,status')
            ->find($transactionId);

        if ($transaction === null || ! $transaction->needs_contract) {
            return;
        }

        $contract = $transaction->contract;
        if ($contract === null) {
            throw ValidationException::withMessages([
                'transaction_id' => 'Needs Contract is enabled on this deal. Create and sign a contract before creating an invoice, or turn off Needs Contract on the deal.',
            ]);
        }

        if ((string) $contract->status !== ContractStatus::Signed->value) {
            throw ValidationException::withMessages([
                'transaction_id' => 'Needs Contract is enabled on this deal. The contract must be signed before you can create an invoice, or turn off Needs Contract on the deal.',
            ]);
        }
    }
}
