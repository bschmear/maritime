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

    public function index(Request $request)
    {
        $columns = $this->getSchemaColumns();
        $fieldsSchema = $this->getFieldsSchema();
        $schema = $this->getTableSchema();
        $formSchema = $this->getFormSchema();
        $enumOptions = $this->getEnumOptions();

        if (!in_array('id', $columns)) {
            $columns[] = 'id';
        }

        $relationships = $this->getRelationshipsToLoad($fieldsSchema);

        foreach ($fieldsSchema as $fieldKey => $fieldDef) {
            if (isset($fieldDef['type']) && $fieldDef['type'] === 'record' && isset($fieldDef['typeDomain'])) {
                $relationshipName = $fieldDef['relationship'] ?? str_replace('_id', '', $fieldKey);

                $relationships[$relationshipName] = function ($query) {
                    $query->select('id', 'display_name');
                };
            }
        }

        $query = $this->recordModel->select($columns)->with($relationships);

        $searchQuery = $request->get('search');
        if ($searchQuery && !empty(trim($searchQuery))) {
            $query->whereRaw('LOWER(display_name) LIKE ?', ['%' . strtolower(trim($searchQuery)) . '%']);
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

        $query->orderByRaw('LOWER(display_name) ASC');

        $perPage = $request->get('per_page', 15);
        $records = $query->paginate($perPage);

        // Return JSON for Inertia / AJAX / JSON requests only
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
        $fieldsSchema = $this->getFieldsSchema();
        $enumOptions = $this->getEnumOptions();

        return inertia('Tenant/' . $this->domainName . '/Create', [
            'recordType' => $this->recordType,
            'formSchema' => $formSchema,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
        ]);
    }

    public function store(Request $request, PublicStorage $publicStorage)
    {
        try {
            $data = $request->all();
            $fieldsSchema = $this->getFieldsSchema();

            // Handle image uploads
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
                    $fieldsSchema = $this->getFieldsSchema();
                    $relationships = $this->getRelationshipsToLoad($fieldsSchema);

                    // Add record type relationships with only id and display_name
                    foreach ($fieldsSchema as $fieldKey => $fieldDef) {
                        if (isset($fieldDef['type']) && $fieldDef['type'] === 'record' && isset($fieldDef['typeDomain'])) {
                            if (isset($fieldDef['type']) && $fieldDef['type'] === 'record' && isset($fieldDef['typeDomain'])) {
                                $relationshipName = $fieldDef['relationship'] ?? str_replace('_id', '', $fieldKey);
                                $relationships[$relationshipName] = function ($query) {
                                    $query->select('id', 'display_name');
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
        $fieldsSchema = $this->getFieldsSchema();

        // Build relationships array including both morph and record types
        $relationships = $this->getRelationshipsToLoad($fieldsSchema);

        // Add record type relationships with only id and display_name
        foreach ($fieldsSchema as $fieldKey => $fieldDef) {
            if (isset($fieldDef['type']) && $fieldDef['type'] === 'record' && isset($fieldDef['typeDomain'])) {
                // Convert field key like 'assigned_id' to relationship name like 'assigned'
                $relationshipName = $fieldDef['relationship'] ?? str_replace('_id', '', $fieldKey);
                // Only select id and display_name for the related record
                $relationships[$relationshipName] = function ($query) {
                    $query->select('id', 'display_name');
                };
            }
        }

        // Load the record with relationships
        $record = $this->recordModel
            ->with($relationships)
            ->findOrFail($id);

        $formSchema = $this->getFormSchema();
        $enumOptions = $this->getEnumOptions();

        // If it's a non-Inertia AJAX request, return JSON with full record data
        if ($request->ajax() && !$request->header('X-Inertia')) {
            return response()->json([
                'record' => $record,
                'recordType' => $this->recordType,
                'formSchema' => $formSchema,
                'fieldsSchema' => $fieldsSchema,
                'enumOptions' => $enumOptions,
                'imageUrls' => $this->getImageUrls($record, $fieldsSchema),
            ]);
        }

        // Return Inertia response (for navigation and partial reloads)
        return inertia('Tenant/' . $this->domainName . '/Show', [
            'record' => $record,
            'recordType' => $this->recordType,
            'formSchema' => $formSchema,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
            'imageUrls' => $this->getImageUrls($record, $fieldsSchema),
        ]);
    }

    public function edit($id)
    {
        $record = $this->recordModel->findOrFail($id);
        $formSchema = $this->getFormSchema();
        $fieldsSchema = $this->getFieldsSchema();
        $enumOptions = $this->getEnumOptions();

        return inertia('Tenant/' . $this->domainName . '/Edit', [
            'record' => $record,
            'recordType' => $this->recordType,
            'formSchema' => $formSchema,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
            'imageUrls' => $this->getImageUrls($record, $fieldsSchema),
        ]);
    }

    public function update(Request $request, $id, PublicStorage $publicStorage)
    {
        try {
            $data = $request->all();
            $fieldsSchema = $this->getFieldsSchema();
            // Handle image uploads
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
                    $fieldsSchema = $this->getFieldsSchema();
                    $relationships = $this->getRelationshipsToLoad($fieldsSchema);

                    // Add record type relationships with only id and display_name
                    foreach ($fieldsSchema as $fieldKey => $fieldDef) {
                        if (isset($fieldDef['type']) && $fieldDef['type'] === 'record' && isset($fieldDef['typeDomain'])) {
                            $relationshipName = $fieldDef['relationship'] ?? str_replace('_id', '', $fieldKey);
                            $relationships[$relationshipName] = function ($query) {
                                $query->select('id', 'display_name');
                            };
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
        // Simple lookup - just return id and display_name
        $columns = ['id', 'display_name'];

        $query = $this->recordModel->select($columns);

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
