<?php
namespace App\Domain\Vendor\Actions;

use App\Domain\Vendor\Models\Vendor as RecordModel;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Throwable;

class UpdateVendor
{
    /**
     * Handle the action.
     *
     * @param  int  $id
     * @param  array  $data
     * @return array
     *
     * @throws ValidationException
     */
    public function __invoke(int $id, array $data): array
    {
        // Validate only fields that have validation rules
        $validated = Validator::make($data, [
            'display_name' => ['required', 'string', 'max:255'],
            'email'      => ['nullable', 'email', 'max:255'],
            'phone'      => ['nullable', 'string', 'max:50'],
            'notes'      => ['nullable', 'string'],
        ])->validate();

        try {
            $vendor = RecordModel::findOrFail($id);
            
            // Merge all data with validated fields (validated fields take precedence)
            // This ensures validated fields use their validated values, while other fields are preserved
            $fieldsToSave = array_merge($data, $validated);
            // Remove fields that shouldn't be mass-assigned
            unset($fieldsToSave['id'], $fieldsToSave['created_at'], $fieldsToSave['updated_at']);

            $vendor->update($fieldsToSave);

            return [
                'success' => true,
                'record' => $vendor,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in UpdateVendor', [
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
            Log::error('Unexpected error in UpdateVendor', [
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