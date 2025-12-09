<?php
namespace App\Domain\Customer\Actions;

use App\Domain\Customer\Models\Customer as RecordModel;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateCustomer
{
    public function __invoke(array $data): array
    {
        try {
            // Validate only fields that have validation rules
            $validated = Validator::make($data, [
                'first_name' => ['required', 'string', 'max:255'],
                'last_name'  => ['required', 'string', 'max:255'],
                'email'      => ['nullable', 'email', 'max:255'],
                'phone'      => ['nullable', 'string', 'max:50'],
                'notes'      => ['nullable', 'string'],
            ])->validate();

            // Merge all data with validated fields (validated fields take precedence)
            // This ensures validated fields use their validated values, while other fields are preserved
            $fieldsToSave = array_merge($data, $validated);
            // Remove fields that shouldn't be mass-assigned
            unset($fieldsToSave['id'], $fieldsToSave['created_at'], $fieldsToSave['updated_at']);
            $fieldsToSave['display_name'] = $fieldsToSave['first_name'] . ' ' . $fieldsToSave['last_name'];

            // Create the customer in the tenant database
            $record = RecordModel::create($fieldsToSave);

            return [
                'success' => true,
                'record' => $record,
            ];
        } catch (ValidationException $e) {
            // Re-throw validation exceptions so Laravel can handle them properly
            throw $e;
        } catch (QueryException $e) {
            Log::error('Database query error in CreateCustomer', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in CreateCustomer', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        }
    }
}
