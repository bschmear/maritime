<?php
namespace App\Http\Controllers\Tenant;
use App\Http\Controllers\Tenant\RecordController;
use App\Domain\Document\Models\Document as RecordModel;
use App\Domain\Document\Actions\CreateDocument as CreateAction;
use App\Domain\Document\Actions\UpdateDocument as UpdateAction;
use App\Domain\Document\Actions\DeleteDocument as DeleteAction;
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
}