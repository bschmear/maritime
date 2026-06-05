<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Contact\Models\Contact;
use App\Domain\Customer\Models\Customer;
use App\Domain\Document\Actions\CreateDocument as CreateAction;
use App\Domain\Document\Actions\DeleteDocument as DeleteAction;
use App\Domain\Document\Actions\UpdateDocument as UpdateAction;
use App\Domain\Document\Models\Document as RecordModel;
use App\Domain\Lead\Models\Lead;
use App\Domain\WarrantyClaim\Models\WarrantyClaim;
use App\Domain\Document\Support\DocumentableTypes;
use App\Domain\Document\Support\TenantDocumentAccess;
use App\Support\ContactDocumentLinker;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
            new RecordModel,
            new CreateAction,
            new UpdateAction,
            new DeleteAction,
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
        if ($request->ajax() && ! $request->header('X-Inertia')) {
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

        return inertia('Tenant/'.$this->domainName.'/Show', [
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
                $q->whereRaw('LOWER(display_name) LIKE ?', ['%'.strtolower($query).'%']);
            });

        if ($excludeAttachedTo) {
            [$type, $id] = explode(':', $excludeAttachedTo, 2);
            ContactDocumentLinker::applyExcludeAttachedToFilter($documentsQuery, $type, (int) $id);
        }

        $documents = $documentsQuery
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return response()->json([
            'documents' => $documents,
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
            'visible_to_customer' => 'sometimes|boolean',
            'visible_to_vendor' => 'sometimes|boolean',
        ]);

        $visibleToCustomer = $request->boolean('visible_to_customer');
        $visibleToVendor = $request->boolean('visible_to_vendor');

        try {
            // Create the document and attach it
            $result = ($this->createAction)([
                'file' => $request->file('file'),
                'display_name' => $request->input('display_name'),
                'description' => $request->input('description'),
                'assigned_id' => current_tenant_user_id(),
            ]);

            if ($result['success']) {
                // Attach to the parent model
                $parentModel = DocumentableTypes::resolveModel(
                    $request->input('attach_to_type'),
                    (int) $request->input('attach_to_id'),
                );
                if ($parentModel) {
                    $this->attachDocumentToParent($parentModel, $result['record'], $visibleToCustomer, $visibleToVendor);
                }

                return $this->clusterAwareJson($parentModel, [
                    'success' => true,
                    'document' => $result['record'],
                    'message' => 'Document uploaded and attached successfully.',
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'Failed to upload document.',
                ], 422);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while uploading the document.',
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
            $parentModel = DocumentableTypes::resolveModel(
                $request->input('documentable_type'),
                (int) $request->input('documentable_id'),
            );
            if ($parentModel) {
                $this->attachDocumentToParent($parentModel, $document);

                return $this->clusterAwareJson($parentModel, [
                    'success' => true,
                    'message' => 'Document attached successfully.',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Invalid document parent.',
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while attaching the document.',
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
            $parentModel = DocumentableTypes::resolveModel(
                $request->input('documentable_type'),
                (int) $request->input('documentable_id'),
            );
            if ($parentModel) {
                $this->detachDocumentFromParent($parentModel, $document);

                return $this->clusterAwareJson($parentModel, [
                    'success' => true,
                    'message' => 'Document detached successfully.',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Unable to detach document.',
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while detaching the document.',
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

            if (! TenantDocumentAccess::tenantCanDownload($document)) {
                abort(403, 'Access denied.');
            }

            if (! $document->file || ! Storage::disk('s3')->exists($document->file)) {
                abort(404, 'File not found.');
            }

            return Storage::disk('s3')->download($document->file, $document->display_name);
        } catch (\Exception $e) {
            abort(404, 'Document not found.');
        }
    }

    private function attachDocumentToParent(
        Model $parentModel,
        RecordModel $document,
        bool $visibleToCustomer = false,
        bool $visibleToVendor = false,
    ): void {
        $contact = ContactDocumentLinker::resolveContact($parentModel::class, (int) $parentModel->getKey());
        if ($contact) {
            ContactDocumentLinker::syncAttach($document, $contact, [
                'visible_to_customer' => false,
            ]);
            if ($visibleToCustomer && $contact->customer) {
                ContactDocumentLinker::attachToCustomerOnly($document, $contact->customer, true);
            }

            return;
        }

        if (method_exists($parentModel, 'attachDocument')) {
            $parentModel->attachDocument($document, [
                'visible_to_customer' => $visibleToCustomer,
                'visible_to_vendor' => $visibleToVendor,
            ]);
        }
    }

    public function updatePivot(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'document_id' => 'required|exists:documents,id',
            'documentable_type' => 'required|string',
            'documentable_id' => 'required|integer',
            'visible_to_customer' => 'sometimes',
            'visible_to_vendor' => 'sometimes',
        ]);

        if (! array_key_exists('visible_to_customer', $validated) && ! array_key_exists('visible_to_vendor', $validated)) {
            return response()->json([
                'success' => false,
                'message' => 'A visibility field is required.',
            ], 422);
        }

        $document = $this->recordModel->findOrFail($validated['document_id']);
        $parentModel = DocumentableTypes::resolveModel(
            $validated['documentable_type'],
            (int) $validated['documentable_id'],
        );
        if (! $parentModel) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid document parent.',
            ], 422);
        }

        $contact = ContactDocumentLinker::resolveContactFromRecord($parentModel);
        if ($contact) {
            if (! array_key_exists('visible_to_customer', $validated)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer visibility is required for this record.',
                ], 422);
            }

            $visible = in_array($validated['visible_to_customer'], [true, 1, '1', 'true', 'on', 'yes'], true);

            if (! ContactDocumentLinker::documentInContactCluster($contact, $document)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Document is not attached to this record.',
                ], 422);
            }

            ContactDocumentLinker::setClusterVisibility($document, $parentModel, $visible);
        } elseif (method_exists($parentModel, 'updateDocumentPivot') && $parentModel->hasDocument($document)) {
            $pivot = [];
            if (array_key_exists('visible_to_customer', $validated)) {
                $pivot['visible_to_customer'] = in_array($validated['visible_to_customer'], [true, 1, '1', 'true', 'on', 'yes'], true);
            }
            if (array_key_exists('visible_to_vendor', $validated)) {
                $pivot['visible_to_vendor'] = in_array($validated['visible_to_vendor'], [true, 1, '1', 'true', 'on', 'yes'], true);
            }
            $parentModel->updateDocumentPivot($document, $pivot);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Document is not attached to this record.',
            ], 422);
        }

        return $this->clusterAwareJson($parentModel, [
            'success' => true,
            'message' => 'Document visibility updated.',
        ]);
    }

    private function detachDocumentFromParent(Model $parentModel, RecordModel $document): void
    {
        $contact = ContactDocumentLinker::resolveContact($parentModel::class, (int) $parentModel->getKey());
        if ($contact) {
            ContactDocumentLinker::syncDetach($document, $contact);

            return;
        }

        if (method_exists($parentModel, 'detachDocument')) {
            $parentModel->detachDocument($document);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function clusterAwareJson(?Model $parentModel, array $payload): JsonResponse
    {
        if ($parentModel instanceof Contact || $parentModel instanceof Customer || $parentModel instanceof Lead) {
            $domain = match (true) {
                $parentModel instanceof Contact => 'Contact',
                $parentModel instanceof Customer => 'Customer',
                default => 'Lead',
            };
            $payload['documents'] = ContactDocumentLinker::documentsPayloadForDomain(
                $domain,
                (int) $parentModel->getKey()
            );
        } elseif ($parentModel instanceof WarrantyClaim) {
            ContactDocumentLinker::hydrateDocumentsRelationIfApplicable($parentModel);
            $payload['documents'] = $parentModel->getRelation('documents') ?? [];
        }

        return response()->json($payload);
    }
}
