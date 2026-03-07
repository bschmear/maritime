<?php
namespace App\Http\Controllers\Tenant;
use App\Http\Controllers\Tenant\RecordController;
use App\Domain\Qualification\Models\Qualification as RecordModel;
use App\Domain\Qualification\Actions\CreateQualification as CreateAction;
use App\Domain\Qualification\Actions\UpdateQualification as UpdateAction;
use App\Domain\Qualification\Actions\DeleteQualification as DeleteAction;
use Illuminate\Http\Request;

class QualificationController extends RecordController
{
    protected $recordType = 'Qualification';
    protected $table = null;

    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'qualifications',
            'Qualification',
            new RecordModel(),
            new CreateAction(),
            new UpdateAction(),
            new DeleteAction(),
            $this->recordType
        );
    }
}
