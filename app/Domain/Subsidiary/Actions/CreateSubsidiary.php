<?php

namespace App\Domain\Subsidiary\Actions;

use App\Domain\Location\Actions\CreateLocation as CreateLocationAction;
use App\Domain\Subsidiary\Models\Subsidiary as RecordModel;
use App\Domain\Subsidiary\Support\GoogleReviewUrl;
use App\Enums\Locations\LocationType;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

class CreateSubsidiary
{
    private const ADDRESS_FIELDS = [
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postal_code',
        'country',
        'latitude',
        'longitude',
    ];

    public function __invoke(array $data): array
    {
        $locationTypeIds = array_column(LocationType::options(), 'id');

        $validationRules = [
            'display_name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'inactive' => ['nullable', 'boolean'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'string', 'max:255'],
            'google_review_url' => GoogleReviewUrl::validationRules(),
            'prompt_google_review_on_transaction_close' => ['nullable', 'boolean'],
            'timezone' => ['nullable', 'string', 'max:50'],
            'default_labor_rate' => ['nullable', 'numeric', 'min:0'],
            'work_order_prefix' => ['nullable', 'string', 'max:10'],
            'next_work_order_number' => ['nullable', 'integer', 'min:1000'],
            'settings' => ['nullable', 'array'],
            'notes' => ['nullable', 'string'],
            'logo' => ['nullable', 'integer'],
            'locations' => ['required', 'array', 'min:1'],
            'locations.*.display_name' => ['required', 'string', 'max:255'],
            'locations.*.location_type' => ['required', 'integer', Rule::in($locationTypeIds)],
            'locations.*.email' => ['nullable', 'email', 'max:255'],
            'locations.*.phone' => ['nullable', 'string', 'max:50'],
            'locations.*.notes' => ['nullable', 'string'],
            'locations.*.address_line_1' => ['nullable', 'string', 'max:255'],
            'locations.*.address_line_2' => ['nullable', 'string', 'max:255'],
            'locations.*.city' => ['nullable', 'string', 'max:255'],
            'locations.*.state' => ['nullable', 'string', 'max:255'],
            'locations.*.postal_code' => ['nullable', 'string', 'max:50'],
            'locations.*.country' => ['nullable', 'string', 'max:255'],
            'locations.*.latitude' => ['nullable', 'numeric'],
            'locations.*.longitude' => ['nullable', 'numeric'],
        ];

        if (! array_key_exists('locations', $data)) {
            return [
                'success' => false,
                'message' => 'At least one location is required.',
                'record' => null,
            ];
        }

        $validated = Validator::make($data, $validationRules)->validate();

        if (array_key_exists('google_review_url', $validated)) {
            $validated['google_review_url'] = GoogleReviewUrl::normalize($validated['google_review_url']);
        }

        $locations = $validated['locations'];
        $createData = $validated;
        unset($createData['locations']);

        foreach (self::ADDRESS_FIELDS as $field) {
            unset($createData[$field]);
        }

        if (array_key_exists('prompt_google_review_on_transaction_close', $createData)) {
            $createData['prompt_google_review_on_transaction_close'] = filter_var(
                $createData['prompt_google_review_on_transaction_close'],
                FILTER_VALIDATE_BOOLEAN
            );
        }

        if (array_key_exists('google_review_url', $createData)) {
            $createData['google_review_url'] = GoogleReviewUrl::normalize($createData['google_review_url']);
        }

        try {
            $record = DB::transaction(function () use ($createData, $locations) {
                $subsidiary = RecordModel::create($createData);

                foreach ($locations as $locationData) {
                    $result = (new CreateLocationAction)([
                        'display_name' => $locationData['display_name'],
                        'location_type' => (int) $locationData['location_type'],
                        'email' => $locationData['email'] ?? null,
                        'phone' => $locationData['phone'] ?? null,
                        'notes' => $locationData['notes'] ?? null,
                        'address_line_1' => $locationData['address_line_1'] ?? null,
                        'address_line_2' => $locationData['address_line_2'] ?? null,
                        'city' => $locationData['city'] ?? null,
                        'state' => $locationData['state'] ?? null,
                        'postal_code' => $locationData['postal_code'] ?? null,
                        'country' => $locationData['country'] ?? null,
                        'latitude' => $locationData['latitude'] ?? null,
                        'longitude' => $locationData['longitude'] ?? null,
                    ]);

                    if (! ($result['success'] ?? false) || empty($result['record'])) {
                        throw new \RuntimeException($result['message'] ?? 'Could not create location.');
                    }

                    $subsidiary->locations()->attach($result['record']->id);
                }

                return $subsidiary;
            });

            return [
                'success' => true,
                'record' => $record,
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in CreateSubsidiary', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in CreateSubsidiary', [
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
}
