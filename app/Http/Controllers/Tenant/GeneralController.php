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
}
