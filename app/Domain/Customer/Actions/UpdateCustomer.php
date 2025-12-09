<?php
namespace App\Domain\Customer\Actions;

use App\Domain\Customer\Models\Customer as RecordModel;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Throwable;

class UpdateCustomer
{
    public function __invoke(int $id, array $data): array
    {
        // Validate only fields that have validation rules
        $validated = Validator::make($data, [
            'first_name' => ['sometimes', 'required', 'string', 'max:255'],
            'last_name'  => ['sometimes', 'required', 'string', 'max:255'],
            'email'      => ['nullable', 'email', 'max:255'],
            'phone'      => ['nullable', 'string', 'max:50'],
            'notes'      => ['nullable', 'string'],
        ])->validate();

        try {
            $customer = RecordModel::findOrFail($id);
            
            // Merge all data with validated fields (validated fields take precedence)
            // This ensures validated fields use their validated values, while other fields are preserved
            $fieldsToSave = array_merge($data, $validated);
            // Remove fields that shouldn't be mass-assigned
            unset($fieldsToSave['id'], $fieldsToSave['created_at'], $fieldsToSave['updated_at']);
            $fieldsToSave['display_name'] = $fieldsToSave['first_name'] . ' ' . $fieldsToSave['last_name'];
            $customer->update($fieldsToSave);

            return [
                'success' => true,
                'record' => $customer,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in UpdateCustomer', [
                'error' => $e->getMessage(),
                'id' => $id,
                'data' => $data
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in UpdateCustomer', [
                'error' => $e->getMessage(),
                'id' => $id,
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