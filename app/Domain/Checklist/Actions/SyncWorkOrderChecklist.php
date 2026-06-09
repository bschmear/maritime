<?php

declare(strict_types=1);

namespace App\Domain\Checklist\Actions;

use App\Domain\Checklist\Models\Checklist;
use App\Domain\Checklist\Models\ChecklistItem;
use App\Domain\WorkOrder\Models\WorkOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SyncWorkOrderChecklist
{
    /**
     * @param  array{name?: string, checklist_template_id?: int|null, items?: array<int, array<string, mixed>>}  $data
     * @return array{success: bool, checklist?: array<string, mixed>, message?: string}
     */
    public function __invoke(WorkOrder $workOrder, array $data): array
    {
        if ($workOrder->manager_signed_off_at) {
            return [
                'success' => false,
                'message' => 'Checklist is locked after manager sign-off.',
            ];
        }

        $validated = Validator::make($data, [
            'name' => 'required|string|max:255',
            'checklist_template_id' => 'nullable|integer|exists:checklist_templates,id',
            'items' => 'present|array',
            'items.*.id' => 'nullable|integer',
            'items.*.label' => 'nullable|string|max:500',
            'items.*.required' => 'sometimes|boolean',
            'items.*.response' => ['nullable', 'string', Rule::in(['true', 'false', 'na'])],
            'items.*.manager_approved' => 'sometimes|boolean',
        ])->validate();

        return DB::transaction(function () use ($workOrder, $validated) {
            $existingChecklist = Checklist::query()
                ->where('checklistable_type', WorkOrder::class)
                ->where('checklistable_id', $workOrder->id)
                ->with(['items' => fn ($q) => $q->orderBy('position')])
                ->first();

            if ($existingChecklist && ! self::canManageChecklistStructure()) {
                self::assertEmployeeStructureUnchanged($existingChecklist, $validated['items']);
            }

            $checklist = Checklist::query()->firstOrCreate(
                [
                    'checklistable_type' => WorkOrder::class,
                    'checklistable_id' => $workOrder->id,
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

                $required = (bool) ($itemData['required'] ?? false);
                $response = isset($itemData['response']) && $itemData['response'] !== ''
                    ? (string) $itemData['response']
                    : null;
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
                            'response' => $response,
                        ];

                        if ($response !== $item->response) {
                            $payload['manager_approved'] = false;
                            $payload['manager_approved_at'] = null;
                            $payload['manager_approved_by'] = null;
                        }

                        $item->update($payload);
                        $keptIds[] = $item->id;
                    }
                } else {
                    $item = $checklist->items()->create([
                        'label' => $label,
                        'required' => $required,
                        'position' => $position,
                        'response' => $response,
                        'completed' => false,
                        'manager_approved' => false,
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
    public static function canManageChecklistStructure(): bool
    {
        $slug = current_tenant_role_slug();

        return in_array($slug, ['admin', 'manager'], true);
    }

    /**
     * @param  list<array<string, mixed>>  $incomingItems
     */
    private static function assertEmployeeStructureUnchanged(Checklist $checklist, array $incomingItems): void
    {
        $existingById = $checklist->items->keyBy('id');
        $incomingWithIds = collect($incomingItems)->filter(fn ($item) => ! empty($item['id']));

        if ($incomingWithIds->count() !== $existingById->count()) {
            throw ValidationException::withMessages([
                'checklist' => ['Only administrators and managers can add or remove checklist items.'],
            ]);
        }

        foreach ($incomingWithIds as $itemData) {
            $existing = $existingById->get((int) $itemData['id']);
            if (! $existing) {
                throw ValidationException::withMessages([
                    'checklist' => ['Only administrators and managers can add or remove checklist items.'],
                ]);
            }

            $label = trim((string) ($itemData['label'] ?? ''));
            $required = (bool) ($itemData['required'] ?? false);

            if ($label !== $existing->label || $required !== (bool) $existing->required) {
                throw ValidationException::withMessages([
                    'checklist' => ['Only administrators and managers can edit checklist item labels.'],
                ]);
            }
        }
    }

    public static function formatForFrontend(Checklist $checklist): array
    {
        return [
            'id' => $checklist->id,
            'name' => $checklist->name,
            'checklist_template_id' => $checklist->checklist_template_id,
            'items' => $checklist->items->map(fn (ChecklistItem $i) => [
                'id' => $i->id,
                'label' => $i->label,
                'required' => (bool) $i->required,
                'response' => $i->response,
                'manager_approved' => (bool) $i->manager_approved,
                'manager_approved_at' => $i->manager_approved_at?->toISOString(),
                'manager_approved_by' => $i->manager_approved_by,
                'position' => $i->position,
            ])->values()->all(),
        ];
    }
}
