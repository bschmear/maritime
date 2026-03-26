<?php

declare(strict_types=1);

namespace App\Domain\Checklist\Actions;

use App\Domain\BoatShowEvent\Models\BoatShowEvent;
use App\Domain\Checklist\Models\Checklist;
use App\Domain\Checklist\Models\ChecklistItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SyncChecklist
{
    /** @var array<int, class-string> */
    private const ALLOWED_CHECKLISTABLE = [
        BoatShowEvent::class,
    ];

    /**
     * @param  array{name?: string, checklist_template_id?: int|null, items?: array<int, array<string, mixed>>}  $data
     * @return array{success: bool, checklist?: array<string, mixed>, message?: string}
     */
    public function __invoke(string $checklistableType, int $checklistableId, array $data): array
    {
        if (! in_array($checklistableType, self::ALLOWED_CHECKLISTABLE, true)) {
            throw ValidationException::withMessages([
                'checklistable_type' => ['Checklist is not enabled for this record type.'],
            ]);
        }

        $validated = Validator::make($data, [
            'name' => 'required|string|max:255',
            'checklist_template_id' => 'nullable|integer|exists:checklist_templates,id',
            'items' => 'present|array',
            'items.*.id' => 'nullable|integer',
            'items.*.label' => 'nullable|string|max:500',
            'items.*.completed' => 'sometimes|boolean',
            'items.*.required' => 'sometimes|boolean',
        ])->validate();

        return DB::transaction(function () use ($checklistableType, $checklistableId, $validated) {
            $checklist = Checklist::query()->firstOrCreate(
                [
                    'checklistable_type' => $checklistableType,
                    'checklistable_id' => $checklistableId,
                ],
                [
                    'name' => $validated['name'],
                    'checklist_template_id' => $validated['checklist_template_id'] ?? null,
                ]
            );

            $checklist->update([
                'name' => $validated['name'],
                'checklist_template_id' => array_key_exists('checklist_template_id', $validated)
                    ? $validated['checklist_template_id']
                    : $checklist->checklist_template_id,
            ]);

            $keptIds = [];
            foreach (array_values($validated['items']) as $position => $itemData) {
                $label = trim((string) ($itemData['label'] ?? ''));
                if ($label === '') {
                    continue;
                }

                $completed = (bool) ($itemData['completed'] ?? false);
                $required = (bool) ($itemData['required'] ?? false);
                $itemId = $itemData['id'] ?? null;

                if ($itemId) {
                    $item = ChecklistItem::query()
                        ->where('checklist_id', $checklist->id)
                        ->whereKey($itemId)
                        ->first();

                    if ($item) {
                        $payload = [
                            'label' => $label,
                            'required' => $required,
                            'position' => $position,
                            'completed' => $completed,
                        ];
                        if ($completed) {
                            $payload['completed_at'] = $item->completed_at ?? now();
                            $payload['completed_by'] = $item->completed_by ?? auth()->id();
                        } else {
                            $payload['completed_at'] = null;
                            $payload['completed_by'] = null;
                        }
                        $item->update($payload);
                        $keptIds[] = $item->id;
                    }
                } else {
                    $item = $checklist->items()->create([
                        'label' => $label,
                        'required' => $required,
                        'position' => $position,
                        'completed' => $completed,
                        'completed_at' => $completed ? now() : null,
                        'completed_by' => $completed ? auth()->id() : null,
                    ]);
                    $keptIds[] = $item->id;
                }
            }

            ChecklistItem::query()
                ->where('checklist_id', $checklist->id)
                ->whereNotIn('id', $keptIds)
                ->delete();

            $checklist->load(['items' => fn ($q) => $q->orderBy('position')]);

            return [
                'success' => true,
                'checklist' => self::formatForFrontend($checklist),
            ];
        });
    }

    /**
     * @return array{id: int, name: string, checklist_template_id: int|null, items: list<array<string, mixed>>}
     */
    public static function formatForFrontend(Checklist $checklist): array
    {
        return [
            'id' => $checklist->id,
            'name' => $checklist->name,
            'checklist_template_id' => $checklist->checklist_template_id,
            'items' => $checklist->items->map(fn (ChecklistItem $i) => [
                'id' => $i->id,
                'label' => $i->label,
                'completed' => (bool) $i->completed,
                'required' => (bool) $i->required,
                'position' => $i->position,
            ])->values()->all(),
        ];
    }
}
