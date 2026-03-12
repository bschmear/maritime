<?php
namespace App\Http\Controllers\Tenant;
use App\Http\Controllers\Tenant\RecordController;
use App\Domain\QualityControl\Models\QualityControl as RecordModel;
use App\Domain\QualityControl\Actions\CreateQualityControl as CreateAction;
use App\Domain\QualityControl\Actions\UpdateQualityControl as UpdateAction;
use App\Domain\QualityControl\Actions\DeleteQualityControl as DeleteAction;
use Illuminate\Http\Request;

class QualityControlController extends RecordController
{
    protected $recordType = 'QualityControl';
    protected $table = null;

    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'qualitycontrols',
            'QualityControl',
            new RecordModel(),
            new CreateAction(),
            new UpdateAction(),
            new DeleteAction(),
            $this->recordType // Domain name for schema lookup
        );
    }
}