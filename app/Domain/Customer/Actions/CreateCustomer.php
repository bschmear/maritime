<?php

namespace App\Domain\Customer\Actions;

use App\Domain\Contact\Models\Contact;
use App\Domain\Contact\Models\ContactAddress;
use App\Domain\Customer\Models\Customer as RecordModel;
use App\Enums\Entity\ContactStage;
use App\Enums\Entity\ContactStatus;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateCustomer
{
    public function __invoke(array $data): array
    {
        try {
            $existingContactId = null;
            if (isset($data['contact_id']) && $data['contact_id'] !== null && $data['contact_id'] !== '') {
                $cid = filter_var($data['contact_id'], FILTER_VALIDATE_INT);
                if ($cid !== false && $cid > 0) {
                    $existingContactId = $cid;
                }
            }

            if ($existingContactId !== null) {
                Validator::make($data, [
                    'contact_id' => ['required', 'integer', 'exists:contacts,id'],
                    'subsidiary_id' => ['required', 'integer', 'exists:subsidiaries,id'],
                ])->validate();
                $fieldsToSave = $data;
            } else {
                $validated = Validator::make($data, [
                    'first_name' => ['required', 'string', 'max:255'],
                    'last_name' => ['required', 'string', 'max:255'],
                    'email' => ['nullable', 'email', 'max:255'],
                    'phone' => ['nullable', 'string', 'max:50'],
                    'notes' => ['nullable', 'string'],
                    'subsidiary_id' => ['required', 'integer', 'exists:subsidiaries,id'],
                ])->validate();

                $fieldsToSave = array_merge($data, $validated);
            }

            unset($fieldsToSave['id'], $fieldsToSave['created_at'], $fieldsToSave['updated_at']);

            unset($fieldsToSave['contact_id']);

            if ($existingContactId === null) {
                $fieldsToSave['display_name'] = trim(($fieldsToSave['first_name'] ?? '').' '.($fieldsToSave['last_name'] ?? ''));

                if ($fieldsToSave['display_name'] === '') {
                    $fieldsToSave['display_name'] = $fieldsToSave['email'] ?? $fieldsToSave['company'] ?? 'Customer';
                }

                $inactive = filter_var($fieldsToSave['inactive'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $fieldsToSave['inactive'] = $inactive;
                $fieldsToSave['status'] = $inactive ? ContactStatus::Inactive->value : ContactStatus::Active->value;
            }

            [$contactData, $addressData, $profileData] = RecordModel::splitPayload($fieldsToSave);

            if (array_key_exists('assigned_user_id', $fieldsToSave)) {
                $profileData['assigned_user_id'] = $fieldsToSave['assigned_user_id'];
            }

            if (auth()->check()) {
                $profileData['created_by_user_id'] = auth()->id();
                $profileData['last_updated_by_user_id'] = auth()->id();
            }

            $record = DB::transaction(function () use ($contactData, $addressData, $profileData, $existingContactId) {
                if ($existingContactId !== null) {
                    $contact = Contact::query()->findOrFail($existingContactId);
                    if ($contactData !== []) {
                        $contact->update($contactData);
                    }
                } else {
                    $contact = Contact::query()->create($contactData);
                }

                $hasAddress = collect($addressData)->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();
                if ($hasAddress) {
                    $primary = $contact->primaryAddress;
                    if ($primary) {
                        $primary->update($addressData);
                    } else {
                        ContactAddress::query()->create(array_merge($addressData, [
                            'contact_id' => $contact->id,
                            'is_primary' => true,
                        ]));
                    }
                }

                $profileData['contact_id'] = $contact->id;

                $customer = RecordModel::query()->create($profileData);
                $contact->update(['stage_id' => ContactStage::Customer]);

                return $customer;
            });

            return [
                'success' => true,
                'record' => $record,
            ];
        } catch (ValidationException $e) {
            throw $e;
        } catch (QueryException $e) {
            Log::error('Database query error in CreateCustomer', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in CreateCustomer', [
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
