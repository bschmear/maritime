<?php

namespace App\Domain\Estimate\Actions;

use App\Domain\Contact\Models\Contact;
use App\Domain\Contract\Actions\CreateContract;
use App\Domain\Customer\Models\Customer;
use App\Domain\Estimate\Models\Estimate;
use App\Domain\Subsidiary\Models\Subsidiary;
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

                // Resolve (or create) the contact and ensure a customer profile exists.
                $contact = $this->resolveContact($locked);
                $customer = $this->ensureCustomerProfile($contact, $locked);

                // Back-fill contact_id on the estimate if not already set.
                if (! $locked->contact_id) {
                    $locked->update(['contact_id' => $contact->id]);
                }

                // Prefer the estimate's own subsidiary/location; fall back to user-based resolution.
                $subsidiaryId = $locked->subsidiary_id ?? $this->resolveSubsidiaryId($locked);
                $locationId = $locked->location_id ?? $this->resolveLocationId($subsidiaryId);

                $subtotal = (float) $version->subtotal;
                $taxTotal = (float) $version->tax;
                $total = (float) $version->total;

                $txResult = ($this->createTransaction)([
                    'customer_id' => $customer->id,
                    'user_id' => $locked->user_id,
                    'estimate_id' => $locked->id,
                    'opportunity_id' => $locked->opportunity_id,
                    'subsidiary_id' => $subsidiaryId,
                    'location_id' => $locationId,
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
                        'asset_variant_id' => $line->asset_variant_id ?: null,
                        'asset_unit_id' => $line->asset_unit_id ?: null,
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

    /**
     * Pick the location for the transaction from the subsidiary's linked locations.
     *   1. The subsidiary's primary location (pivot primary = true).
     *   2. Any location linked to the subsidiary.
     *   3. The first location in the tenant.
     */
    protected function resolveLocationId(?int $subsidiaryId): ?int
    {
        if ($subsidiaryId) {
            $sub = Subsidiary::query()->find($subsidiaryId);
            if ($sub) {
                $primary = $sub->locations()->wherePivot('primary', true)->first();
                if ($primary) {
                    return $primary->id;
                }

                $any = $sub->locations()->first();
                if ($any) {
                    return $any->id;
                }
            }
        }

        return \App\Domain\Location\Models\Location::query()->value('id');
    }

    /**
     * Pick a subsidiary for the transaction:
     *   1. The assigned user's primary subsidiary.
     *   2. Any subsidiary the assigned user belongs to.
     *   3. The first subsidiary in the tenant (fallback for single-subsidiary setups).
     */
    protected function resolveSubsidiaryId(Estimate $estimate): ?int
    {
        $user = $estimate->user ?? \App\Domain\User\Models\User::query()->find($estimate->user_id);

        if ($user) {
            $primary = $user->subsidiaries()->wherePivot('primary', true)->first();
            if ($primary) {
                return $primary->id;
            }

            $any = $user->subsidiaries()->first();
            if ($any) {
                return $any->id;
            }
        }

        return Subsidiary::query()->value('id');
    }

    /**
     * Resolve the Contact for this estimate.
     *  1. If estimate.contact_id is already set, return that contact.
     *  2. If estimate.customer has a contact_id, use that contact.
     *  3. Search by email; create a new contact as a last resort.
     */
    protected function resolveContact(Estimate $estimate): Contact
    {
        // Prefer the directly linked contact.
        if ($estimate->contact_id) {
            $direct = Contact::find($estimate->contact_id);
            if ($direct) {
                return $direct;
            }
        }

        // Try via the customer profile's contact.
        $customer = $estimate->customer;
        if ($customer && $customer->contact_id) {
            $via = Contact::find($customer->contact_id);
            if ($via) {
                return $via;
            }
        }

        // Fall back to email search or create.
        $email = $estimate->customer_email ?: $customer?->email;

        if ($email) {
            $existing = Contact::findByEmailCaseInsensitive($email);
            if ($existing) {
                return $existing;
            }
        }

        return Contact::create([
            'display_name' => $estimate->customer_name ?: $customer?->display_name ?: 'Customer',
            'first_name' => $customer?->first_name,
            'last_name' => $customer?->last_name,
            'email' => $email,
            'phone' => $estimate->customer_phone ?: $customer?->phone,
        ]);
    }

    /**
     * Find or create a customer_profile for the given contact.
     * Prefers the estimate's existing customer_id to avoid creating a duplicate.
     */
    protected function ensureCustomerProfile(Contact $contact, Estimate $estimate): Customer
    {
        // Re-use the estimate's existing customer profile if it is already linked to the contact.
        if ($estimate->customer_id) {
            $existing = Customer::find($estimate->customer_id);
            if ($existing) {
                // Ensure the customer is linked to the contact (back-fill if legacy row).
                if (! $existing->contact_id) {
                    $existing->update(['contact_id' => $contact->id]);
                }

                return $existing;
            }
        }

        // Find or create by contact.
        $byContact = Customer::where('contact_id', $contact->id)->first();
        if ($byContact) {
            return $byContact;
        }

        return Customer::create([
            'contact_id' => $contact->id,
            'account_status' => 'active',
        ]);
    }
}
