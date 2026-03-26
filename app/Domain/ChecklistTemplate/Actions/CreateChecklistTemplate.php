<?php

declare(strict_types=1);

namespace App\Domain\ChecklistTemplate\Actions;

use App\Domain\ChecklistTemplate\Models\ChecklistTemplate;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class CreateChecklistTemplate
{
    /**
     * @param  array{name: string, context?: string|null, items: list<array{label: string, required?: bool}>}  $data
     * @return array{success: bool, record?: ChecklistTemplate, message?: string}
     */
    public function __invoke(array $data): array
    {
        $validated = Validator::make($data, [
            'name' => 'required|string|max:255',
            'context' => 'nullable|string|max:100',
            'items' => 'required|array|min:1',
            'items.*.label' => 'required|string|max:500',
            'items.*.required' => 'sometimes|boolean',
        ])->validate();

        try {
            $record = DB::transaction(function () use ($validated) {
                $template = ChecklistTemplate::create([
                    'name' => $validated['name'],
                    'context' => $validated['context'] ?? null,
                    'is_active' => true,
                ]);

                foreach (array_values($validated['items']) as $position => $row) {
                    $template->items()->create([
                        'label' => $row['label'],
                        'required' => (bool) ($row['required'] ?? false),
                        'position' => $position,
                    ]);
                }

                return $template->load('items');
            });

            return [
                'success' => true,
                'record' => $record,
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in CreateChecklistTemplate', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in CreateChecklistTemplate', [
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
