<?php
namespace App\Http\Controllers\Tenant;
use App\Http\Controllers\Tenant\RecordController;
use App\Domain\Document\Models\Document as RecordModel;
use App\Domain\Document\Actions\CreateDocument as CreateAction;
use App\Domain\Document\Actions\UpdateDocument as UpdateAction;
use App\Domain\Document\Actions\DeleteDocument as DeleteAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class DocumentController extends RecordController
{
    protected $recordType = 'Document';
    protected $table = null;

    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'documents',
            'Document',
            new RecordModel(),
            new CreateAction(),
            new UpdateAction(),
            new DeleteAction(),
            $this->recordType // Domain name for schema lookup
        );
    }

    public function show(Request $request, $id)
    {
        $fieldsSchema = $this->getFieldsSchema();
        $record = $this->recordModel->with($this->getRelationshipsToLoad($fieldsSchema))->findOrFail($id);
        $formSchema = $this->getFormSchema();
        $fieldsSchema = $this->getFieldsSchema();
        $enumOptions = $this->getEnumOptions();

        $imgUrls = $this->getImageUrls($record, $fieldsSchema);

        // Get file information
        $fileExtension = '';
        $fileSize = 0;
        $previewUrl = null;
        $downloadUrl = null;
        $canPreview = false;

        if ($record->file && Storage::disk('public')->exists($record->file)) {
            // Get file extension
            $fileExtension = strtolower(pathinfo($record->file, PATHINFO_EXTENSION));
            
            // Get file size
            $fileSize = Storage::disk('public')->size($record->file);
            
            // Generate URLs
            $downloadUrl = Storage::disk('public')->url($record->file);
            
            // Check if file can be previewed
            $previewableExtensions = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];
            if (in_array($fileExtension, $previewableExtensions)) {
                $canPreview = true;
                $previewUrl = $downloadUrl;
            }
        }

        // If it's a non-Inertia AJAX request, return JSON
        if ($request->ajax() && !$request->header('X-Inertia')) {
            return response()->json([
                'record' => $record,
                'recordType' => $this->recordType,
                'formSchema' => $formSchema,
                'fieldsSchema' => $fieldsSchema,
                'enumOptions' => $enumOptions,
                'imageUrls' => $imgUrls,
                'previewUrl' => $previewUrl,
                'downloadUrl' => $downloadUrl,
                'canPreview' => $canPreview,
                'fileExtension' => $fileExtension,
                'fileSize' => $fileSize,
            ]);
        }

        return inertia('Tenant/' . $this->domainName . '/Show', [
            'record' => $record,
            'recordType' => $this->recordType,
            'recordTitle' => $this->recordTitle,
            'formSchema' => $formSchema,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
            'imageUrls' => $imgUrls,
            'previewUrl' => $previewUrl,
            'downloadUrl' => $downloadUrl,
            'canPreview' => $canPreview,
            'fileExtension' => $fileExtension,
            'fileSize' => $fileSize,
        ]);
    }

    /**
     * Search documents for attachment
     */
    public function search(Request $request)
    {
        $query = $request->get('q', '');
        $excludeAttachedTo = $request->get('exclude_attached_to', '');
        $limit = $request->get('limit', 20);

        $documentsQuery = $this->recordModel->query()
            ->when($query, function ($q) use ($query) {
                $q->whereRaw('LOWER(display_name) LIKE ?', ['%' . strtolower($query) . '%']);
            });
        
        // Exclude documents already attached to this specific parent
        if ($excludeAttachedTo) {
            [$type, $id] = explode(':', $excludeAttachedTo);
            $modelClass = "App\\Domain\\{$type}\\Models\\{$type}";
            
            // Use whereNotExists with a subquery on the pivot table
            $documentsQuery->whereNotExists(function ($query) use ($modelClass, $id) {
                $query->select(DB::raw(1))
                      ->from('documentables')
                      ->whereColumn('documentables.document_id', 'documents.id')
                      ->where('documentables.documentable_type', $modelClass)
                      ->where('documentables.documentable_id', $id);
            });
        }
        
        $documents = $documentsQuery
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return response()->json([
            'documents' => $documents
        ]);
    }

    /**
     * Upload and attach a document to a model
     */
    public function uploadAttach(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:jpeg,png,jpg,gif,svg,pdf,doc,docx,csv,txt,xlsx,excel,x-excel,x-msexcel|max:51200',
            'display_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'attach_to_type' => 'required|string',
            'attach_to_id' => 'required|integer',
        ]);

        try {
            // Create the document and attach it
            $result = ($this->createAction)([
                'file' => $request->file('file'),
                'display_name' => $request->input('display_name'),
                'description' => $request->input('description'),
                'assigned_id' => auth()->id(),
            ]);

            if ($result['success']) {
                // Attach to the parent model
                $attachToType = $request->input('attach_to_type');
                $attachToId = $request->input('attach_to_id');

                $parentModel = app($attachToType)->find($attachToId);
                if ($parentModel && method_exists($parentModel, 'attachDocument')) {
                    $parentModel->attachDocument($result['record']);
                }

                return response()->json([
                    'success' => true,
                    'document' => $result['record'],
                    'message' => 'Document uploaded and attached successfully.'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'Failed to upload document.'
                ], 422);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while uploading the document.'
            ], 500);
        }
    }

    /**
     * Attach an existing document to a model
     */
    public function attach(Request $request)
    {
        $request->validate([
            'document_id' => 'required|exists:documents,id',
            'documentable_type' => 'required|string',
            'documentable_id' => 'required|integer',
        ]);

        try {
            $document = $this->recordModel->findOrFail($request->input('document_id'));
            $documentableType = $request->input('documentable_type');
            $documentableId = $request->input('documentable_id');

            // Get the parent model and attach the document
            $parentModel = app($documentableType)->find($documentableId);
            if ($parentModel && method_exists($parentModel, 'attachDocument')) {
                $parentModel->attachDocument($document);

                return response()->json([
                    'success' => true,
                    'message' => 'Document attached successfully.'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Unable to attach document.'
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while attaching the document.'
            ], 500);
        }
    }

    /**
     * Detach a document from a model
     */
    public function detach(Request $request)
    {
        $request->validate([
            'document_id' => 'required|exists:documents,id',
            'documentable_type' => 'required|string',
            'documentable_id' => 'required|integer',
        ]);

        try {
            $document = $this->recordModel->findOrFail($request->input('document_id'));
            $documentableType = $request->input('documentable_type');
            $documentableId = $request->input('documentable_id');

            // Get the parent model and detach the document
            $parentModel = app($documentableType)->find($documentableId);
            if ($parentModel && method_exists($parentModel, 'detachDocument')) {
                $parentModel->detachDocument($document);

                return response()->json([
                    'success' => true,
                    'message' => 'Document detached successfully.'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Unable to detach document.'
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while detaching the document.'
            ], 500);
        }
    }

    /**
     * Download a document
     */
    public function download(Request $request, $id)
    {
        try {
            $document = $this->recordModel->findOrFail($id);

            // Check if user has access to this document
            // For now, allow download if they can access it through relationships
            $canAccess = true; // TODO: Implement proper access control

            if (!$canAccess) {
                abort(403, 'Access denied.');
            }

            if (!$document->file || !Storage::disk('s3')->exists($document->file)) {
                abort(404, 'File not found.');
            }

            return Storage::disk('s3')->download($document->file, $document->display_name);
        } catch (\Exception $e) {
            abort(404, 'Document not found.');
        }
    }
}