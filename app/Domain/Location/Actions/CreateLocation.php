<?php
namespace App\Domain\Location\Actions;

use App\Domain\Location\Models\Location as RecordModel;
use App\Enums\Locations\LocationType;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Illuminate\Validation\Rule;
use Throwable;

class CreateLocation
{
    public function __invoke(array $data): array
    {

        try {
            $locationTypeIds = array_map(static fn (array $option) => (int) $option['id'], LocationType::options());

            $validated = Validator::make($data, [
                'display_name' => ['nullable', 'string', 'max:255'],
                'location_type' => ['nullable', 'integer', Rule::in($locationTypeIds)],
                'email' => ['nullable', 'email', 'max:255'],
                'phone' => ['nullable', 'string', 'max:50'],
                'notes' => ['nullable', 'string'],
                'manager_user_id' => ['nullable', 'integer', 'exists:users,id'],
                'delivery_approver_user_id' => ['nullable', 'integer', 'exists:users,id'],
            ])->validate();

            $fieldsToSave = array_merge($data, $validated);

            // Remove fields that shouldn't be mass-assigned
            unset($fieldsToSave['id'], $fieldsToSave['created_at'], $fieldsToSave['updated_at']);
            $fieldsToSave['created_by_id'] = current_tenant_user_id();
            // Create the lead in the tenant database
            $record = RecordModel::create($fieldsToSave);

            return [
                'success' => true,
                'record' => $record,
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in CreateLocation', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in CreateLocation', [
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