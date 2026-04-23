<?php

namespace App\Domain\Invoice\Actions;

use App\Domain\Customer\Models\Customer;
use App\Domain\WorkOrder\Models\WorkOrder;
use App\Enums\Payments\Currency;
use App\Enums\ServiceTicketServiceItem\WarrantyCoverageType;

class BuildInvoicePrefillFromWorkOrder
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(WorkOrder $workOrder): array
    {
        $workOrder->load([
            'customer' => Customer::eagerWithContactSelect(['email', 'phone', 'mobile']),
            'subsidiary' => fn ($q) => $q->select(['id', 'display_name']),
            'assetUnit' => fn ($q) => $q
                ->select(['id', 'asset_id', 'serial_number', 'hin', 'sku'])
                ->with(['asset' => fn ($aq) => $aq->select(['id', 'display_name'])]),
            'location' => fn ($q) => $q->select([
                'id',
                'display_name',
                'address_line_1',
                'address_line_2',
                'city',
                'state',
                'postal_code',
                'country',
            ]),
            'serviceItems' => fn ($q) => $q->orderBy('sort_order')->orderBy('id'),
        ]);

        $customer = $workOrder->customer;
        $contact = $customer?->contact;
        $assetUnit = $workOrder->assetUnit;
        $location = $workOrder->location;
        $taxRate = (float) ($workOrder->tax_rate ?? 0);

        $workOrderLabel = $workOrder->work_order_number
            ? 'Work Order #'.$workOrder->work_order_number
            : 'Work Order #'.$workOrder->id;
        $assetUnitLabel = trim((string) ($assetUnit?->display_name ?? ''));
        $notes = 'Created from '.$workOrderLabel;
        if ($assetUnitLabel !== '') {
            $notes .= ' | Asset Unit: '.$assetUnitLabel;
        }

        $initialData = [
            'work_order_id' => $workOrder->id,
            'contact_id' => $customer?->contact_id,
            'currency' => Currency::toStoredValue('USD') ?? 'USD',
            'tax_rate' => $taxRate,
            'fees_total' => 0,
            'notes' => $notes,
            'subsidiary_id' => $workOrder->subsidiary_id,
            'location_id' => $workOrder->location_id,
        ];

        if ($workOrder->subsidiary) {
            $initialData['subsidiary'] = [
                'id' => $workOrder->subsidiary->id,
                'display_name' => $workOrder->subsidiary->display_name,
            ];
        }
        if ($location) {
            $initialData['location'] = [
                'id' => $location->id,
                'display_name' => $location->display_name,
            ];
        }

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

        $initialData['work_order'] = [
            'id' => $workOrder->id,
            'work_order_number' => $workOrder->work_order_number,
            'display_name' => $workOrder->display_name,
        ];

        $initialData['customer_name'] = $customer?->display_name ?? $contact?->display_name ?? '';
        $initialData['customer_email'] = $customer?->email ?? $contact?->email ?? '';
        $initialData['customer_phone'] = $customer?->phone ?? $contact?->phone ?? $contact?->mobile ?? '';

        $initialData['billing_address_line1'] = $location?->address_line_1 ?? '';
        $initialData['billing_address_line2'] = $location?->address_line_2 ?? '';
        $initialData['billing_city'] = $location?->city ?? '';
        $initialData['billing_state'] = $location?->state ?? '';
        $initialData['billing_postal'] = $location?->postal_code ?? '';
        $initialData['billing_country'] = $location?->country ?? '';

        $billableItems = $workOrder->serviceItems
            ->filter(fn ($item) => (bool) $item->billable)
            ->values();

        $initialData['items'] = $billableItems->map(function ($item, $index) use ($taxRate) {
            $billingType = (int) ($item->billing_type ?? 3);
            $quantity = 1.0;
            $unitPrice = (float) ($item->unit_price ?? 0);
            $isWarranty = (bool) ($item->warranty ?? false);
            $warrantyType = $item->warranty_type instanceof WarrantyCoverageType
                ? $item->warranty_type->value
                : ($item->warranty_type ?? null);
            $billableTo = $item->billable_to ?? null;
            if (! $billableTo) {
                $billableTo = ! $isWarranty
                    ? 'customer'
                    : ($warrantyType === WarrantyCoverageType::Manufacturer->value ? 'manufacturer' : 'internal');
            }

            if ($billingType === 1) {
                $quantity = (float) ($item->estimated_hours ?? 0);
            } elseif ($billingType === 3) {
                $quantity = (float) ($item->quantity ?? 1);
            }

            return [
                'name' => $item->display_name ?? '',
                'description' => $item->description ?? '',
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'cost' => (float) ($item->unit_cost ?? 0),
                'discount' => 0,
                'is_warranty' => $isWarranty,
                'warranty_type' => $warrantyType,
                'billable_to' => $billableTo,
                'taxable' => $taxRate > 0,
                'tax_rate' => $taxRate,
                'position' => $index,
            ];
        })->all();

        return $initialData;
    }
}
