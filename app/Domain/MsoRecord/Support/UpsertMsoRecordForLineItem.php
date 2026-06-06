<?php

declare(strict_types=1);

namespace App\Domain\MsoRecord\Support;

use App\Domain\AssetUnit\Models\AssetUnit;
use App\Domain\MsoRecord\Models\MsoRecord;
use App\Domain\Transaction\Models\Transaction;
use App\Domain\Transaction\Models\TransactionLineItem;
use App\Enums\MsoRecord\Status;

final class UpsertMsoRecordForLineItem
{
    public static function handle(
        Transaction $transaction,
        TransactionLineItem $lineItem,
        Status $status,
        ?int $createdById = null,
    ): MsoRecord {
        $assetUnit = AssetUnit::query()->findOrFail((int) $lineItem->asset_unit_id);
        $sourceDocument = $assetUnit->msoSourceDocument();

        $existing = MsoRecord::query()
            ->where('transaction_line_item_id', $lineItem->id)
            ->first();

        $payload = [
            'asset_unit_id' => $assetUnit->id,
            'transaction_id' => $transaction->id,
            'transaction_line_item_id' => $lineItem->id,
            'source_document_id' => $sourceDocument?->id,
            'status' => $status->value,
            'details' => MsoRecordSnapshot::build($transaction, $lineItem, $assetUnit),
            'created_by_id' => $createdById ?? current_tenant_user_id(),
        ];

        if ($status->isResolved()) {
            $payload['submitted_at'] = now();
        }

        if ($existing) {
            $existing->fill($payload)->save();

            return $existing->fresh();
        }

        return MsoRecord::query()->create($payload);
    }

    public static function ensureDraft(
        Transaction $transaction,
        TransactionLineItem $lineItem,
        ?int $createdById = null,
    ): MsoRecord {
        $existing = MsoRecord::query()
            ->where('transaction_line_item_id', $lineItem->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        return self::handle($transaction, $lineItem, Status::Draft, $createdById);
    }
}
