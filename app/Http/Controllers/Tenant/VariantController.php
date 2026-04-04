<?php
namespace App\Http\Controllers\Tenant;
use App\Http\Controllers\Tenant\RecordController;
use App\Domain\Variant\Models\Variant as RecordModel;
use App\Domain\Variant\Actions\CreateVariant as CreateAction;
use App\Domain\Variant\Actions\UpdateVariant as UpdateAction;
use App\Domain\Variant\Actions\DeleteVariant as DeleteAction;
use Illuminate\Http\Request;

class VariantController extends RecordController
{
    protected $recordType = 'Variant';
    protected $table = null;

    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'variants',
            'Variant',
            new RecordModel(),
            new CreateAction(),
            new UpdateAction(),
            new DeleteAction(),
            $this->recordType // Domain name for schema lookup
        );
    }
}