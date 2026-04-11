<?php

declare(strict_types=1);

namespace App\Domain\ContactAddress\Actions;

use App\Domain\ContactAddress\Models\ContactAddress as RecordModel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateContactAddress
{
    /**
     * @return array{success: bool, record?: RecordModel, message?: string}
     */
    public function __invoke(array $data): array
    {
        try {
            $validated = Validator::make($data, [
                'contact_id' => ['required', 'integer', 'exists:contacts,id'],
                'label' => ['nullable', 'string', 'max:255'],
                'is_primary' => ['sometimes', 'boolean'],
                'address_line_1' => ['nullable', 'string', 'max:255'],
                'address_line_2' => ['nullable', 'string', 'max:255'],
                'city' => ['nullable', 'string', 'max:255'],
                'state' => ['nullable', 'string', 'max:255'],
                'postal_code' => ['nullable', 'string', 'max:50'],
                'country' => ['nullable', 'string', 'max:255'],
                'latitude' => ['nullable', 'numeric'],
                'longitude' => ['nullable', 'numeric'],
            ])->validate();

            $wantsPrimary = ! empty($validated['is_primary']);
            unset($validated['is_primary']);

            $record = DB::transaction(function () use ($validated, $wantsPrimary): RecordModel {
                $contactId = (int) $validated['contact_id'];
                $shouldPrimary = $wantsPrimary || ! RecordModel::query()->where('contact_id', $contactId)->exists();

                if ($shouldPrimary) {
                    RecordModel::query()->where('contact_id', $contactId)->update(['is_primary' => false]);
                }

                return RecordModel::create(array_merge($validated, [
                    'is_primary' => $shouldPrimary,
                ]));
            });

            return [
                'success' => true,
                'record' => $record,
            ];
        } catch (ValidationException $e) {
            throw $e;
        } catch (QueryException $e) {
            Log::error('Database query error in CreateContactAddress', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in CreateContactAddress', [
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
