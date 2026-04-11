<?php

namespace App\Domain\Lead\Actions;

use App\Domain\Contact\Models\ContactAddress;
use App\Domain\Lead\Models\Lead as RecordModel;
use App\Enums\Entity\ContactStatus;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class UpdateLead
{
    /**
     * @return array{success: bool, record: ?RecordModel, message?: string}
     *
     * @throws ValidationException
     */
    public function __invoke(int $id, array $data): array
    {
        $validated = Validator::make($data, [
            'first_name' => ['sometimes', 'required', 'string', 'max:255'],
            'last_name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
        ])->validate();

        try {
            $lead = RecordModel::query()->with(['contact.primaryAddress'])->findOrFail($id);

            $fieldsToSave = array_merge($data, $validated);
            unset($fieldsToSave['id'], $fieldsToSave['created_at'], $fieldsToSave['updated_at']);

            if (isset($fieldsToSave['first_name']) || isset($fieldsToSave['last_name'])) {
                $fn = $fieldsToSave['first_name'] ?? $lead->contact?->first_name ?? '';
                $ln = $fieldsToSave['last_name'] ?? $lead->contact?->last_name ?? '';
                $fieldsToSave['display_name'] = trim($fn.' '.$ln);
            }

            if (array_key_exists('inactive', $fieldsToSave)) {
                $inactive = filter_var($fieldsToSave['inactive'], FILTER_VALIDATE_BOOLEAN);
                $fieldsToSave['inactive'] = $inactive;
                $fieldsToSave['status'] = $inactive ? ContactStatus::Inactive->value : ContactStatus::Active->value;
            }

            [$contactData, $addressData, $profileData] = RecordModel::splitPayload($fieldsToSave);

            if (array_key_exists('assigned_user_id', $fieldsToSave)) {
                $profileData['assigned_user_id'] = $fieldsToSave['assigned_user_id'];
            }

            if (auth()->check()) {
                $profileData['last_updated_by_user_id'] = auth()->id();
            }

            DB::transaction(function () use ($lead, $contactData, $addressData, $profileData) {
                if ($lead->contact && $contactData !== []) {
                    $lead->contact->update($contactData);
                }

                $hasAddress = collect($addressData)->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();
                if ($hasAddress && $lead->contact) {
                    $primary = $lead->contact->primaryAddress;
                    if ($primary) {
                        $primary->update($addressData);
                    } else {
                        ContactAddress::query()->create(array_merge($addressData, [
                            'contact_id' => $lead->contact->id,
                            'is_primary' => true,
                        ]));
                    }
                }

                if ($profileData !== []) {
                    $lead->update($profileData);
                }
            });

            $lead->refresh();

            return [
                'success' => true,
                'record' => $lead,
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in UpdateLead', [
                'error' => $e->getMessage(),
                'id' => $id,
                'data' => $data,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in UpdateLead', [
                'error' => $e->getMessage(),
                'id' => $id,
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
