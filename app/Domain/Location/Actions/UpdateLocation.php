<?php
namespace App\Domain\Location\Actions;

use App\Domain\Location\Models\Location as RecordModel;
use App\Domain\SystemLog\Support\LogSystemEvent;
use App\Enums\Locations\LocationType;
use App\Enums\System\SystemLogAction;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Illuminate\Validation\Rule;
use Throwable;

class UpdateLocation
{
    public function __invoke(int $id, array $data): array
    {
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


        try {
            $record = RecordModel::findOrFail($id);
            $previousApproverId = $record->delivery_approver_user_id;
            $previousManagerId = $record->manager_user_id;
            $fieldsToSave = array_merge($data, $validated);

            // Remove fields that shouldn't be mass-assigned
            unset($fieldsToSave['id'], $fieldsToSave['created_at'], $fieldsToSave['updated_at']);

            $fieldsToSave['updated_by_id'] = current_tenant_user_id();

            $record->update($fieldsToSave);

            $approverChanged = array_key_exists('delivery_approver_user_id', $fieldsToSave)
                && (int) ($fieldsToSave['delivery_approver_user_id'] ?? 0) !== (int) ($previousApproverId ?? 0);
            $managerChanged = array_key_exists('manager_user_id', $fieldsToSave)
                && (int) ($fieldsToSave['manager_user_id'] ?? 0) !== (int) ($previousManagerId ?? 0);
            if ($approverChanged || $managerChanged) {
                LogSystemEvent::record($record->fresh(), SystemLogAction::Updated);
            }

            return [
                'success' => true,
                'record' => $record,
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in UpdateLocation', [
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
            Log::error('Unexpected error in UpdateLocation', [
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