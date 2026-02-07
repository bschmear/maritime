<?php
namespace App\Http\Controllers\Tenant;
use App\Http\Controllers\Tenant\RecordController;
use App\Domain\Subsidiary\Models\Subsidiary as RecordModel;
use App\Domain\Subsidiary\Actions\CreateSubsidiary as CreateAction;
use App\Domain\Subsidiary\Actions\UpdateSubsidiary as UpdateAction;
use App\Domain\Subsidiary\Actions\DeleteSubsidiary as DeleteAction;
use App\Enums\RecordType;
use Illuminate\Http\Request;

class SubsidiaryController extends RecordController
{
    public function __construct(Request $request)
    {
        $recordType = RecordType::Subsidiary;

        parent::__construct(
            $request,
            $recordType->plural(), // 'subsidiaries'
            $recordType->domainName(), // 'Subsidiary'
            new RecordModel(),
            new CreateAction(),
            new UpdateAction(),
            new DeleteAction(),
            $recordType->domainName() // 'Subsidiary'
        );
    }
}
