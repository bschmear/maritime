<?php

declare(strict_types=1);

namespace App\Domain\MsoRecord\Support;

use App\Domain\AssetUnit\Models\AssetUnit;
use App\Domain\Transaction\Models\Transaction;
use App\Domain\Transaction\Models\TransactionLineItem;

final class MsoRecordSnapshot
{
    /**
     * @return array<string, mixed>
     */
    public static function build(Transaction $transaction, TransactionLineItem $lineItem, AssetUnit $assetUnit): array
    {
        $transaction->loadMissing(['customer']);

        return [
            'transaction' => [
                'id' => $transaction->id,
                'display_name' => $transaction->display_name,
                'customer_name' => $transaction->customer_name,
                'customer_email' => $transaction->customer_email,
                'customer_phone' => $transaction->customer_phone,
                'closed_at' => $transaction->closed_at?->toIso8601String(),
            ],
            'line_item' => [
                'id' => $lineItem->id,
                'name' => $lineItem->name,
                'description' => $lineItem->description,
                'quantity' => $lineItem->quantity,
                'unit_price' => $lineItem->unit_price,
            ],
            'asset_unit' => [
                'id' => $assetUnit->id,
                'display_name' => $assetUnit->display_name,
                'serial_number' => $assetUnit->serial_number,
                'hin' => $assetUnit->hin,
            ],
        ];
    }
}
