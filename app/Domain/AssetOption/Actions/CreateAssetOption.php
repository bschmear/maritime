<?php

declare(strict_types=1);

namespace App\Domain\AssetOption\Actions;

use App\Domain\AssetOption\Models\AssetOption as RecordModel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class CreateAssetOption
{
    public function __invoke(array $data): array
    {
        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('asset_options', 'slug')],
            'input_type' => ['required', 'string', Rule::in(['select', 'color', 'multi_select', 'toggle'])],
            'is_required' => ['sometimes', 'boolean'],
            'allow_multiple' => ['sometimes', 'boolean'],
            'min_select' => ['nullable', 'integer', 'min:0'],
            'max_select' => ['nullable', 'integer', 'min:0'],
            'active' => ['sometimes', 'boolean'],
        ])->validate();

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $baseSlug = $validated['slug'];
        $suffix = 1;
        while (RecordModel::query()->where('slug', $validated['slug'])->exists()) {
            $validated['slug'] = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        try {
            $record = RecordModel::create($validated);

            return [
                'success' => true,
                'record' => $record,
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in CreateAssetOption', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in CreateAssetOption', [
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
