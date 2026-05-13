<?php

declare(strict_types=1);

namespace App\Domain\ConsignmentAgreement\Actions;

use App\Domain\AssetUnit\Models\AssetUnit;
use App\Domain\ConsignmentAgreement\Models\ConsignmentAgreement as RecordModel;
use App\Domain\Contact\Models\ContactAddress;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class CreateConsignmentAgreement
{
    /**
     * @return array{success: bool, record?: RecordModel, message?: string}
     */
    public function __invoke(array $data): array
    {
        $validated = Validator::make($data, [
            'asset_unit_id' => 'required|integer|exists:asset_units,id',
            'agreement_date' => 'nullable|date',
            'boat_description' => 'nullable|string|max:20000',
            'motor_description' => 'nullable|string|max:20000',
            'other_description' => 'nullable|string|max:20000',
            'boat_title_signed_delivered' => 'sometimes|boolean',
            'owner_contact_id' => 'required|integer|exists:contacts,id',
            'owner_contact_address_id' => 'nullable|integer|exists:contact_addresses,id',
            'notes' => 'nullable|string|max:20000',
            'asking_boat' => 'nullable|numeric',
            'asking_motor' => 'nullable|numeric',
            'asking_other' => 'nullable|numeric',
            'asking_sold' => 'nullable|numeric',
            'minimum_boat' => 'nullable|numeric',
            'minimum_motor' => 'nullable|numeric',
            'minimum_other' => 'nullable|numeric',
            'minimum_sold' => 'nullable|numeric',
        ])->after(function (\Illuminate\Validation\Validator $validator): void {
            $v = $validator->validated();
            $contactId = (int) ($v['owner_contact_id'] ?? 0);
            $addrId = isset($v['owner_contact_address_id']) ? (int) $v['owner_contact_address_id'] : 0;
            if ($addrId > 0) {
                $belongs = ContactAddress::query()
                    ->whereKey($addrId)
                    ->where('contact_id', $contactId)
                    ->exists();
                if (! $belongs) {
                    $validator->errors()->add('owner_contact_address_id', 'The selected address does not belong to this contact.');
                }
            }
            $unit = AssetUnit::query()->with('customer')->find((int) ($v['asset_unit_id'] ?? 0));
            $expected = $unit?->customer?->contact_id;
            if ($expected !== null && $contactId !== (int) $expected) {
                $validator->errors()->add('owner_contact_id', 'Owner contact must match the consignment unit’s customer contact.');
            }
        })->validate();

        foreach ([
            'asking_boat', 'asking_motor', 'asking_other', 'asking_sold',
            'minimum_boat', 'minimum_motor', 'minimum_other', 'minimum_sold',
        ] as $moneyKey) {
            if (array_key_exists($moneyKey, $validated) && $validated[$moneyKey] === '') {
                $validated[$moneyKey] = null;
            }
        }

        try {
            $record = RecordModel::create($validated);

            return [
                'success' => true,
                'record' => $record,
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in CreateConsignmentAgreement', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in CreateConsignmentAgreement', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
