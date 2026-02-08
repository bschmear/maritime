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
        $columns = ['id', 'display_name'];
    

        $query = $recordModel->select($columns);

        // Apply search query (fuzzy search on display_name, case-insensitive)
        $searchQuery = $request->get('search');
        if ($searchQuery && !empty(trim($searchQuery))) {
            $query->whereRaw('LOWER(display_name) LIKE ?', ['%' . strtolower(trim($searchQuery)) . '%']);
        }

        // Custom ordering support
        $orderBy = $request->get('order_by', 'display_name');
        $orderDirection = $request->get('order_direction', 'asc');

        // Validate order_by field exists in the model
        if (in_array($orderBy, ['id', 'display_name', 'created_at', 'updated_at'])) {
            $query->orderBy($orderBy, $orderDirection);
        } else {
            // Default to display_name ordering
            $query->orderBy('display_name', 'asc');
        }

        // Get per_page from request, default to 15
        $perPage = $request->get('per_page', 15);
        $records = $query->paginate($perPage);

        return response()->json([
            'records' => $records->items(),
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
