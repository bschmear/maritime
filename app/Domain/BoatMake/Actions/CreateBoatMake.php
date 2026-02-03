<?php
namespace App\Domain\BoatMake\Actions;

use App\Domain\BoatMake\Models\BoatMake as RecordModel;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Database\QueryException;
use Throwable;

class CreateBoatMake
{
    public function __invoke(array $data): array
    {
        // Validate input
        $validated = Validator::make($data, [
            'display_name' => ['required', 'string', 'max:255'],
            'is_custom' => ['sometimes', 'boolean'],
            'logo' => ['sometimes', 'nullable', 'string'],
            'active' => ['sometimes', 'boolean'],
        ])->validate();

        // Generate unique slug from title
        $slug = Str::slug($validated['display_name']);
        $originalSlug = $slug;
        $counter = 1;

        while (RecordModel::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        $validated['slug'] = $slug;

        try {
            $record = RecordModel::create($validated);

            return [
                'success' => true,
                'record' => $record,
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in CreateBoatMake', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in CreateBoatMake', [
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