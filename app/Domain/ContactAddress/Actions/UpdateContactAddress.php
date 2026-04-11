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

class UpdateContactAddress
{
    /**
     * @return array{success: bool, record?: RecordModel, message?: string}
     */
    public function __invoke(int $id, array $data): array
    {
        try {
            $record = RecordModel::findOrFail($id);

            $validated = Validator::make($data, [
                'contact_id' => ['sometimes', 'integer', 'exists:contacts,id'],
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

            $wantsPrimary = array_key_exists('is_primary', $validated)
                ? (bool) $validated['is_primary']
                : null;

            DB::transaction(function () use ($record, $validated, $wantsPrimary): void {
                $contactId = (int) ($validated['contact_id'] ?? $record->contact_id);

                if ($wantsPrimary === true) {
                    RecordModel::query()
                        ->where('contact_id', $contactId)
                        ->where('id', '!=', $record->id)
                        ->update(['is_primary' => false]);
                }

                if ($wantsPrimary !== null) {
                    $validated['is_primary'] = $wantsPrimary;
                }

                $record->fill($validated);
                $record->save();
            });

            return [
                'success' => true,
                'record' => $record->fresh(),
            ];
        } catch (ValidationException $e) {
            throw $e;
        } catch (QueryException $e) {
            Log::error('Database query error in UpdateContactAddress', [
                'error' => $e->getMessage(),
                'id' => $id,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in UpdateContactAddress', [
                'error' => $e->getMessage(),
                'id' => $id,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
