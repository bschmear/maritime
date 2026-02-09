<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Concerns\HasSchemaSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class GeneralController extends BaseController
{
    use AuthorizesRequests, ValidatesRequests, HasSchemaSupport;


    public function lookup(Request $request)
    {
        // Simple lookup - just return id and display_name
        $type = $request->get('type');

        $recordModel = 'App\Domain\\' . ucfirst($type) . '\Models\\' . ucfirst($type);
        $recordModel = new $recordModel();

        // Check if display_name column exists, otherwise just select id
        $tableName = $recordModel->getTable();
        $hasDisplayNameColumn = \Schema::connection($recordModel->getConnectionName())
            ->hasColumn($tableName, 'display_name');

        $columns = ['id'];
        if ($hasDisplayNameColumn) {
            $columns[] = 'display_name';
        } else {
            // For models without display_name column, add foreign keys needed for relationships
            if ($type === 'assetunit') {
                $columns[] = 'asset_id';
                $columns[] = 'serial_number';
                $columns[] = 'hin';
                $columns[] = 'sku';
            } elseif ($type === 'inventoryunit') {
                $columns[] = 'inventory_item_id';
                $columns[] = 'serial_number';
                $columns[] = 'hin';
                $columns[] = 'sku';
            }
        }

        $query = $recordModel->select($columns);

        // Load relationships for models that need them for display names
        if ($type === 'assetunit') {
            $query->with('asset:id,display_name');
        } elseif ($type === 'inventoryunit') {
            $query->with('inventoryItem:id,display_name');
        }

        // Apply search query (fuzzy search on display_name or related fields, case-insensitive)
        $searchQuery = $request->get('search');
        if ($searchQuery && !empty(trim($searchQuery))) {
            if ($hasDisplayNameColumn) {
                $query->whereRaw('LOWER(display_name) LIKE ?', ['%' . strtolower(trim($searchQuery)) . '%']);
            } else {
                // For models without display_name column, search in typical display name fields
                $searchTerm = '%' . strtolower(trim($searchQuery)) . '%';
                if ($type === 'assetunit') {
                    // For AssetUnit, also search in the joined assets table
                    $query->where(function ($q) use ($searchTerm) {
                        $q->whereRaw('LOWER(asset_units.serial_number) LIKE ?', [$searchTerm])
                          ->orWhereRaw('LOWER(asset_units.hin) LIKE ?', [$searchTerm])
                          ->orWhereRaw('LOWER(asset_units.sku) LIKE ?', [$searchTerm])
                          ->orWhereRaw('LOWER(assets.display_name) LIKE ?', [$searchTerm])
                          ->orWhereRaw('CAST(asset_units.id AS TEXT) LIKE ?', [$searchTerm]);
                    });
                } elseif ($type === 'inventoryunit') {
                    // For InventoryUnit, also search in the joined inventory_items table
                    $query->where(function ($q) use ($searchTerm) {
                        $q->whereRaw('LOWER(inventory_units.serial_number) LIKE ?', [$searchTerm])
                          ->orWhereRaw('LOWER(inventory_units.hin) LIKE ?', [$searchTerm])
                          ->orWhereRaw('LOWER(inventory_units.sku) LIKE ?', [$searchTerm])
                          ->orWhereRaw('LOWER(inventory_items.display_name) LIKE ?', [$searchTerm])
                          ->orWhereRaw('CAST(inventory_units.id AS TEXT) LIKE ?', [$searchTerm]);
                    });
                } else {
                    $query->where(function ($q) use ($searchTerm) {
                        $q->whereRaw('LOWER(serial_number) LIKE ?', [$searchTerm])
                          ->orWhereRaw('LOWER(hin) LIKE ?', [$searchTerm])
                          ->orWhereRaw('LOWER(sku) LIKE ?', [$searchTerm])
                          ->orWhereRaw('CAST(id AS TEXT) LIKE ?', [$searchTerm]);
                    });
                }
            }
        }

        // Custom ordering support
        $orderBy = $request->get('order_by', 'display_name');
        $orderDirection = $request->get('order_direction', 'asc');

        // Handle ordering - if display_name column doesn't exist, use alternative ordering
        if ($orderBy === 'display_name' && !$hasDisplayNameColumn) {
            // For AssetUnit and InventoryUnit models, order by parent item then unit identifier
            if ($type === 'assetunit') {
                // Override select to use table prefixes to avoid ambiguous column errors
                $prefixedColumns = array_map(function($col) {
                    return $col === 'id' ? 'asset_units.id' : $col;
                }, $columns);
                $query->select($prefixedColumns)
                      ->join('assets', 'asset_units.asset_id', '=', 'assets.id')
                      ->orderBy('assets.display_name')
                      ->orderByRaw("COALESCE(NULLIF(asset_units.serial_number, ''), NULLIF(asset_units.hin, ''), NULLIF(asset_units.sku, ''), CAST(asset_units.id AS TEXT))");
            } elseif ($type === 'inventoryunit') {
                // Override select to use table prefixes to avoid ambiguous column errors
                $prefixedColumns = array_map(function($col) {
                    return $col === 'id' ? 'inventory_units.id' : $col;
                }, $columns);
                $query->select($prefixedColumns)
                      ->join('inventory_items', 'inventory_units.inventory_item_id', '=', 'inventory_items.id')
                      ->orderBy('inventory_items.display_name')
                      ->orderByRaw("COALESCE(NULLIF(inventory_units.serial_number, ''), NULLIF(inventory_units.hin, ''), NULLIF(inventory_units.sku, ''), CAST(inventory_units.id AS TEXT))");
            } else {
                // Default ordering for other models without display_name column
                $query->orderBy('created_at', 'desc');
            }
        } elseif (in_array($orderBy, ['id', 'created_at', 'updated_at']) || ($orderBy === 'display_name' && $hasDisplayNameColumn)) {
            $query->orderBy($orderBy, $orderDirection);
        } else {
            // Default ordering
            $query->orderBy('created_at', 'desc');
        }

        // Get per_page from request, default to 15
        $perPage = $request->get('per_page', 15);
        $records = $query->paginate($perPage);

        // Ensure display_name is included in the response for all records
        $items = $records->items();
        if (!$hasDisplayNameColumn) {
            // For models without display_name column, add it from the accessor
            $items = array_map(function ($item) {
                $itemArray = $item->toArray();
                $itemArray['display_name'] = $item->display_name;
                return $itemArray;
            }, $items);
        }

        return response()->json([
            'records' => $items,
            'meta' => [
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
            ]
        ]);
    }

    public function selectForm(Request $request)
    {
        $type = $request->get('type');

        // Get the domain name from the type
        $domainName = ucfirst($type);

        // Temporarily set the domain name for schema methods
        $this->domainName = $domainName;

        // Get form schema and fields schema
        $formSchema = $this->getFormSchema();
        $fieldsSchemaRaw = $this->getFieldsSchema();

        // Handle fields schema structure - some schemas have a "fields" wrapper
        $fieldsSchema = isset($fieldsSchemaRaw['fields']) ? $fieldsSchemaRaw['fields'] : $fieldsSchemaRaw;

        $enumOptions = $this->getEnumOptions();

        // Debug: ensure enum options are loaded
        if (empty($enumOptions)) {
            // Fallback: manually load the enum options for InventoryUnit
            if ($domainName === 'InventoryUnit') {
                $enumOptions = [
                    'App\Enums\Inventory\UnitCondition' => \App\Enums\Inventory\UnitCondition::options(),
                    'App\Enums\Inventory\UnitStatus' => \App\Enums\Inventory\UnitStatus::options(),
                ];
            }
        }

        // If it's an AJAX request, return JSON
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'formSchema' => $formSchema,
                'fieldsSchema' => $fieldsSchema,
                'enumOptions' => $enumOptions,
                'recordType' => strtolower($domainName) . 's', // Convert to plural (BoatMake -> boatmakes)
                'recordTitle' => $domainName,
            ]);
        }

        return response()->json(['error' => 'Invalid request'], 400);
    }
}
