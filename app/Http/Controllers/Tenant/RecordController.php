<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Concerns\HasSchemaSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Http\Controllers\Concerns\HasImageSupport;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use App\Actions\PublicStorage;
use App\Enums\Timezone;
use App\Domain\Document\Models\Document;
use Illuminate\Support\Facades\Storage;

class RecordController extends BaseController
{
    use AuthorizesRequests, ValidatesRequests, HasSchemaSupport, HasImageSupport;

    protected $recordType;
    protected $recordTitle;
    protected $recordModel;
    protected $createAction;
    protected $updateAction;
    protected $deleteAction;
    protected $domainName;

    public function __construct(
        Request $request,
        $recordType,
        $recordTitle,
        $recordModel,
        $createAction,
        $updateAction,
        $deleteAction,
        $domainName = null
    ) {
        $this->middleware('auth');
        $this->recordType = $recordType;
        $this->recordTitle = $recordTitle;
        $this->recordModel = $recordModel;
        $this->createAction = $createAction;
        $this->updateAction = $updateAction;
        $this->deleteAction = $deleteAction;
        $this->domainName = $domainName ?? $recordTitle;
    }
    
    /**
     * Get unwrapped fields schema (handles both wrapped and unwrapped structures)
     */
    protected function getUnwrappedFieldsSchema()
    {
        $fieldsSchemaRaw = $this->getFieldsSchema();
        // Unwrap fields if necessary
        return isset($fieldsSchemaRaw['fields']) ? $fieldsSchemaRaw['fields'] : $fieldsSchemaRaw;
    }

