<?php
namespace App\Domain\AddOn\Actions;

use App\Domain\AddOn\Models\AddOn as RecordModel;
use App\Enums\Transaction\AddOnType;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Throwable;

class UpdateAddOn
{
    public function __invoke(int $id, array $data): array
    {
        $validated = Validator::make($data, [
            'name' => 'required|string|max:255',
            'default_price' => 'required|numeric',
            'type' => ['nullable', Rule::enum(AddOnType::class)],
            'description' => 'nullable|string',
        ])->validate();

        try {
            $record = RecordModel::findOrFail($id);
            $record->update($validated);

            return [
                'success' => true,
                'record' => $record,
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in UpdateAddOn', [
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
            Log::error('Unexpected error in UpdateAddOn', [
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