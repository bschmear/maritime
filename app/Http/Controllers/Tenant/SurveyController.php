<?php
namespace App\Http\Controllers\Tenant;
use App\Http\Controllers\Tenant\RecordController;
use App\Domain\Survey\Models\Survey as RecordModel;
use App\Domain\Survey\Actions\CreateSurvey as CreateAction;
use App\Domain\Survey\Actions\UpdateSurvey as UpdateAction;
use App\Domain\Survey\Actions\DeleteSurvey as DeleteAction;
use Illuminate\Http\Request;

class SurveyController extends RecordController
{
    protected $recordType = 'Survey';
    protected $table = null;

    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'surveys',
            'Survey',
            new RecordModel(),
            new CreateAction(),
            new UpdateAction(),
            new DeleteAction(),
            $this->recordType // Domain name for schema lookup
        );
    }
}