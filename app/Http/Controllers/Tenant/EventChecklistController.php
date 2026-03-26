<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\BoatShowEvent\Models\BoatShowEvent;
use App\Domain\Checklist\Actions\SyncChecklist;
use App\Domain\ChecklistTemplate\Actions\CreateChecklistTemplate;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventChecklistController extends Controller
{
    /**
     * Sync checklist + items for a boat show event (standalone or nested route).
     */
    public function updateBoatShowEvent(Request $request): JsonResponse
    {
        $event = BoatShowEvent::query()->findOrFail((int) $request->route('event'));

        $result = (new SyncChecklist)(
            BoatShowEvent::class,
            $event->id,
            $request->only(['name', 'checklist_template_id', 'items'])
        );

        return response()->json($result);
    }

    /**
     * Save current checklist shape as a reusable template.
     */
    public function storeTemplate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'context' => 'nullable|string|max:100',
            'items' => 'required|array|min:1',
            'items.*.label' => 'required|string|max:500',
            'items.*.required' => 'sometimes|boolean',
        ]);

        $validated['context'] = $validated['context'] ?? 'boat_show_event';

        $result = (new CreateChecklistTemplate)($validated);

        if (! $result['success']) {
            return response()->json($result, 422);
        }

        return response()->json([
            'success' => true,
            'template' => [
                'id' => $result['record']->id,
                'name' => $result['record']->name,
                'items' => $result['record']->items->map(fn ($i) => [
                    'label' => $i->label,
                    'required' => $i->required,
                ])->values()->all(),
            ],
        ]);
    }
}
