<?php

namespace App\Domain\Lead\Actions;

use App\Domain\Contact\Models\Contact;
use App\Domain\Contact\Models\ContactAddress;
use App\Domain\Lead\Models\Lead as RecordModel;
use App\Enums\Entity\ContactStage;
use App\Enums\Entity\ContactStatus;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateLead
{
    public function __invoke(array $data): array
    {
        try {
            $validated = Validator::make($data, [
                'first_name' => ['required', 'string', 'max:255'],
                'last_name' => ['required', 'string', 'max:255'],
                'email' => ['nullable', 'email', 'max:255'],
                'phone' => ['nullable', 'string', 'max:50'],
                'notes' => ['nullable', 'string'],
            ])->validate();

            $fieldsToSave = array_merge($data, $validated);
            unset($fieldsToSave['id'], $fieldsToSave['created_at'], $fieldsToSave['updated_at']);

            $fieldsToSave['display_name'] = trim(($fieldsToSave['first_name'] ?? '').' '.($fieldsToSave['last_name'] ?? ''));

            if (empty($fieldsToSave['display_name'])) {
                $fieldsToSave['display_name'] = $fieldsToSave['email'] ?? $fieldsToSave['company'] ?? 'Lead';
            }

            $inactive = filter_var($fieldsToSave['inactive'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $fieldsToSave['inactive'] = $inactive;
            $fieldsToSave['status'] = $inactive ? ContactStatus::Inactive->value : ContactStatus::Active->value;

            [$contactData, $addressData, $profileData] = RecordModel::splitPayload($fieldsToSave);

            if (array_key_exists('assigned_user_id', $fieldsToSave)) {
                $profileData['assigned_user_id'] = $fieldsToSave['assigned_user_id'];
            }

            $profileData['contact_id'] = null;
            if (auth()->check()) {
                $profileData['created_by_user_id'] = auth()->id();
                $profileData['last_updated_by_user_id'] = auth()->id();
            }

            $record = DB::transaction(function () use ($contactData, $addressData, $profileData) {
                /** @var \App\Domain\Contact\Models\Contact $contact */
                $contact = Contact::query()->create($contactData);
                $contact->update(['stage_id' => ContactStage::Lead]);

                $hasAddress = collect($addressData)->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();
                if ($hasAddress) {
                    ContactAddress::query()->create(array_merge($addressData, [
                        'contact_id' => $contact->id,
                        'is_primary' => true,
                    ]));
                }

                $profileData['contact_id'] = $contact->id;

                return RecordModel::query()->create($profileData);
            });

            return [
                'success' => true,
                'record' => $record,
            ];
        } catch (ValidationException $e) {
            throw $e;
        } catch (QueryException $e) {
            Log::error('Database query error in CreateLead', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in CreateLead', [
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
