<?php

declare(strict_types=1);

namespace App\Domain\AssetOption\Actions;

use App\Domain\AssetOption\Models\AssetOption as RecordModel;
use App\Domain\AssetOption\Models\AssetOptionValue;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

class UpdateAssetOption
{
    public function __invoke(int $id, array $data): array
    {
        $validated = Validator::make($data, [
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('asset_options', 'slug')->ignore($id)],
            'input_type' => ['sometimes', 'string', Rule::in(['select', 'color', 'multi_select', 'toggle'])],
            'is_required' => ['sometimes', 'boolean'],
            'allow_multiple' => ['sometimes', 'boolean'],
            'min_select' => ['nullable', 'integer', 'min:0'],
            'max_select' => ['nullable', 'integer', 'min:0'],
            'active' => ['sometimes', 'boolean'],
            'values' => ['nullable', 'array'],
            'values.*.id' => ['nullable', 'integer'],
            'values.*.label' => ['required', 'string', 'max:255'],
            'values.*.value' => ['nullable', 'string', 'max:255'],
            'values.*.color_hex' => ['nullable', 'string', 'max:32'],
            'values.*.cost' => ['nullable', 'numeric'],
            'values.*.price' => ['nullable', 'numeric'],
            'values.*.sort_order' => ['nullable', 'integer'],
        ])->validate();

        $valuesInput = $validated['values'] ?? null;
        unset($validated['values']);

        try {
            $record = DB::transaction(function () use ($id, $validated, $valuesInput) {
                $record = RecordModel::findOrFail($id);
                if ($validated !== []) {
                    $record->update($validated);
                }

                if ($valuesInput !== null) {
                    $keepIds = [];
                    foreach ($valuesInput as $index => $row) {
                        $payload = [
                            'label' => $row['label'],
                            'value' => $row['value'] ?? null,
                            'color_hex' => $row['color_hex'] ?? null,
                            'cost' => $row['cost'] ?? null,
                            'price' => $row['price'] ?? null,
                            'sort_order' => isset($row['sort_order']) ? (int) $row['sort_order'] : $index * 10,
                            'active' => true,
                        ];
                        if (! empty($row['id'])) {
                            $value = AssetOptionValue::query()
                                ->where('option_id', $record->id)
                                ->whereKey((int) $row['id'])
                                ->first();
                            if ($value) {
                                $value->update($payload);
                                $keepIds[] = $value->id;
                            }
                        } else {
                            $created = $record->allValues()->create(array_merge($payload, [
                                'is_default' => false,
                            ]));
                            $keepIds[] = $created->id;
                        }
                    }
                    $record->allValues()->whereNotIn('id', $keepIds)->delete();
                }

                return $record->fresh();
            });

            return [
                'success' => true,
                'record' => $record,
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in UpdateAssetOption', [
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
            Log::error('Unexpected error in UpdateAssetOption', [
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
