<?php
namespace App\Http\Controllers\Tenant;
use App\Http\Controllers\Tenant\RecordController;
use App\Domain\InventoryItem\Models\InventoryItem as RecordModel;
use App\Domain\InventoryItem\Actions\CreateInventoryItem as CreateAction;
use App\Domain\InventoryItem\Actions\UpdateInventoryItem as UpdateAction;
use App\Domain\InventoryItem\Actions\DeleteInventoryItem as DeleteAction;
use Illuminate\Http\Request;

class InventoryItemController extends RecordController
{
    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'inventoryitems',     // recordType (route parameter name)
            'InventoryItem',      // recordTitle (display name)
            new RecordModel(),    // Model instance
            new CreateAction(),   // Create action
            new UpdateAction(),   // Update action
            new DeleteAction(),   // Delete action
            'InventoryItem'       // domainName (for schema lookup)
        );
    }

    /**
     * Override show method to load documents relationship
     */
    public function show(Request $request, $id)
    {
        $fieldsSchema = $this->getFieldsSchema();
        $record = $this->recordModel->with([
            'documents' => function ($query) {
                $query->orderBy('created_at', 'desc');
            }
        ])->findOrFail($id);

        $formSchema = $this->getFormSchema();
        $fieldsSchema = $this->getFieldsSchema();
        $enumOptions = $this->getEnumOptions();

        $imgUrls = $this->getImageUrls($record, $fieldsSchema);

        // If it's a non-Inertia AJAX request, return JSON
        if ($request->ajax() && !$request->header('X-Inertia')) {
            return response()->json([
                'record' => $record,
                'recordType' => $this->recordType,
                'formSchema' => $formSchema,
                'fieldsSchema' => $fieldsSchema,
                'enumOptions' => $enumOptions,
                'imageUrls' => $imgUrls,
            ]);
        }

        return inertia('Tenant/' . $this->domainName . '/Show', [
            'record' => $record,
            'recordType' => $this->recordType,
            'recordTitle' => $this->recordTitle,
            'formSchema' => $formSchema,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
            'domainName' => $this->domainName,
            'imageUrls' => $imgUrls,
        ]);
    }
}
