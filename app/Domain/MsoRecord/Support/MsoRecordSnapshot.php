<?php

declare(strict_types=1);

namespace App\Domain\MsoRecord\Support;

use App\Domain\AssetUnit\Models\AssetUnit;
use App\Domain\Location\Models\Location;
use App\Domain\Transaction\Models\Transaction;
use App\Domain\Transaction\Models\TransactionLineItem;
use App\Domain\User\Models\User;

final class MsoRecordSnapshot
{
    /**
     * @return array<string, mixed>
     */
    public static function build(
        Transaction $transaction,
        TransactionLineItem $lineItem,
        AssetUnit $assetUnit,
        ?User $assignedUser = null,
    ): array {
        $transaction->loadMissing(['customer.contact', 'subsidiary', 'location']);

        $customer = $transaction->customer;
        $addressParts = array_filter([
            $transaction->billing_address_line1,
            $transaction->billing_address_line2,
            trim(implode(', ', array_filter([
                $transaction->billing_city,
                $transaction->billing_state,
                $transaction->billing_postal,
            ]))),
            $transaction->billing_country,
        ]);

        return [
            'transaction' => [
                'id' => $transaction->id,
                'display_name' => $transaction->display_name,
                'customer_name' => $transaction->customer_name,
                'customer_email' => $transaction->customer_email,
                'customer_phone' => $transaction->customer_phone,
                'customer_title' => $customer?->title ?? $customer?->contact?->title,
                'customer_address' => implode("\n", $addressParts),
                'billing_address_line1' => $transaction->billing_address_line1,
                'billing_address_line2' => $transaction->billing_address_line2,
                'billing_city' => $transaction->billing_city,
                'billing_state' => $transaction->billing_state,
                'billing_postal' => $transaction->billing_postal,
                'billing_country' => $transaction->billing_country,
                'closed_at' => $transaction->closed_at?->toIso8601String(),
            ],
            'subsidiary' => [
                'id' => $transaction->subsidiary_id,
                'display_name' => $transaction->subsidiary?->display_name,
            ],
            'location' => self::serializeLocation($transaction->location),
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
            'assigned_user' => $assignedUser ? [
                'id' => $assignedUser->id,
                'display_name' => $assignedUser->display_name ?: $assignedUser->full_name,
                'position_title' => $assignedUser->position_title,
                'signature' => $assignedUser->savedSignaturePayload(),
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function serializeLocation(?Location $location): ?array
    {
        if (! $location) {
            return null;
        }

        return [
            'id' => $location->id,
            'display_name' => $location->display_name,
            'address_line_1' => $location->address_line_1,
            'address_line_2' => $location->address_line_2,
            'city' => $location->city,
            'state' => $location->state,
            'postal_code' => $location->postal_code,
            'country' => $location->country,
        ];
    }
}
