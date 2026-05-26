<?php

declare(strict_types=1);

namespace App\Domain\Invoice\Support;

use App\Domain\Transaction\Models\Transaction;
use App\Domain\Transaction\Models\TransactionLineItem;

final class FlattenTransactionItemsForInvoice
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function fromTransaction(Transaction $transaction): array
    {
        $transaction->loadMissing([
            'items.addons',
            'items.selectedAssetOptions',
            'items.selectedAssetOptionsFromSourceLine',
        ]);

        $taxRate = (float) ($transaction->tax_rate ?? 0);
        $out = [];
        $position = 0;

        foreach ($transaction->items as $line) {
            $out[] = self::mainLinePayload($line, $position++, $taxRate);

            foreach ($line->addons as $addon) {
                $out[] = [
                    'transaction_line_item_id' => null,
                    'itemable_type' => null,
                    'itemable_id' => null,
                    'asset_variant_id' => null,
                    'asset_unit_id' => null,
                    'name' => $line->name.' — '.($addon->name ?: 'Add-on'),
                    'description' => $addon->notes,
                    'quantity' => (float) ($addon->quantity ?? 1),
                    'unit_price' => (float) ($addon->price ?? 0),
                    'cost' => 0,
                    'discount' => 0,
                    'is_warranty' => false,
                    'warranty_type' => null,
                    'billable_to' => 'customer',
                    'position' => $position++,
                    'taxable' => (bool) ($addon->taxable ?? true),
                    'tax_rate' => ($addon->taxable ?? true) ? $taxRate : 0,
                ];
            }

            $options = $line->selectedAssetOptions->isNotEmpty()
                ? $line->selectedAssetOptions
                : $line->selectedAssetOptionsFromSourceLine;

            foreach ($options as $option) {
                $out[] = [
                    'transaction_line_item_id' => null,
                    'itemable_type' => null,
                    'itemable_id' => null,
                    'asset_variant_id' => null,
                    'asset_unit_id' => null,
                    'name' => $line->name.' — '.self::optionLabel($option),
                    'description' => null,
                    'quantity' => 1,
                    'unit_price' => (float) ($option->price ?? 0),
                    'cost' => 0,
                    'discount' => 0,
                    'is_warranty' => false,
                    'warranty_type' => null,
                    'billable_to' => 'customer',
                    'position' => $position++,
                    'taxable' => (bool) ($option->taxable ?? true),
                    'tax_rate' => ($option->taxable ?? true) ? $taxRate : 0,
                ];
            }
        }

        return $out;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array{subtotal: float, tax_total: float, discount_total: float, total: float}
     */
    public static function rollupTotals(array $items, float $feesTotal = 0): array
    {
        $subtotal = 0.0;
        $discountTotal = 0.0;
        $taxTotal = 0.0;

        foreach ($items as $item) {
            $qty = (float) ($item['quantity'] ?? 1);
            $price = (float) ($item['unit_price'] ?? 0);
            $discount = (float) ($item['discount'] ?? 0);
            $itemSub = ($qty * $price) - $discount;

            $subtotal += $itemSub;
            $discountTotal += $discount;

            if (! empty($item['taxable']) && ! empty($item['tax_rate'])) {
                $taxTotal += round($itemSub * ((float) $item['tax_rate'] / 100), 2);
            }
        }

        $subtotal = round($subtotal, 2);
        $taxTotal = round($taxTotal, 2);
        $total = round($subtotal + $taxTotal + $feesTotal, 2);

        return [
            'subtotal' => $subtotal,
            'tax_total' => $taxTotal,
            'discount_total' => round($discountTotal, 2),
            'total' => $total,
        ];
    }

    private static function mainLinePayload(TransactionLineItem $line, int $position, float $taxRate): array
    {
        return [
            'transaction_line_item_id' => $line->id,
            'itemable_type' => $line->itemable_type,
            'itemable_id' => $line->itemable_id,
            'asset_variant_id' => $line->asset_variant_id,
            'asset_unit_id' => $line->asset_unit_id,
            'name' => $line->name ?? '',
            'description' => $line->description,
            'quantity' => (float) ($line->quantity ?? 1),
            'unit_price' => (float) ($line->unit_price ?? 0),
            'cost' => 0,
            'discount' => (float) ($line->discount ?? 0),
            'is_warranty' => false,
            'warranty_type' => null,
            'billable_to' => 'customer',
            'position' => $position,
            'taxable' => (bool) ($line->taxable ?? false),
            'tax_rate' => ($line->taxable ?? false) ? $taxRate : 0,
        ];
    }

    private static function optionLabel(object $option): string
    {
        $name = trim((string) ($option->option_name ?? ''));
        $value = trim((string) ($option->value_label ?? ''));

        if ($name !== '' && $value !== '') {
            return $name.': '.$value;
        }

        return $name !== '' ? $name : ($value !== '' ? $value : 'Option');
    }
}
