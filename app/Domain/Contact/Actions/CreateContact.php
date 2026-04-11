<?php

namespace App\Domain\Contact\Actions;

use App\Domain\Contact\Models\Contact as RecordModel;
use App\Domain\Contact\Models\ContactAddress;
use App\Enums\Entity\ContactMethod;
use App\Enums\Entity\ContactStage;
use App\Enums\Entity\ContactStatus;
use App\Enums\Entity\ContactTimePreference;
use App\Enums\Entity\ContactType;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

class CreateContact
{
    public function __invoke(array $data): array
    {
        $validated = Validator::make($data, [

            /*
            |--------------------------------------------------------------------------
            | Identity
            |--------------------------------------------------------------------------
            */
            'display_name' => ['nullable', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],

            'type' => ['nullable', Rule::in(array_column(ContactType::cases(), 'value'))],

            /*
            |--------------------------------------------------------------------------
            | Contact Info
            |--------------------------------------------------------------------------
            */
            'email' => ['nullable', 'email', 'max:255'],
            'secondary_email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'mobile' => ['nullable', 'string', 'max:50'],

            /*
            |--------------------------------------------------------------------------
            | Company
            |--------------------------------------------------------------------------
            */
            'company' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],

            /*
            |--------------------------------------------------------------------------
            | Ownership / Preferences
            |--------------------------------------------------------------------------
            */
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],

            'vendor_id' => ['nullable', 'integer', 'exists:vendors,id'],

            'preferred_contact_method' => [
                'nullable',
                fn (string $attribute, mixed $value, \Closure $fail) => ContactMethod::assertValidForValidation($value, $fail, $attribute),
            ],

            'preferred_contact_time' => [
                'nullable',
                fn (string $attribute, mixed $value, \Closure $fail) => ContactTimePreference::assertValidForValidation($value, $fail, $attribute),
            ],

            /*
            |--------------------------------------------------------------------------
            | Classification
            |--------------------------------------------------------------------------
            */
            'source' => ['nullable', 'string', 'max:255'],

            'status' => [
                'nullable',
                fn (string $attribute, mixed $value, \Closure $fail) => ContactStatus::assertValidForValidation($value, $fail, $attribute),
            ],

            'stage_id' => [
                'nullable',
                fn (string $attribute, mixed $value, \Closure $fail) => ContactStage::assertValidForValidation($value, $fail, $attribute),
            ],

            'inactive' => ['nullable', 'boolean'],

            /*
            |--------------------------------------------------------------------------
            | Meta
            |--------------------------------------------------------------------------
            */
            'website' => ['nullable', 'url'],
            'linkedin' => ['nullable', 'url'],
            'facebook' => ['nullable', 'url'],

            'avatar' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],

            /*
            |--------------------------------------------------------------------------
            | Addresses (array support)
            |--------------------------------------------------------------------------
            */
            'addresses' => ['nullable', 'array'],

            'addresses.*.label' => ['nullable', 'string', 'max:255'],
            'addresses.*.is_primary' => ['nullable', 'boolean'],

            'addresses.*.address_line_1' => ['nullable', 'string', 'max:255'],
            'addresses.*.address_line_2' => ['nullable', 'string', 'max:255'],
            'addresses.*.city' => ['nullable', 'string', 'max:255'],
            'addresses.*.state' => ['nullable', 'string', 'max:255'],
            'addresses.*.postal_code' => ['nullable', 'string', 'max:50'],
            'addresses.*.country' => ['nullable', 'string', 'max:255'],

        ])->validate();

        foreach ([
            'preferred_contact_method' => ContactMethod::class,
            'preferred_contact_time' => ContactTimePreference::class,
            'status' => ContactStatus::class,
        ] as $key => $enumClass) {
            if (! array_key_exists($key, $validated)) {
                continue;
            }
            $v = $validated[$key];
            if ($v === null || $v === '') {
                $validated[$key] = null;

                continue;
            }
            $validated[$key] = $enumClass::toStoredValue($v);
        }

        if (array_key_exists('stage_id', $validated)) {
            $sv = $validated['stage_id'];
            if ($sv === null || $sv === '') {
                $validated['stage_id'] = null;
            } else {
                $validated['stage_id'] = ContactStage::toStoredId($sv);
            }
        }

        try {
            return DB::transaction(function () use ($validated) {

                if (empty($validated['display_name'])) {
                    $validated['display_name'] = trim(
                        ($validated['first_name'] ?? '').' '.($validated['last_name'] ?? '')
                    ) ?: ($validated['company'] ?? null);
                }

                $validated['type'] = $validated['type'] ?? ContactType::Person->value;
                $validated['status'] = $validated['status'] ?? ContactStatus::Active->value;
                $validated['stage_id'] = $validated['stage_id'] ?? ContactStage::Contact->id();

                $addresses = $validated['addresses'] ?? [];
                unset($validated['addresses']);

                $record = RecordModel::create($validated);

                if (! empty($addresses)) {

                    // Ensure only ONE primary
                    $primarySet = false;

                    foreach ($addresses as $index => $address) {

                        $isPrimary = $address['is_primary'] ?? false;

                        if ($isPrimary && ! $primarySet) {
                            $primarySet = true;
                        } else {
                            $isPrimary = false;
                        }

                        ContactAddress::create([
                            'contact_id' => $record->id,
                            'label' => $address['label'] ?? null,
                            'is_primary' => $isPrimary,

                            'address_line_1' => $address['address_line_1'] ?? null,
                            'address_line_2' => $address['address_line_2'] ?? null,
                            'city' => $address['city'] ?? null,
                            'state' => $address['state'] ?? null,
                            'postal_code' => $address['postal_code'] ?? null,
                            'country' => $address['country'] ?? null,
                        ]);
                    }

                    // If none marked primary → set first as primary
                    if (! $primarySet && count($addresses) > 0) {
                        $record->addresses()->first()?->update(['is_primary' => true]);
                    }
                }

                return [
                    'success' => true,
                    'record' => $record->load('addresses'),
                ];
            });

        } catch (QueryException $e) {
            Log::error('Database query error in CreateContact', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return [
                'success' => false,
                'message' => 'Database error creating contact',
                'record' => null,
            ];

        } catch (Throwable $e) {
            Log::error('Unexpected error in CreateContact', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return [
                'success' => false,
                'message' => 'Unexpected error creating contact',
                'record' => null,
            ];
        }
    }
}
