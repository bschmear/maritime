<?php

namespace App\Http\Controllers\Kiosk;

use App\Domain\InventoryCatalog\Models\InventoryBoatMake;
use App\Domain\InventoryCatalog\Models\InventoryBoatType;
use App\Domain\InventoryCatalog\Models\InventoryHullMaterial;
use App\Domain\InventoryCatalog\Models\InventoryHullType;
use App\Http\Controllers\Controller;
use App\Support\InventoryCatalogImageStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InventoryBrandController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search', ''));

        $brands = InventoryBoatMake::query()
            ->withCount('catalogAssets')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('display_name', 'ilike', "%{$search}%")
                        ->orWhere('slug', 'ilike', "%{$search}%");
                });
            })
            ->orderBy('display_name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Kiosk/Inventory/Brands/Index', [
            'brands' => $brands,
            'filters' => ['search' => $search],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Kiosk/Inventory/Brands/Create', $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateBrand($request);

        $brand = InventoryBoatMake::query()->create($validated);

        return redirect()->route('kiosk.inventory-brands.show', $brand)
            ->with('success', 'Brand created successfully.');
    }

    public function show(int $inventoryBrand): Response
    {
        $brand = InventoryBoatMake::query()
            ->with([
                'boatType:id,display_name,slug',
                'boatTypes:id,display_name,slug',
                'hullType:id,display_name',
                'hullMaterial:id,display_name',
                'catalogAssets' => static function ($query) {
                    $query
                        ->orderBy('display_name')
                        ->with(['variants' => static function ($variants) {
                            $variants->orderBy('display_name');
                        }]);
                },
            ])
            ->withCount('catalogAssets')
            ->findOrFail($inventoryBrand);

        return Inertia::render('Kiosk/Inventory/Brands/Show', [
            'brand' => $brand,
        ]);
    }

    public function edit(int $inventoryBrand): Response
    {
        $brand = InventoryBoatMake::query()->withCount('catalogAssets')->findOrFail($inventoryBrand);

        return Inertia::render('Kiosk/Inventory/Brands/Edit', [
            'brand' => $brand,
            ...$this->formOptions(),
        ]);
    }

    public function update(Request $request, int $inventoryBrand): RedirectResponse
    {
        $brand = InventoryBoatMake::query()->findOrFail($inventoryBrand);
        $validated = $this->validateBrand($request, $brand);

        $brand->update($validated);

        return redirect()->route('kiosk.inventory-brands.show', $brand)
            ->with('success', 'Brand updated successfully.');
    }

    public function destroy(int $inventoryBrand): RedirectResponse
    {
        $brand = InventoryBoatMake::query()->findOrFail($inventoryBrand);

        InventoryCatalogImageStorage::deleteIfStored($brand->logo_url);
        $brand->delete();

        return redirect()->route('kiosk.inventory-brands.index')
            ->with('success', 'Brand deleted successfully.');
    }

    public function uploadLogo(Request $request, int $inventoryBrand): JsonResponse
    {
        $brand = InventoryBoatMake::query()->findOrFail($inventoryBrand);

        $validated = $request->validate([
            'logo_file' => 'required|image|mimes:jpeg,jpg,png,webp,gif|max:10240',
        ]);

        $result = InventoryCatalogImageStorage::store(
            $request->file('logo_file'),
            $brand->logo_url,
        );

        $brand->update(['logo_url' => $result['url']]);

        return response()->json([
            'logo_url' => $result['url'],
        ]);
    }

    public function removeLogo(int $inventoryBrand): RedirectResponse
    {
        $brand = InventoryBoatMake::query()->findOrFail($inventoryBrand);

        InventoryCatalogImageStorage::deleteIfStored($brand->logo_url);
        $brand->update(['logo_url' => null]);

        return back()->with('success', 'Brand logo removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateBrand(Request $request, ?InventoryBoatMake $brand = null): array
    {
        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('inventory.boat_make', 'slug')->ignore($brand?->id),
            ],
            'active' => ['sometimes', 'boolean'],
            'boat_type_id' => ['nullable', 'integer', Rule::exists('inventory.boat_type', 'id')],
            'hull_type_id' => ['nullable', 'integer', Rule::exists('inventory.hull_type', 'id')],
            'hull_material_id' => ['nullable', 'integer', Rule::exists('inventory.hull_material', 'id')],
            'website_url' => ['nullable', 'string', 'max:512'],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);

        $validated['slug'] = Str::slug($validated['slug']);
        $validated['active'] = $request->boolean('active', true);

        return $validated;
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'boatTypes' => InventoryBoatType::query()->orderBy('display_name')->get(['id', 'display_name']),
            'hullTypes' => InventoryHullType::query()->orderBy('display_name')->get(['id', 'display_name']),
            'hullMaterials' => InventoryHullMaterial::query()->orderBy('display_name')->get(['id', 'display_name']),
        ];
    }
}
