<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Domain\DeliveryChecklistTemplate\Models\DeliveryChecklistTemplate;
use App\Domain\DeliveryChecklistTemplate\Models\DeliveryChecklistTemplateItem;
use App\Domain\DeliveryChecklistCategory\Models\DeliveryChecklistCategory;
use Illuminate\Http\Request;

class DeliveryChecklistTemplateController extends Controller
{
    public function index(Request $request)
    {
        $templates = DeliveryChecklistTemplate::with('items')->latest()->get();

        return inertia('Tenant/Delivery/ChecklistTemplates/Index', [
            'templates' => $templates,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'is_default' => 'boolean',
        ]);

        $template = DeliveryChecklistTemplate::create($validated);

        return redirect()->route('delivery-checklist-templates.show', $template->id);
    }

    public function show($id)
    {
        // Ensure default categories exist
        $this->ensureDefaultCategories();

        $template = DeliveryChecklistTemplate::with(['items.category'])->findOrFail($id);
        $categories = DeliveryChecklistCategory::orderBy('name')->get();

        return inertia('Tenant/Delivery/ChecklistTemplates/Show', [
            'template' => $template,
            'categories' => $categories,
        ]);
    }

    public function update(Request $request, $id)
    {
        $template = DeliveryChecklistTemplate::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'is_default' => 'boolean',
        ]);

        $template->update($validated);

        return response()->json($template->load('items'));
    }

    public function destroy($id)
    {
        $template = DeliveryChecklistTemplate::findOrFail($id);
        $template->delete();

        return response()->json(['message' => 'Template deleted']);
    }

    /*
    |--------------------------------------------------------------------------
    | Template Items
    |--------------------------------------------------------------------------
    */

    public function addItem(Request $request, $templateId)
    {
        $template = DeliveryChecklistTemplate::findOrFail($templateId);

        $validated = $request->validate([
            'label' => 'required|string|max:255',
            'category_id' => 'nullable|exists:delivery_checklist_categories,id',
            'new_category_name' => 'nullable|string|max:255',
            'is_required' => 'boolean',
        ]);

        $categoryId = $validated['category_id'] ?? null;
        $newCategoryName = $validated['new_category_name'] ?? null;

        if (!$categoryId && !$newCategoryName) {
            return response()->json(['message' => 'Either select an existing category or provide a new category name.'], 422);
        }

        if ($categoryId && $newCategoryName) {
            return response()->json(['message' => 'Please select either an existing category OR create a new one, not both.'], 422);
        }

        if (!$categoryId && $newCategoryName) {
            $category = DeliveryChecklistCategory::firstOrCreate([
                'name' => $newCategoryName
            ], [
                'color' => 'blue',
            ]);
            $categoryId = $category->id;
        }

        $item = $template->items()->create([
            'label' => $validated['label'],
            'category_id' => $categoryId,
            'is_required' => $validated['is_required'],
            'sort_order' => $template->items()->where('category_id', $categoryId)->max('sort_order') + 1,
        ]);

        return response()->json($item->load('category'), 201);
    }

    public function updateItem(Request $request, $itemId)
    {
        $item = DeliveryChecklistTemplateItem::findOrFail($itemId);

        $validated = $request->validate([
            'label' => 'required|string|max:255',
            'category_id' => 'nullable|exists:delivery_checklist_categories,id',
            'new_category_name' => 'nullable|string|max:255',
            'is_required' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $categoryId = $validated['category_id'] ?? null;
        $newCategoryName = $validated['new_category_name'] ?? null;

        if (!$categoryId && !$newCategoryName) {
            return response()->json(['message' => 'Either select an existing category or provide a new category name.'], 422);
        }

        if ($categoryId && $newCategoryName) {
            return response()->json(['message' => 'Please select either an existing category OR create a new one, not both.'], 422);
        }

        if (!$categoryId && $newCategoryName) {
            $category = DeliveryChecklistCategory::firstOrCreate([
                'name' => $newCategoryName
            ], [
                'color' => 'blue',
            ]);
            $categoryId = $category->id;
        }

        $item->update([
            'label' => $validated['label'],
            'category_id' => $categoryId,
            'is_required' => $validated['is_required'],
            'sort_order' => $validated['sort_order'] ?? $item->sort_order,
        ]);

        return response()->json($item->load('category'));
    }

    public function deleteItem($itemId)
    {
        $item = DeliveryChecklistTemplateItem::findOrFail($itemId);
        $item->delete();

        return response()->json(['message' => 'Item deleted']);
    }

    /*
    |--------------------------------------------------------------------------
    | Categories
    |--------------------------------------------------------------------------
    */

    private function ensureDefaultCategories()
    {
        if (DeliveryChecklistCategory::count() === 0) {
            DeliveryChecklistCategory::create(['name' => 'Pre Delivery', 'color' => 'blue']);
            DeliveryChecklistCategory::create(['name' => 'Upon Delivery', 'color' => 'green']);
        }
    }

    public function getCategories()
    {
        $this->ensureDefaultCategories();
        $categories = DeliveryChecklistCategory::orderBy('name')->get();

        return response()->json($categories);
    }

    public function createCategory(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:delivery_checklist_categories,name',
            'color' => 'nullable|string|max:255',
        ]);

        $category = DeliveryChecklistCategory::create([
            'name' => $validated['name'],
            'color' => $validated['color'] ?? 'blue',
        ]);

        return response()->json($category, 201);
    }

    public function updateCategory(Request $request, $categoryId)
    {
        $category = DeliveryChecklistCategory::findOrFail($categoryId);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:delivery_checklist_categories,name,' . $categoryId,
            'color' => 'nullable|string|max:255',
        ]);

        $category->update($validated);

        return response()->json($category);
    }

    public function deleteCategory($categoryId)
    {
        $category = DeliveryChecklistCategory::findOrFail($categoryId);

        // Check if category is being used
        if ($category->items()->exists() || $category->deliveryChecklistItems()->exists()) {
            return response()->json(['message' => 'Cannot delete category that is being used'], 422);
        }

        $category->delete();

        return response()->json(['message' => 'Category deleted']);
    }
}
