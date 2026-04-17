<?php

namespace App\Domain\DeliveryLocation\Actions;

use App\Domain\DeliveryLocation\Models\DeliveryLocation as RecordModel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

class CreateDeliveryLocation
{
    public function __invoke(array $data): array
    {
        $validated = Validator::make($data, self::rules())->validate();

        try {
            $record = RecordModel::create(array_merge($validated, [
                'uuid' => (string) Str::uuid(),
            ]));

            return [
                'success' => true,
                'record' => $record,
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in CreateDeliveryLocation', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in CreateDeliveryLocation', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        }
    }

    public static function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'subsidiary_id' => 'nullable|exists:subsidiaries,id',
            'address_line_1' => 'nullable|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'contact_name' => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:5000',
            'active' => 'nullable|boolean',
        ];
    }
}
