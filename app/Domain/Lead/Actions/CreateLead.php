<?php

namespace App\Domain\Lead\Actions;

use App\Domain\Contact\Models\Contact;
use App\Domain\Contact\Models\ContactAddress;
use App\Domain\Integration\Support\QuickBooksSettings;
use App\Domain\Lead\Models\Lead as RecordModel;
use App\Domain\SystemLog\Support\LogSystemEvent;
use App\Enums\Entity\ContactStage;
use App\Enums\Entity\ContactStatus;
use App\Enums\Entity\ContactType;
use App\Enums\System\SystemLogAction;
use App\Jobs\PushContactToQuickBooks;
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
            $forImport = filter_var($data['for_import'] ?? false, FILTER_VALIDATE_BOOLEAN);
            unset($data['for_import']);

            $systemLogActor = trim((string) ($data['system_log_actor'] ?? '')) ?: null;
            unset($data['system_log_actor']);

            $nameRules = $forImport
                ? ['nullable', 'string', 'max:255']
                : ['required', 'string', 'max:255'];

            $validated = Validator::make($data, [
                'first_name' => $nameRules,
                'last_name' => $nameRules,
                'email' => ['nullable', 'email', 'max:255'],
                'phone' => ['nullable', 'string', 'max:50'],
                'mobile' => ['nullable', 'string', 'max:50'],
                'notes' => ['nullable', 'string'],
            ])->validate();

            $fieldsToSave = array_merge($data, $validated);
            unset($fieldsToSave['id'], $fieldsToSave['created_at'], $fieldsToSave['updated_at']);

            if (empty($fieldsToSave['display_name'])) {
                $fieldsToSave['display_name'] = trim(($fieldsToSave['first_name'] ?? '').' '.($fieldsToSave['last_name'] ?? ''));
            }

            if (empty($fieldsToSave['display_name'])) {
                $fieldsToSave['display_name'] = $fieldsToSave['email'] ?? $fieldsToSave['company'] ?? 'Lead';
            }

            $inactive = filter_var($fieldsToSave['inactive'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $fieldsToSave['inactive'] = $inactive;
            $fieldsToSave['status'] = $inactive
                ? (string) ContactStatus::Inactive->id()
                : (string) ContactStatus::Active->id();

            [$contactData, $addressData, $profileData] = RecordModel::splitPayload($fieldsToSave);

            if (array_key_exists('source_id', $contactData)) {
                $profileData['source_id'] = $contactData['source_id'];
            }

            if (array_key_exists('assigned_user_id', $fieldsToSave)) {
                $profileData['assigned_user_id'] = $fieldsToSave['assigned_user_id'];
            }

            $profileData['contact_id'] = null;
            $tenantUserId = current_tenant_user_id();
            if ($tenantUserId !== null) {
                $profileData['created_by_user_id'] = $tenantUserId;
                $profileData['last_updated_by_user_id'] = $tenantUserId;
            }

            $record = DB::transaction(function () use ($contactData, $addressData, $profileData) {
                /** @var Contact $contact */
                $contact = Contact::query()->create(array_merge($contactData, [
                    'type' => (string) ContactType::Person->id(),
                ]));
                $contact->update(['stage_id' => ContactStage::Lead->id()]);

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

            LogSystemEvent::record($record, SystemLogAction::Created, $systemLogActor);

            if ($record->contact_id && QuickBooksSettings::forCurrentTenant()->isSyncContactsEnabled()) {
                $contact = Contact::query()->find($record->contact_id);
                if ($contact !== null && ! $contact->quickbooks_customer_id) {
                    PushContactToQuickBooks::dispatch($contact->id);
                }
            }

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
