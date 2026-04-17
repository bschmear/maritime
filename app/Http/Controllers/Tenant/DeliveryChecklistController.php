<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Delivery\Models\Delivery;
use App\Domain\DeliveryChecklistCategory\Models\DeliveryChecklistCategory;
use App\Domain\DeliveryChecklistItem\Models\DeliveryChecklistItem;
use App\Domain\DeliveryChecklistTemplate\Models\DeliveryChecklistTemplate;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DeliveryChecklistController extends Controller
{
    /**
     * Get checklist for a delivery
     */
    public function index($deliveryId)
    {
        $delivery = Delivery::findOrFail($deliveryId);

        return $delivery->checklistItems()
            ->with('completedBy')
            ->orderBy('sort_order')
            ->orderBy('category_id')
            ->get();
    }

    /**
     * Add checklist items from template or create from scratch
     */
    public function store(Request $request, $deliveryId)
    {
        $delivery = Delivery::findOrFail($deliveryId);

        $validated = $request->validate([
            'template_id' => 'nullable|exists:delivery_checklist_templates,id',
            'items' => 'nullable|array',
            'items.*.label' => 'required|string|max:255',
            'items.*.category' => 'required|string',
            'items.*.is_required' => 'boolean',
        ]);

        if ($request->template_id) {
            $template = DeliveryChecklistTemplate::with('items')->findOrFail($request->template_id);

            foreach ($template->items as $templateItem) {
                $delivery->checklistItems()->create([
                    'template_item_id' => $templateItem->id,
                    'label' => $templateItem->label,
                    'category_id' => $templateItem->category_id,
                    'is_required' => $templateItem->is_required,
                    'sort_order' => $templateItem->sort_order,
                ]);
            }
        } elseif ($request->items) {
            foreach ($request->items as $itemData) {
                $category = DeliveryChecklistCategory::firstOrCreate([
                    'name' => $itemData['category'],
                ], [
                    'color' => 'blue',
                ]);

                $delivery->checklistItems()->create([
                    'label' => $itemData['label'],
                    'category_id' => $category->id,
                    'is_required' => $itemData['is_required'] ?? false,
                    'sort_order' => $itemData['sort_order'] ?? 0,
                ]);
            }
        }

        return response()->json(['message' => 'Checklist items added']);
    }

    /**
     * Add a single checklist item
     */
    public function addItem(Request $request, $deliveryId)
    {
        $delivery = Delivery::findOrFail($deliveryId);

        $validated = $request->validate([
            'label' => 'required|string|max:255',
            'category' => 'required|string',
            'is_required' => 'boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $category = DeliveryChecklistCategory::firstOrCreate([
            'name' => $validated['category'],
        ], [
            'color' => 'blue',
        ]);

        $sortOrder = $validated['sort_order'] ?? $delivery->checklistItems()
            ->where('category_id', $category->id)
            ->max('sort_order') + 1;

        $item = $delivery->checklistItems()->create([
            'label' => $validated['label'],
            'category_id' => $category->id,
            'is_required' => $validated['is_required'],
            'sort_order' => $sortOrder,
        ]);

        return response()->json($item->load('category'), 201);
    }

    /**
     * Update a checklist item
     */
    public function updateItem(Request $request, $deliveryId, $itemId)
    {
        $delivery = Delivery::findOrFail($deliveryId);
        $item = DeliveryChecklistItem::findOrFail($itemId);

        if ($item->delivery_id !== $delivery->id) {
            abort(403, 'Item does not belong to this delivery');
        }

        $validated = $request->validate([
            'completed' => 'sometimes|boolean',
            'label' => 'sometimes|required|string|max:255',
            'category' => 'sometimes|required|string',
            'is_required' => 'sometimes|boolean',
        ]);

        $updates = [];

        if ($request->has('label')) {
            $updates['label'] = $validated['label'];
        }

        if ($request->has('is_required')) {
            $updates['is_required'] = (bool) $validated['is_required'];
        }

        if ($request->has('category')) {
            $category = DeliveryChecklistCategory::firstOrCreate([
                'name' => $validated['category'],
            ], [
                'color' => 'blue',
            ]);
            $updates['category_id'] = $category->id;
        }

        if ($request->has('completed')) {
            $completed = (bool) $request->completed;
            $updates['completed'] = $completed;
            if ($completed && ! $item->completed) {
                $updates['completed_at'] = now();
                $updates['completed_by'] = auth()->id();
            } elseif (! $completed) {
                $updates['completed_at'] = null;
                $updates['completed_by'] = null;
            }
        }

        $item->update($updates);

        return response()->json($item->load(['completedBy', 'category']));
    }

    /**
     * Remove a checklist item
     */
    public function removeItem($deliveryId, $itemId)
    {
        $delivery = Delivery::findOrFail($deliveryId);
        $item = DeliveryChecklistItem::findOrFail($itemId);

        if ($item->delivery_id !== $delivery->id) {
            abort(403, 'Item does not belong to this delivery');
        }

        $item->delete();

        return response()->json(['message' => 'Item removed']);
    }
}
