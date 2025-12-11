<?php
namespace App\Http\Controllers\Tenant;
use App\Http\Controllers\Tenant\RecordController;
use App\Domain\Document\Models\Document as RecordModel;
use App\Domain\Document\Actions\CreateDocument as CreateAction;
use App\Domain\Document\Actions\UpdateDocument as UpdateAction;
use App\Domain\Document\Actions\DeleteDocument as DeleteAction;
use Illuminate\Http\Request;

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
}