    public function index(Request $request)
    {
        $columns = $this->getSchemaColumns();
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $schema = $this->getTableSchema();
        $formSchema = $this->getFormSchema();
        $enumOptions = $this->getEnumOptions();

        // Separate actual database columns from relationship columns
        $actualColumns = [];
        $relationshipColumns = [];

        foreach ($columns as $column) {
            if (strpos($column, '.') !== false) {
                // This is a relationship column like "asset.display_name"
                $relationshipColumns[] = $column;
            } else {
                // This is an actual database column
                $actualColumns[] = $column;
            }
        }

        if (!in_array('id', $actualColumns)) {
            $actualColumns[] = 'id';
        }

        $relationships = $this->getRelationshipsToLoad($fieldsSchema);

        // Load relationships needed for display names
        if ($this->domainName === 'AssetUnit') {
            $relationships['asset'] = function ($query) {
                $query->select(['id', 'display_name']);
            };
        } elseif ($this->domainName === 'InventoryUnit') {
            $relationships['inventoryItem'] = function ($query) {
                $query->select(['id', 'display_name']);
            };
        }

        foreach ($fieldsSchema as $fieldKey => $fieldDef) {
            if (isset($fieldDef['type']) && $fieldDef['type'] === 'record' && isset($fieldDef['typeDomain'])) {
                $relationshipName = $fieldDef['relationship'] ?? str_replace('_id', '', $fieldKey);

                // Determine which fields to select for this relationship
                $selectFields = ['id'];

                // Handle special cases for models that don't have display_name column
                if ($fieldDef['typeDomain'] === 'AssetUnit') {
                    // AssetUnit uses accessor for display_name, so select the underlying columns and load asset relationship
                    $selectFields = ['id', 'serial_number', 'hin', 'sku', 'asset_id'];
                    $relationships[$relationshipName] = function ($query) {
                        $query->select(['id', 'serial_number', 'hin', 'sku', 'asset_id'])
                              ->with(['asset' => function ($q) {
                                  $q->select(['id', 'display_name']);
                              }]);
                    };
                } else {
                    $selectFields[] = 'display_name';
                }

                // If a custom displayField is specified, add it to the select
                if (isset($fieldDef['displayField']) && $fieldDef['displayField'] !== 'display_name') {
                    $selectFields[] = $fieldDef['displayField'];
                }

                // Make sure we have unique fields
                $selectFields = array_unique($selectFields);

                // Only set the relationship if it wasn't already set for AssetUnit
                if (!isset($relationships[$relationshipName])) {
                    $relationships[$relationshipName] = function ($query) use ($selectFields) {
                        $query->select($selectFields);
                    };
                }
            }
        }

        $query = $this->recordModel->select($actualColumns)->with($relationships);

        $searchQuery = $request->get('search');
        if ($searchQuery && !empty(trim($searchQuery))) {
            // Check if display_name column exists, otherwise search in typical display name fields
            $tableName = $this->recordModel->getTable();
            $hasDisplayName = \Schema::connection($this->recordModel->getConnectionName())
                ->hasColumn($tableName, 'display_name');

            if ($hasDisplayName) {
                $query->whereRaw('LOWER(display_name) LIKE ?', ['%' . strtolower(trim($searchQuery)) . '%']);
            } else {
                // Search in fields that typically make up display names
                $searchTerm = '%' . strtolower(trim($searchQuery)) . '%';
                if ($this->domainName === 'AssetUnit') {
                    // For AssetUnit, also search in the joined assets table
                    $query->where(function ($q) use ($searchTerm) {
                        $q->whereRaw('LOWER(asset_units.serial_number) LIKE ?', [$searchTerm])
                          ->orWhereRaw('LOWER(asset_units.hin) LIKE ?', [$searchTerm])
                          ->orWhereRaw('LOWER(asset_units.sku) LIKE ?', [$searchTerm])
                          ->orWhereRaw('LOWER(assets.display_name) LIKE ?', [$searchTerm])
                          ->orWhereRaw('CAST(asset_units.id AS TEXT) LIKE ?', [$searchTerm]);
                    });
                } elseif ($this->domainName === 'InventoryUnit') {
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


        // Order by display_name if the column exists, otherwise by created_at
        $tableName = $this->recordModel->getTable();
        $hasDisplayName = \Schema::connection($this->recordModel->getConnectionName())
            ->hasColumn($tableName, 'display_name');

        if ($hasDisplayName) {
            $query->orderByRaw('LOWER(display_name) ASC');
        } else {
            // For models with virtual display names (like AssetUnit, InventoryUnit), order by parent item then unit identifier
            if ($this->domainName === 'AssetUnit') {
                // Override select to use table prefixes to avoid ambiguous column errors
                $prefixedColumns = array_map(function($col) {
                    // Prefix all columns that belong to the asset_units table
                    $tableColumns = ['id', 'asset_id', 'serial_number', 'hin', 'sku', 'condition', 'status', 'inactive', 'is_customer_owned', 'is_consignment', 'engine_hours', 'last_service_at', 'warranty_expires_at', 'cost', 'asking_price', 'sold_price', 'price_history', 'vendor_id', 'customer_id', 'location_id', 'subsidiary_id', 'in_service_at', 'out_of_service_at', 'sold_at', 'attributes', 'notes', 'created_at', 'updated_at'];
                    return in_array($col, $tableColumns) ? 'asset_units.' . $col : $col;
                }, $actualColumns);
                $query->select($prefixedColumns)
                      ->join('assets', 'asset_units.asset_id', '=', 'assets.id')
                      ->orderBy('assets.display_name')
                      ->orderByRaw("COALESCE(NULLIF(asset_units.serial_number, ''), NULLIF(asset_units.hin, ''), NULLIF(asset_units.sku, ''), CAST(asset_units.id AS TEXT))");
            } elseif ($this->domainName === 'InventoryUnit') {
                // Override select to use table prefixes to avoid ambiguous column errors
                $prefixedColumns = array_map(function($col) {
                    // Prefix all columns that belong to the inventory_units table
                    $tableColumns = ['id', 'inventory_item_id', 'serial_number', 'hin', 'sku', 'condition', 'status', 'inactive', 'is_customer_owned', 'is_consignment', 'engine_hours', 'last_service_at', 'warranty_expires_at', 'cost', 'asking_price', 'sold_price', 'price_history', 'vendor_id', 'customer_id', 'location_id', 'subsidiary_id', 'in_service_at', 'out_of_service_at', 'sold_at', 'attributes', 'notes', 'created_at', 'updated_at'];
                    return in_array($col, $tableColumns) ? 'inventory_units.' . $col : $col;
                }, $actualColumns);
                $query->select($prefixedColumns)
                      ->join('inventory_items', 'inventory_units.inventory_item_id', '=', 'inventory_items.id')
                      ->orderBy('inventory_items.display_name')
                      ->orderByRaw("COALESCE(NULLIF(inventory_units.serial_number, ''), NULLIF(inventory_units.hin, ''), NULLIF(inventory_units.sku, ''), CAST(inventory_units.id AS TEXT))");
            } else {
                $query->orderBy('created_at', 'desc');
            }
        }

        $perPage = $request->get('per_page', 15);
        $records = $query->paginate($perPage);

        // Return JSON for Inertia / AJAX / JSON requests only
        if ($request->ajax() && !$request->header('X-Inertia')) {
            return response()->json([
                'records' => $records->items(),
                'schema' => $schema,
                'fieldsSchema' => $fieldsSchema, // Already unwrapped above
                'meta' => [
                    'current_page' => $records->currentPage(),
                    'last_page' => $records->lastPage(),
                    'per_page' => $records->perPage(),
                    'total' => $records->total(),
                ],
            ]);
        }

        // Normal initial page load - return Inertia page
        $pluralTitle = Str::plural($this->recordTitle);

        return inertia('Tenant/' . $this->domainName . '/Index', [
            'records' => $records,
            'recordType' => $this->recordType,
            'recordTitle' => $this->recordTitle,
            'pluralTitle' => $pluralTitle,
            'schema' => $schema,
            'formSchema' => $formSchema,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
        ]);
    }

    public function create()
    {
        $formSchema = $this->getFormSchema();
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $enumOptions = $this->getEnumOptions();

        // Get account settings for timezone display (cached)
        $account = \App\Models\AccountSettings::getCurrent();

        return inertia('Tenant/' . $this->domainName . '/Create', [
            'recordType' => $this->recordType,
            'formSchema' => $formSchema,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
            'account' => $account,
            'timezones' => Timezone::options(),
        ]);
    }

    public function store(Request $request, PublicStorage $publicStorage)
    {
        try {
            $data = $request->all();
            $fieldsSchema = $this->getUnwrappedFieldsSchema();

            // Handle image uploads (creates Document records)
            foreach ($fieldsSchema as $fieldKey => $fieldDef) {
                if (isset($fieldDef['type']) && $fieldDef['type'] === 'image') {
                    if ($request->hasFile($fieldKey)) {
                        $file = $request->file($fieldKey);

                        // Get meta options if available
                        $meta = $fieldDef['meta'] ?? [];

                        // Default options
                        $directory = $meta['directory'] ?? ($this->domainName . '/' . $fieldKey); // e.g., "User/avatar"
                        $isPrivate = $meta['private'] ?? false;
                        $resizeWidth = $meta['max_width'] ?? null;
                        $crop = $meta['crop'] ?? false;

                        // Store the image
                        $result = $publicStorage->store(
                            file: $file,
                            directory: $directory,
                            resizeWidth: $resizeWidth,
                            existingFile: null,
                            crop: $crop,
                            deleteOld: false,
                            isPrivate: $isPrivate
                        );

                        // Create Document record
                        $document = Document::create([
                            'display_name' => $result['display_name'],
                            'file' => $result['key'],
                            'file_extension' => $result['file_extension'],
                            'file_size' => $result['file_size'],
                            'created_by_id' => auth()->id(),
                            'updated_by_id' => auth()->id(),
                        ]);

                        // Update data to save the document ID instead of the file object/key
                        $data[$fieldKey] = $document->id;
                    }
                }
            }

            $result = ($this->createAction)($data);

            // Handle case where action returns a model directly (for backward compatibility)
            if (!is_array($result)) {
                $result = [
                    'success' => true,
                    'record' => $result,
                ];
            }

            if ($result['success']) {
                // If it's an AJAX request that wants JSON, return JSON instead of redirecting
                if ($request->ajax() && !$request->header('X-Inertia')) {
                    // Reload the record with relationships to ensure display_name is available
                    $fieldsSchema = $this->getUnwrappedFieldsSchema();
                    $relationships = $this->getRelationshipsToLoad($fieldsSchema);

                    // Add record type relationships with id, display_name, and custom displayField
                    foreach ($fieldsSchema as $fieldKey => $fieldDef) {
                        if (isset($fieldDef['type']) && $fieldDef['type'] === 'record' && isset($fieldDef['typeDomain'])) {
                            $relationshipName = $fieldDef['relationship'] ?? str_replace('_id', '', $fieldKey);
                            
                            // Determine which fields to select for this relationship
                            $selectFields = ['id'];

                            // Handle special cases for models that don't have display_name column
                            if ($fieldDef['typeDomain'] === 'AssetUnit') {
                                // AssetUnit uses accessor for display_name, so select the underlying columns and load asset relationship
                                $selectFields = ['id', 'serial_number', 'hin', 'sku', 'asset_id'];
                                $relationships[$relationshipName] = function ($query) {
                                    $query->select(['id', 'serial_number', 'hin', 'sku', 'asset_id'])
                                          ->with(['asset' => function ($q) {
                                              $q->select(['id', 'display_name']);
                                          }]);
                                };
                            } else {
                                $selectFields[] = 'display_name';
                            }

                            // If a custom displayField is specified, add it to the select
                            if (isset($fieldDef['displayField']) && $fieldDef['displayField'] !== 'display_name') {
                                $selectFields[] = $fieldDef['displayField'];
                            }

                            // Make sure we have unique fields
                            $selectFields = array_unique($selectFields);

                            // Only set the relationship if it wasn't already set for AssetUnit
                            if (!isset($relationships[$relationshipName])) {
                                $relationships[$relationshipName] = function ($query) use ($selectFields) {
                                    $query->select($selectFields);
                                };
                            }
                        }
                    }

                    // Reload the record with relationships
                    $record = $this->recordModel->with($relationships)->find($result['record']->id);

                    return response()->json([
                        'success' => true,
                        'recordId' => $result['record']->id,
                        'record' => $record,
                        'message' => $this->domainName . ' created successfully',
                    ]);
                }

                return redirect()
                    ->route($this->recordType . '.show', $result['record']->id)
                    ->with('success', $this->domainName . ' created successfully')
                    ->with('recordId', $result['record']->id);
            }

            // Handle errors for AJAX requests
            if ($request->ajax() && !$request->header('X-Inertia')) {
                return response()->json([
                    'success' => false,
                    'errors' => $result['errors'] ?? [],
                    'message' => $result['message'] ?? 'Failed to create ' . $this->recordTitle,
                ], 422);
            }

            return back()
                ->withInput()
                ->with('error', $result['message'] ?? 'Failed to create ' . $this->recordTitle);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Handle validation errors
            if ($request->ajax() && !$request->header('X-Inertia')) {
                return response()->json([
                    'success' => false,
                    'errors' => $e->errors(),
                    'message' => 'Validation failed',
                ], 422);
            }

            return back()
                ->withInput()
                ->withErrors($e->errors());
        }
    }

    public function show(Request $request, $id)
    {
        $fieldsSchema = $this->getUnwrappedFieldsSchema();

        // Build relationships array including both morph and record types
        $relationships = $this->getRelationshipsToLoad($fieldsSchema);

        // Add record type relationships with id, display_name, and custom displayField
        foreach ($fieldsSchema as $fieldKey => $fieldDef) {
            if (isset($fieldDef['type']) && $fieldDef['type'] === 'record' && isset($fieldDef['typeDomain'])) {
                // Convert field key like 'assigned_id' to relationship name like 'assigned'
                $relationshipName = $fieldDef['relationship'] ?? str_replace('_id', '', $fieldKey);

                // Determine which fields to select for this relationship
                $selectFields = ['id'];

                // Handle special cases for models that don't have display_name column
                if ($fieldDef['typeDomain'] === 'AssetUnit') {
                    // AssetUnit uses accessor for display_name, so select the underlying columns and load asset relationship
                    $selectFields = ['id', 'serial_number', 'hin', 'sku', 'asset_id'];
                    $relationships[$relationshipName] = function ($query) {
                        $query->select(['id', 'serial_number', 'hin', 'sku', 'asset_id'])
                              ->with(['asset' => function ($q) {
                                  $q->select(['id', 'display_name']);
                              }]);
                    };
                } else {
                    $selectFields[] = 'display_name';
                }

                // If a custom displayField is specified, add it to the select
                if (isset($fieldDef['displayField']) && $fieldDef['displayField'] !== 'display_name') {
                    $selectFields[] = $fieldDef['displayField'];
                }

                // Make sure we have unique fields
                $selectFields = array_unique($selectFields);

                // Only set the relationship if it wasn't already set for AssetUnit
                if (!isset($relationships[$relationshipName])) {
                    $relationships[$relationshipName] = function ($query) use ($selectFields) {
                        $query->select($selectFields);
                    };
                }
            }
        }

        // Load form schema to check for sublists
        $formSchema = $this->getFormSchema();

        // Add sublist relationships (for model relationships like hasMany, belongsToMany)
        if (isset($formSchema['sublists']) && is_array($formSchema['sublists'])) {
            foreach ($formSchema['sublists'] as $sublist) {
                if (isset($sublist['modelRelationship'])) {
                    $relationships[$sublist['modelRelationship']] = function ($query) {
                        // Load basic fields for the related records
                        $query->select('*');
                    };
                }
            }
        }
       
        // Load the record with relationships
        $record = $this->recordModel
            ->with($relationships)
            ->findOrFail($id);
           
        $enumOptions = $this->getEnumOptions();

        // Get account settings for timezone display (cached)
        $account = \App\Models\AccountSettings::getCurrent();

        // If it's a non-Inertia AJAX request, return JSON with full record data
        if ($request->ajax() && !$request->header('X-Inertia')) {
            return response()->json([
                'record' => $record,
                'recordType' => $this->recordType,
                'recordTitle' => $this->recordTitle,
                'domainName' => $this->domainName,
                'formSchema' => $formSchema,
                'fieldsSchema' => $fieldsSchema,
                'enumOptions' => $enumOptions,
                'imageUrls' => $this->getImageUrls($record, $fieldsSchema),
                'account' => $account,
                'timezones' => Timezone::options(),
            ]);
        }

        // Return Inertia response (for navigation and partial reloads)
        return inertia('Tenant/' . $this->domainName . '/Show', [
            'record' => $record,
            'recordType' => $this->recordType,
            'recordTitle' => $this->recordTitle,
            'domainName' => $this->domainName,
            'formSchema' => $formSchema,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
            'imageUrls' => $this->getImageUrls($record, $fieldsSchema),
            'account' => $account,
            'timezones' => Timezone::options(),
        ]);
    }

    public function edit($id)
    {
        $fieldsSchema = $this->getUnwrappedFieldsSchema();

        // Build relationships array for loading related data
        $relationships = $this->getRelationshipsToLoad($fieldsSchema);

        // Add record type relationships with id, display_name, and custom displayField
        foreach ($fieldsSchema as $fieldKey => $fieldDef) {
            if (isset($fieldDef['type']) && $fieldDef['type'] === 'record' && isset($fieldDef['typeDomain'])) {
                // Convert field key like 'assigned_id' to relationship name like 'assigned'
                $relationshipName = $fieldDef['relationship'] ?? str_replace('_id', '', $fieldKey);

                // Determine which fields to select for this relationship
                $selectFields = ['id'];

                // Handle special cases for models that don't have display_name column
                if ($fieldDef['typeDomain'] === 'AssetUnit') {
                    // AssetUnit uses accessor for display_name, so select the underlying columns and load asset relationship
                    $selectFields = ['id', 'serial_number', 'hin', 'sku', 'asset_id'];
                    $relationships[$relationshipName] = function ($query) {
                        $query->select(['id', 'serial_number', 'hin', 'sku', 'asset_id'])
                              ->with(['asset' => function ($q) {
                                  $q->select(['id', 'display_name']);
                              }]);
                    };
                } else {
                    $selectFields[] = 'display_name';
                }

                // If a custom displayField is specified, add it to the select
                if (isset($fieldDef['displayField']) && $fieldDef['displayField'] !== 'display_name') {
                    $selectFields[] = $fieldDef['displayField'];
                }

                // Make sure we have unique fields
                $selectFields = array_unique($selectFields);

                // Only set the relationship if it wasn't already set for AssetUnit
                if (!isset($relationships[$relationshipName])) {
                    $relationships[$relationshipName] = function ($query) use ($selectFields) {
                        $query->select($selectFields);
                    };
                }
            }
        }

        // Load the record with relationships
        $record = $this->recordModel->with($relationships)->findOrFail($id);

        $formSchema = $this->getFormSchema();
        $enumOptions = $this->getEnumOptions();

        // Get account settings for timezone display (cached)
        $account = \App\Models\AccountSettings::getCurrent();

        return inertia('Tenant/' . $this->domainName . '/Edit', [
            'record' => $record,
            'recordType' => $this->recordType,
            'formSchema' => $formSchema,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
            'imageUrls' => $this->getImageUrls($record, $fieldsSchema),
            'account' => $account,
            'timezones' => Timezone::options(),
        ]);
    }

    public function update(Request $request, $id, PublicStorage $publicStorage)
    {
        try {
            $data = $request->all();
            $fieldsSchema = $this->getUnwrappedFieldsSchema();
            
            // Handle image uploads (creates Document records)
            foreach ($fieldsSchema as $fieldKey => $fieldDef) {
                if (isset($fieldDef['type']) && $fieldDef['type'] === 'image') {
                    // Get current record to find existing image
                    $currentRecord = $this->recordModel->find($id);
                    $existingDocumentId = $currentRecord ? $currentRecord->{$fieldKey} : null;

                    if ($request->hasFile($fieldKey)) {
                        $file = $request->file($fieldKey);

                        // Get meta options
                        $meta = $fieldDef['meta'] ?? [];

                        // Default options
                        $directory = $meta['directory'] ?? ($this->domainName . '/' . $fieldKey);
                        $isPrivate = $meta['private'] ?? false;
                        $resizeWidth = $meta['max_width'] ?? null;
                        $crop = $meta['crop'] ?? false;

                        $existingDocument = $existingDocumentId ? Document::find($existingDocumentId) : null;
                        $existingFileKey = $existingDocument ? $existingDocument->file : null;

                        // Store new image and delete old one
                        $storageResult = $publicStorage->store(
                            file: $file,
                            directory: $directory,
                            resizeWidth: $resizeWidth,
                            existingFile: $existingFileKey,
                            crop: $crop,
                            deleteOld: true,
                            isPrivate: $isPrivate
                        );

                        // Create New Document record
                        $document = Document::create([
                            'display_name' => $storageResult['display_name'],
                            'file' => $storageResult['key'],
                            'file_extension' => $storageResult['file_extension'],
                            'file_size' => $storageResult['file_size'],
                            'created_by_id' => auth()->id(),
                            'updated_by_id' => auth()->id(),
                        ]);

                        // Delete old document record
                        if ($existingDocument) {
                            $existingDocument->delete();
                        }

                        // Update data
                        $data[$fieldKey] = $document->id;
                    } elseif (isset($data[$fieldKey]) && $data[$fieldKey] == $existingDocumentId) {
                        // Value is unchanged ID, remove from data to bypass validation "image" rule
                        unset($data[$fieldKey]);
                    }
                }
            }
            $result = ($this->updateAction)($id, $data);

            if ($result['success']) {

                // Check if this is a non-Inertia AJAX request (axios from preventRedirect)
                if ($request->ajax() && !$request->header('X-Inertia')) {

                    // Reload the record with relationships to ensure display_name is available
                    $fieldsSchema = $this->getUnwrappedFieldsSchema();
                    $relationships = $this->getRelationshipsToLoad($fieldsSchema);

                    // Add record type relationships with id, display_name, and custom displayField
                    foreach ($fieldsSchema as $fieldKey => $fieldDef) {
                        if (isset($fieldDef['type']) && $fieldDef['type'] === 'record' && isset($fieldDef['typeDomain'])) {
                            $relationshipName = $fieldDef['relationship'] ?? str_replace('_id', '', $fieldKey);
                            
                            // Determine which fields to select for this relationship
                            $selectFields = ['id'];

                            // Handle special cases for models that don't have display_name column
                            if ($fieldDef['typeDomain'] === 'AssetUnit') {
                                // AssetUnit uses accessor for display_name, so select the underlying columns and load asset relationship
                                $selectFields = ['id', 'serial_number', 'hin', 'sku', 'asset_id'];
                                $relationships[$relationshipName] = function ($query) {
                                    $query->select(['id', 'serial_number', 'hin', 'sku', 'asset_id'])
                                          ->with(['asset' => function ($q) {
                                              $q->select(['id', 'display_name']);
                                          }]);
                                };
                            } else {
                                $selectFields[] = 'display_name';
                            }

                            // If a custom displayField is specified, add it to the select
                            if (isset($fieldDef['displayField']) && $fieldDef['displayField'] !== 'display_name') {
                                $selectFields[] = $fieldDef['displayField'];
                            }

                            // Make sure we have unique fields
                            $selectFields = array_unique($selectFields);

                            // Only set the relationship if it wasn't already set for AssetUnit
                            if (!isset($relationships[$relationshipName])) {
                                $relationships[$relationshipName] = function ($query) use ($selectFields) {
                                    $query->select($selectFields);
                                };
                            }
                        }
                    }

                    // Reload the record with relationships
                    $record = $this->recordModel->with($relationships)->find($id);

                    return response()->json([
                        'success' => true,
                        'record' => $record,
                        'message' => $this->domainName . ' updated successfully',
                    ]);
                }

                // Inertia Response (Always redirect for Inertia requests)
                return back()->with('success', $this->domainName . ' updated successfully');
            }

            // Handle business logic errors
            if ($request->ajax() && !$request->header('X-Inertia')) {
                return response()->json([
                    'success' => false,
                    'errors' => $result['errors'] ?? [],
                    'message' => $result['message'] ?? 'Failed to update ' . $this->recordTitle,
                ], 422);
            }

            // For Inertia requests, return back with errors
            return back()
                ->withInput()
                ->withErrors(['general' => $result['message'] ?? 'Failed to update ' . $this->recordTitle]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            // Handle validation errors
            if ($request->ajax() && !$request->header('X-Inertia')) {
                return response()->json([
                    'success' => false,
                    'errors' => $e->errors(),
                    'message' => 'Validation failed',
                ], 422);
            }

            // For Inertia requests, throw the exception and let Inertia handle it
            throw $e;
        }
    }

    public function destroy($id)
    {
        $result = ($this->deleteAction)($id);

        if ($result['success']) {
            return redirect()
                ->route($this->recordType . '.index')
                ->with('success', $this->domainName . ' deleted successfully');
        }

        return back()
            ->with('error', $result['message'] ?? 'Failed to delete ' . $this->recordTitle);
    }

    public function lookup(Request $request)
    {
        // Determine which columns to select
        $columns = ['id', 'display_name'];

        $query = $this->recordModel->select($columns);

        // Apply search query
        $searchQuery = $request->get('search');
        if ($searchQuery && !empty(trim($searchQuery))) {
            // Default search on display_name
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

    protected function getEnumOptions(): array
    {
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $enumOptions = [];

        foreach ($fieldsSchema as $fieldKey => $fieldDef) {
            if (isset($fieldDef['enum']) && $fieldDef['enum']) {
                $enumClass = $fieldDef['enum'];

                if (class_exists($enumClass) && method_exists($enumClass, 'options')) {
                    $enumOptions[$enumClass] = $enumClass::options();
                } elseif (class_exists($enumClass)) {
                    // Fallback: if no options method, create from cases
                    $enumOptions[$enumClass] = array_map(fn($case) => [
                        'id' => $case->value,
                        'name' => $case->name ?? $case->value,
                    ], $enumClass::cases());
                }
            }
        }

        return $enumOptions;
    }

    /**
     * Attach a related record via Many-to-Many relationship
     */
    public function attachRelationship(Request $request, $id)
    {
        $request->validate([
            'relationship' => 'required|string',
            'related_id' => 'required|integer',
        ]);

        try {
            $record = $this->recordModel->findOrFail($id);
            $relationship = $request->input('relationship');
            $relatedId = $request->input('related_id');

            // Check if relationship exists and is a BelongsToMany
            if (!method_exists($record, $relationship)) {
                return response()->json([
                    'success' => false,
                    'message' => "Relationship '{$relationship}' does not exist on this model."
                ], 400);
            }

            $relationshipInstance = $record->$relationship();
            
            if (!($relationshipInstance instanceof \Illuminate\Database\Eloquent\Relations\BelongsToMany)) {
                return response()->json([
                    'success' => false,
                    'message' => "Relationship '{$relationship}' is not a Many-to-Many relationship."
                ], 400);
            }

            // Check if already attached
            if ($relationshipInstance->where($relationshipInstance->getRelated()->getQualifiedKeyName(), $relatedId)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => "This record is already attached."
                ], 400);
            }

            // Attach the record
            $relationshipInstance->attach($relatedId);

            return response()->json([
                'success' => true,
                'message' => 'Record attached successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to attach record: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Detach a related record via Many-to-Many relationship
     */
    public function detachRelationship(Request $request, $id)
    {
        $request->validate([
            'relationship' => 'required|string',
            'related_id' => 'required|integer',
        ]);

        try {
            $record = $this->recordModel->findOrFail($id);
            $relationship = $request->input('relationship');
            $relatedId = $request->input('related_id');

            // Check if relationship exists and is a BelongsToMany
            if (!method_exists($record, $relationship)) {
                return response()->json([
                    'success' => false,
                    'message' => "Relationship '{$relationship}' does not exist on this model."
                ], 400);
            }

            $relationshipInstance = $record->$relationship();
            
            if (!($relationshipInstance instanceof \Illuminate\Database\Eloquent\Relations\BelongsToMany)) {
                return response()->json([
                    'success' => false,
                    'message' => "Relationship '{$relationship}' is not a Many-to-Many relationship."
                ], 400);
            }

            // Detach the record
            $relationshipInstance->detach($relatedId);

            return response()->json([
                'success' => true,
                'message' => 'Record detached successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to detach record: ' . $e->getMessage()
            ], 500);
        }
    }

}
