<?php
namespace App\Http\Controllers\Tenant;
use App\Http\Controllers\Tenant\RecordController;
use App\Domain\ServiceItem\Models\ServiceItem as RecordModel;
use App\Domain\ServiceItem\Actions\CreateServiceItem as CreateAction;
use App\Domain\ServiceItem\Actions\UpdateServiceItem as UpdateAction;
use App\Domain\ServiceItem\Actions\DeleteServiceItem as DeleteAction;
use App\Enums\RecordType;
use Illuminate\Http\Request;

class ServiceItemController extends RecordController
{
    public function __construct(Request $request)
    {
        $recordType = RecordType::ServiceItem;
        parent::__construct(
            $request,
            $recordType->plural(),
            $recordType->title(),
            new RecordModel(),
            new CreateAction(),
            new UpdateAction(),
            new DeleteAction(),
            $recordType->domainName()
        );
    }
}
