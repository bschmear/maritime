<?php

namespace App\Domain\Invoice\Actions;

use App\Domain\Asset\Models\Asset;
use App\Domain\Customer\Models\Customer;
use App\Domain\InventoryItem\Models\InventoryItem;
use App\Domain\Transaction\Models\Transaction;
use App\Enums\Payments\Currency;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class BuildInvoicePrefillFromTransaction
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(Transaction $transaction): array
    {
        $transaction->load([
            'contract' => fn ($q) => $q->select(['id', 'sequence']),
            'customer' => Customer::eagerWithContactSelect(['email', 'phone', 'mobile']),
            'items' => fn ($q) => $q
                ->with([
                    'addons',
                    'itemable' => function (MorphTo $morph) {
                        $morph->constrain([
                            Asset::class => fn ($query) => $query
                                ->select(['id', 'display_name', 'year', 'make_id'])
                                ->with(['make' => fn ($m) => $m->select(['id', 'display_name'])]),
                            InventoryItem::class => fn ($query) => $query->select(['id', 'display_name', 'sku']),
                        ]);
                    },
                    'estimateLineItem' => fn ($q2) => $q2
                        ->select(['id', 'asset_variant_id'])
                        ->with([
                            'assetVariant' => fn ($q3) => $q3->select(['id', 'display_name', 'name']),
                        ]),
                ])
                ->orderBy('position')
                ->orderBy('id'),
        ]);

        $customer = $transaction->customer;
        $contact = $customer?->contact;

        $initialData = [
            'transaction_id' => $transaction->id,
            'contact_id' => $customer?->contact_id,
            'currency' => Currency::toStoredValue($transaction->currency ?? 'USD') ?? 'USD',
            'tax_rate' => (float) ($transaction->tax_rate ?? 0),
            'discount_total' => (float) ($transaction->discount_total ?? 0),
            'fees_total' => (float) ($transaction->fees_total ?? 0),
            'notes' => $transaction->notes,
        ];

        if ($contact) {
            $initialData['contact'] = [
                'id' => $contact->id,
                'display_name' => $contact->display_name,
                'first_name' => $contact->first_name,
                'last_name' => $contact->last_name,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'mobile' => $contact->mobile,
            ];
        }

        $initialData['transaction'] = [
            'id' => $transaction->id,
            'display_name' => $transaction->display_name,
        ];

        if ($transaction->contract) {
            $initialData['contract_id'] = $transaction->contract->id;
            $initialData['contract'] = [
                'id' => $transaction->contract->id,
                'display_name' => $transaction->contract->display_name,
            ];
        }

        $initialData['customer_name'] = $transaction->customer_name
            ?? $customer?->display_name
            ?? $contact?->display_name
            ?? '';
        $initialData['customer_email'] = $transaction->customer_email
            ?? $customer?->email
            ?? $contact?->email
            ?? '';
        $initialData['customer_phone'] = $transaction->customer_phone
            ?? $customer?->phone
            ?? $contact?->phone
            ?? $contact?->mobile
            ?? '';

        $initialData['billing_address_line1'] = $transaction->billing_address_line1;
        $initialData['billing_address_line2'] = $transaction->billing_address_line2;
        $initialData['billing_city'] = $transaction->billing_city;
        $initialData['billing_state'] = $transaction->billing_state;
        $initialData['billing_postal'] = $transaction->billing_postal;
        $initialData['billing_country'] = $transaction->billing_country;

        $initialData['items'] = $transaction->items->map(function ($item) {
            $eli = $item->estimateLineItem;

            return [
                'transaction_item_id' => $item->id,
                'itemable_type' => $item->itemable_type,
                'itemable_id' => $item->itemable_id,
                'name' => $item->name ?? '',
                'description' => $item->description ?? '',
                'quantity' => (float) ($item->quantity ?? 1),
                'unit_price' => (float) ($item->unit_price ?? 0),
                'discount' => (float) ($item->discount ?? 0),
                'taxable' => (bool) ($item->taxable ?? false),
                'tax_rate' => (float) ($item->tax_rate ?? 0),
                'position' => $item->position ?? 0,
                'itemable' => $item->relationLoaded('itemable') && $item->itemable ? match (true) {
                    $item->itemable instanceof Asset => [
                        'year' => $item->itemable->year,
                        'make' => $item->itemable->relationLoaded('make') && $item->itemable->make
                            ? ['display_name' => $item->itemable->make->display_name]
                            : null,
                    ],
                    $item->itemable instanceof InventoryItem => [
                        'sku' => $item->itemable->sku,
                    ],
                    default => null,
                } : null,
                'addons' => $item->addons->map(fn ($a) => [
                    'id' => $a->id,
                    'addon_id' => $a->addon_id,
                    'name' => $a->name,
                    'price' => (float) ($a->price ?? 0),
                    'quantity' => (int) ($a->quantity ?? 1),
                    'taxable' => (bool) ($a->taxable ?? true),
                    'tax_rate' => $a->tax_rate !== null ? (float) $a->tax_rate : null,
                    'notes' => $a->notes,
                ])->values()->all(),
                'estimate_line_item' => $eli ? [
                    'id' => $eli->id,
                    'asset_variant_id' => $eli->asset_variant_id,
                    'asset_variant' => $eli->relationLoaded('assetVariant') && $eli->assetVariant ? [
                        'id' => $eli->assetVariant->id,
                        'display_name' => $eli->assetVariant->display_name,
                        'name' => $eli->assetVariant->name,
                    ] : null,
                ] : null,
            ];
        })->values()->all();

        return $initialData;
    }
}
