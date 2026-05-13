<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\AssetUnit\Models\AssetUnit;
use App\Domain\ConsignmentAgreement\Models\ConsignmentAgreement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ConsignmentAgreementController extends Controller
{
    public function store(Request $request, AssetUnit $assetunit): RedirectResponse
    {
        abort_unless($assetunit->is_consignment, 422, 'This unit is not marked as consignment.');

        $existing = $assetunit->consignmentAgreements()->unsigned()->latest('id')->first();
        if ($existing) {
            return back()->with('info', 'A draft consignment agreement already exists for this unit.');
        }

        $assetunit->load(['asset:id,display_name', 'assetVariant:id,display_name', 'customer']);

        ConsignmentAgreement::create([
            'asset_unit_id' => $assetunit->id,
            'agreement_date' => now()->toDateString(),
            'boat_description' => $assetunit->asset?->display_name,
            'motor_description' => $assetunit->assetVariant?->display_name,
            'asking_boat' => $assetunit->asking_price,
            'owner_seller_name' => $assetunit->customer?->display_name,
            'owner_address' => $this->formatCustomerAddress($assetunit->customer),
            'owner_phone_1' => $assetunit->customer?->phone,
            'owner_phone_2' => $assetunit->customer?->mobile,
        ]);

        return back()->with('success', 'Consignment agreement draft created. Fill in details and share the customer link.');
    }

    public function update(Request $request, AssetUnit $assetunit): RedirectResponse
    {
        abort_unless($assetunit->is_consignment, 422, 'This unit is not marked as consignment.');

        $agreement = $assetunit->consignmentAgreements()->unsigned()->latest('id')->firstOrFail();

        $validated = $request->validate([
            'agreement_date' => 'nullable|date',
            'boat_description' => 'nullable|string|max:20000',
            'motor_description' => 'nullable|string|max:20000',
            'other_description' => 'nullable|string|max:20000',
            'owner_seller_name' => 'nullable|string|max:255',
            'owner_address' => 'nullable|string|max:2000',
            'owner_phone_1' => 'nullable|string|max:50',
            'owner_phone_2' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:20000',
            'asking_boat' => 'nullable|numeric',
            'asking_motor' => 'nullable|numeric',
            'asking_other' => 'nullable|numeric',
            'asking_sold' => 'nullable|numeric',
            'minimum_boat' => 'nullable|numeric',
            'minimum_motor' => 'nullable|numeric',
            'minimum_other' => 'nullable|numeric',
            'minimum_sold' => 'nullable|numeric',
        ]);

        $validated['boat_title_signed_delivered'] = $request->boolean('boat_title_signed_delivered');

        foreach ([
            'asking_boat', 'asking_motor', 'asking_other', 'asking_sold',
            'minimum_boat', 'minimum_motor', 'minimum_other', 'minimum_sold',
        ] as $moneyKey) {
            if (array_key_exists($moneyKey, $validated) && $validated[$moneyKey] === '') {
                $validated[$moneyKey] = null;
            }
        }

        $agreement->update($validated);

        return back()->with('success', 'Consignment agreement updated.');
    }

    private function formatCustomerAddress(?\App\Domain\Customer\Models\Customer $customer): ?string
    {
        if ($customer === null) {
            return null;
        }

        $parts = array_filter([
            $customer->address_line_1,
            $customer->address_line_2,
            trim(implode(', ', array_filter([$customer->city, $customer->state])).($customer->postal_code ? ' '.$customer->postal_code : '')),
        ]);

        return $parts !== [] ? implode("\n", $parts) : null;
    }
}
