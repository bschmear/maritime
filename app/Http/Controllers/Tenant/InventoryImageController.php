<?php
namespace App\Http\Controllers\Tenant;
use App\Http\Controllers\Tenant\RecordController;
use App\Domain\InventoryImage\Models\InventoryImage as RecordModel;
use App\Domain\InventoryImage\Actions\CreateInventoryImage as CreateAction;
use App\Domain\InventoryImage\Actions\UpdateInventoryImage as UpdateAction;
use App\Domain\InventoryImage\Actions\DeleteInventoryImage as DeleteAction;
use Illuminate\Http\Request;

class InventoryImageController extends RecordController
{
    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'inventoryimages',        // recordType (route parameter name)
            'InventoryImage',         // recordTitle (display name)
            new RecordModel(),        // Model instance
            new CreateAction(),       // Create action
            new UpdateAction(),       // Update action
            new DeleteAction(),       // Delete action
            'InventoryImage'          // domainName (for schema lookup)
        );
    }

    /**
     * Override index to ensure all fields needed for image gallery are loaded
     */
    public function index(Request $request)
    {
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $schema = $this->getTableSchema();
        $formSchema = $this->getFormSchema();
        $enumOptions = $this->getEnumOptions();

        // For image gallery, we need all fields including those that accessors depend on
        $query = $this->recordModel->select([
            'id',
            'imageable_type',
            'imageable_id',
            'display_name',
            'description',
            'file',
            'file_extension',
            'file_size',
            'sort_order',
            'role',
            'is_primary',
            'created_at',
            'updated_at',
            'created_by_id',
            'updated_by_id'
        ]);

        // Apply filters
        $filtersParam = $request->get('filters');
        if ($filtersParam) {
            try {
                $filters = json_decode(urldecode($filtersParam), true);
                if (is_array($filters)) {
                    $query = $this->applyFilters($query, $filters, $fieldsSchema);
                }
            } catch (\Exception $e) {
                // ignore invalid filters
            }
        }

        // Order by sort_order for gallery display
        $query->orderBy('sort_order', 'asc')->orderBy('created_at', 'desc');

        $perPage = $request->get('per_page', 100);
        $records = $query->paginate($perPage);

        // For AJAX requests, return JSON
        if ($request->ajax() && !$request->header('X-Inertia')) {
            return response()->json([
                'records' => $records->items(),
                'schema' => $schema,
                'fieldsSchema' => $fieldsSchema,
                'meta' => [
                    'current_page' => $records->currentPage(),
                    'last_page' => $records->lastPage(),
                    'per_page' => $records->perPage(),
                    'total' => $records->total(),
                ],
            ]);
        }

        // Normal initial page load - return Inertia page
        return inertia('Tenant/' . $this->domainName . '/Index', [
            'records' => $records,
            'recordType' => $this->recordType,
            'recordTitle' => $this->recordTitle,
            'pluralTitle' => \Str::plural($this->recordTitle),
            'schema' => $schema,
            'formSchema' => $formSchema,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
        ]);
    }

    /**
     * Store a new image
     * Override parent to handle file uploads directly without Document creation
     */
    public function store(Request $request, \App\Actions\PublicStorage $publicStorage)
    {
        try {
            $data = $request->all();
            
            // The CreateInventoryImage action handles file upload internally
            $result = ($this->createAction)($data);

            // Handle case where action returns a model directly
            if (!is_array($result)) {
                $result = [
                    'success' => true,
                    'record' => $result,
                ];
            }

            if ($result['success']) {
                // For AJAX requests, return JSON
                if ($request->wantsJson() || $request->ajax()) {
                    return response()->json([
                        'success' => true,
                        'record' => $result['record'],
                        'message' => ucfirst($this->recordTitle) . ' created successfully.',
                    ]);
                }

                // Otherwise redirect
                return redirect()
                    ->route("{$this->recordType}.show", $result['record']->id)
                    ->with('success', ucfirst($this->recordTitle) . ' created successfully.');
            } else {
                if ($request->wantsJson() || $request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => $result['message'] ?? 'Failed to create record.',
                    ], 422);
                }

                return redirect()
                    ->back()
                    ->withInput()
                    ->with('error', $result['message'] ?? 'Failed to create record.');
            }
        } catch (\Exception $e) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 500);
            }

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'An error occurred: ' . $e->getMessage());
        }
    }

    /**
     * Update an existing image
     * Override parent to handle file uploads directly without Document creation
     */
    public function update(Request $request, $id, \App\Actions\PublicStorage $publicStorage)
    {
        try {
            $data = $request->all();
            
            // The UpdateInventoryImage action handles file upload internally
            $result = ($this->updateAction)($id, $data);

            // Handle case where action returns a model directly
            if (!is_array($result)) {
                $result = [
                    'success' => true,
                    'record' => $result,
                ];
            }

            if ($result['success']) {
                // For AJAX requests, return JSON
                if ($request->wantsJson() || $request->ajax()) {
                    return response()->json([
                        'success' => true,
                        'record' => $result['record'],
                        'message' => ucfirst($this->recordTitle) . ' updated successfully.',
                    ]);
                }

                // Otherwise redirect
                return redirect()
                    ->route("{$this->recordType}.show", $id)
                    ->with('success', ucfirst($this->recordTitle) . ' updated successfully.');
            } else {
                if ($request->wantsJson() || $request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => $result['message'] ?? 'Failed to update record.',
                    ], 422);
                }

                return redirect()
                    ->back()
                    ->withInput()
                    ->with('error', $result['message'] ?? 'Failed to update record.');
            }
        } catch (\Exception $e) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 500);
            }

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'An error occurred: ' . $e->getMessage());
        }
    }
}