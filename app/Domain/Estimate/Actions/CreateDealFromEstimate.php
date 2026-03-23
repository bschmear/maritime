<?php

namespace App\Domain\Estimate\Actions;

use App\Domain\Contact\Models\Contact;
use App\Domain\Contract\Actions\CreateContract;
use App\Domain\Estimate\Models\Estimate;
use App\Domain\Transaction\Actions\CreateTransaction;
use App\Domain\Transaction\Models\Transaction;
use App\Domain\Transaction\Models\TransactionItem;
use App\Domain\Transaction\Models\TransactionItemAddon;
use App\Domain\Transaction\Support\ComputeTransactionLineTax;
use App\Enums\Estimate\EstimateStatus;
use App\Models\AccountSettings;
use Illuminate\Support\Facades\DB;
use Throwable;

class CreateDealFromEstimate
{
    public function __construct(
        protected CreateTransaction $createTransaction,
    ) {}

    /**
     * @return array{success: bool, message?: string, transaction?: Transaction|null, already_existed?: bool}
     */
    public function __invoke(Estimate $estimate): array
    {
        if ($estimate->transaction_id) {
            return [
                'success' => true,
                'message' => 'Deal already exists for this estimate.',
                'transaction' => Transaction::find($estimate->transaction_id),
            ];
        }

        if (! $this->isApprovedAndSigned($estimate)) {
            return [
                'success' => false,
                'message' => 'Estimate must be approved and signed before creating a deal.',
                'transaction' => null,
            ];
        }

        try {
            $alreadyExisted = false;

            $result = DB::transaction(function () use ($estimate, &$alreadyExisted): Transaction {
                $locked = Estimate::query()
                    ->whereKey($estimate->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($locked->transaction_id) {
                    $alreadyExisted = true;

                    return Transaction::query()->findOrFail($locked->transaction_id);
                }

                $locked->loadMissing(['primaryVersion.lineItems.addons', 'customer']);

                $version = $locked->primaryVersion;
                if (! $version) {
                    throw new \RuntimeException('Estimate is missing a primary version.');
                }

                $settings = AccountSettings::getCurrent();
                $contact = $this->resolveContact($locked, $settings);

                $subtotal = (float) $version->subtotal;
                $taxTotal = (float) $version->tax;
                $total = (float) $version->total;

                $txResult = ($this->createTransaction)([
                    'customer_id' => $locked->customer_id,
                    'user_id' => $locked->user_id,
                    'estimate_id' => $locked->id,
                    'opportunity_id' => $locked->opportunity_id,
                    'status' => 'active',
                    'customer_name' => $locked->customer_name,
                    'customer_email' => $locked->customer_email,
                    'customer_phone' => $locked->customer_phone,
                    'billing_address_line1' => $locked->billing_address_line1,
                    'billing_address_line2' => $locked->billing_address_line2,
                    'billing_city' => $locked->billing_city,
                    'billing_state' => $locked->billing_state,
                    'billing_postal' => $locked->billing_postal,
                    'billing_country' => $locked->billing_country,
                    'billing_latitude' => $locked->billing_latitude,
                    'billing_longitude' => $locked->billing_longitude,
                    'title' => 'Deal for Estimate #'.$locked->sequence,
                    'subtotal' => $subtotal,
                    'tax_rate' => $locked->tax_rate,
                    'tax_jurisdiction' => $locked->tax_jurisdiction,
                    'tax_total' => $taxTotal,
                    'discount_total' => null,
                    'fees_total' => null,
                    'total' => $total,
                    'currency' => 'USD',
                    'notes' => $locked->notes,
                ]);

                if (! ($txResult['success'] ?? false) || empty($txResult['record'])) {
                    throw new \RuntimeException($txResult['message'] ?? 'Failed to create transaction.');
                }

                $transaction = $txResult['record'];

                $dealRate = floatval($locked->tax_rate ?? 0);
                $position = 0;

                foreach ($version->lineItems as $line) {
                    $qty = (float) ($line->quantity ?? 1);
                    $price = (float) ($line->unit_price ?? 0);
                    $discount = (float) ($line->discount ?? 0);
                    $lineBase = max(0, $qty * $price - $discount);
                    $itemTaxable = true;
                    $itemTax = ComputeTransactionLineTax::amount($lineBase, $itemTaxable, $dealRate);

                    $addonsPreTax = 0.0;
                    $addonsTaxSum = 0.0;

                    foreach ($line->addons as $estimateAddon) {
                        $aBase = (float) $estimateAddon->price * (int) $estimateAddon->quantity;
                        $aTaxable = true;
                        $addonsPreTax += $aBase;
                        $addonsTaxSum += ComputeTransactionLineTax::amount($aBase, $aTaxable, $dealRate);
                    }

                    $lineGrand = $lineBase + $addonsPreTax + $itemTax + $addonsTaxSum;

                    $itemableType = $line->itemable_type;
                    $type = match (true) {
                        str_ends_with((string) $itemableType, 'Asset') => 'asset',
                        str_ends_with((string) $itemableType, 'InventoryItem') => 'inventory',
                        default => 'line',
                    };

                    $txItem = TransactionItem::create([
                        'transaction_id' => $transaction->id,
                        'type' => $type,
                        'itemable_type' => $itemableType,
                        'itemable_id' => $line->itemable_id,
                        'name' => $line->name ?: 'Line item',
                        'description' => $line->description,
                        'quantity' => $qty,
                        'unit_price' => $price,
                        'discount' => $discount,
                        'subtotal' => $lineBase,
                        'taxable' => $itemTaxable,
                        'tax_rate' => $dealRate > 0 ? $dealRate : null,
                        'tax_amount' => $itemTax > 0 ? $itemTax : null,
                        'total' => $lineGrand,
                        'position' => $line->position ?? $position,
                        'estimate_item_id' => $line->id,
                    ]);

                    foreach ($line->addons as $estimateAddon) {
                        $aBase = (float) $estimateAddon->price * (int) $estimateAddon->quantity;
                        $aTaxable = true;
                        $aTax = ComputeTransactionLineTax::amount($aBase, $aTaxable, $dealRate);

                        TransactionItemAddon::create([
                            'transaction_item_id' => $txItem->id,
                            'addon_id' => $estimateAddon->addon_id,
                            'name' => $estimateAddon->name,
                            'price' => $estimateAddon->price,
                            'quantity' => $estimateAddon->quantity,
                            'taxable' => $aTaxable,
                            'tax_rate' => $dealRate > 0 ? $dealRate : null,
                            'tax_amount' => $aTax > 0 ? $aTax : null,
                            'notes' => $estimateAddon->notes,
                            'metadata' => $estimateAddon->metadata,
                        ]);
                    }

                    $position++;
                }

                /*                 $contractResult = (new CreateContract)([
                                    'contact_id'            => $contact->id,
                                    'estimate_id'           => $locked->id,
                                    'transaction_id'        => $transaction->id,
                                    'total_amount'          => $total,
                                    'currency'              => 'USD',
                                    'payment_terms'         => $locked->terms,
                                    'delivery_terms'        => $settings->default_delivery_terms,
                                    'notes'                 => $locked->notes,
                                    'billing_address_line1' => $locked->billing_address_line1,
                                    'billing_address_line2' => $locked->billing_address_line2,
                                    'billing_city'          => $locked->billing_city,
                                    'billing_state'         => $locked->billing_state,
                                    'billing_postal'        => $locked->billing_postal,
                                    'billing_country'       => $locked->billing_country,
                                    'billing_latitude'      => $locked->billing_latitude,
                                    'billing_longitude'     => $locked->billing_longitude,
                                ]);

                                if (! $contractResult['success']) {
                                    throw new \RuntimeException($contractResult['message'] ?? 'Failed to create contract.');
                                } */

                $locked->update(['transaction_id' => $transaction->id]);

                return $transaction->fresh();
            });

            return [
                'success' => true,
                'transaction' => $result,
                'already_existed' => $alreadyExisted,
                'message' => $alreadyExisted ? 'Deal already exists for this estimate.' : null,
            ];
        } catch (Throwable $e) {
            report($e);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'transaction' => null,
            ];
        }
    }

    protected function isApprovedAndSigned(Estimate $estimate): bool
    {
        $status = $estimate->status;
        $approved = $status === EstimateStatus::Approved->value
            || (int) $status === EstimateStatus::Approved->id();

        // Portal approval sets approved_at (and should set signed_at). Legacy rows may only have approved_at.
        $accepted = $estimate->signed_at !== null || $estimate->approved_at !== null;

        return $approved && $accepted;
    }

    protected function resolveContact(Estimate $estimate, AccountSettings $settings): Contact
    {
        $email = $estimate->customer_email ?: $estimate->customer?->email;

        $query = Contact::query()->where('account_settings_id', $settings->id);

        if ($email) {
            $existing = (clone $query)->whereRaw('LOWER(email) = ?', [strtolower($email)])->first();
            if ($existing) {
                return $existing;
            }
        }

        $customer = $estimate->customer;

        return Contact::create([
            'account_settings_id' => $settings->id,
            'display_name' => $estimate->customer_name ?: $customer?->display_name ?: 'Customer',
            'first_name' => $customer?->first_name,
            'last_name' => $customer?->last_name,
            'email' => $email,
            'phone' => $estimate->customer_phone ?: $customer?->phone,
        ]);
    }
}
