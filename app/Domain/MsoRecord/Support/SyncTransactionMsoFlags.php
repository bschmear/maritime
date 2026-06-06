<?php

declare(strict_types=1);

namespace App\Domain\MsoRecord\Support;

use App\Domain\MsoRecord\Models\MsoRecord;
use App\Domain\Transaction\Models\Transaction;
use App\Domain\Transaction\Models\TransactionLineItem;
use App\Enums\MsoRecord\Status;

final class SyncTransactionMsoFlags
{
    public static function forTransaction(Transaction|int $transaction): void
    {
        $transactionId = $transaction instanceof Transaction ? (int) $transaction->getKey() : $transaction;

        $transaction = Transaction::query()->find($transactionId);
        if (! $transaction) {
            return;
        }

        $assetUnitLineIds = TransactionLineItem::query()
            ->where('parent_type', Transaction::class)
            ->where('parent_id', $transactionId)
            ->whereNotNull('asset_unit_id')
            ->pluck('id');

        $msoNeeded = $transaction->isCompleted() && $assetUnitLineIds->isNotEmpty();

        $msoCreated = false;
        if ($msoNeeded) {
            $resolvedCount = MsoRecord::query()
                ->whereIn('transaction_line_item_id', $assetUnitLineIds)
                ->whereIn('status', [Status::Submitted->value, Status::NotRequired->value])
                ->count();

            $msoCreated = $resolvedCount === $assetUnitLineIds->count();
        }

        if ((bool) $transaction->mso_needed !== $msoNeeded || (bool) $transaction->mso_created !== $msoCreated) {
            $transaction->forceFill([
                'mso_needed' => $msoNeeded,
                'mso_created' => $msoCreated,
            ])->saveQuietly();
        }
    }
}
