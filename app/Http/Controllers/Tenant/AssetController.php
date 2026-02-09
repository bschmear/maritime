<?php
namespace App\Http\Controllers\Tenant;
use App\Http\Controllers\Tenant\RecordController;
use App\Domain\Asset\Models\Asset as RecordModel;
use App\Domain\Asset\Actions\CreateAsset as CreateAction;
use App\Domain\Asset\Actions\UpdateAsset as UpdateAction;
use App\Domain\Asset\Actions\DeleteAsset as DeleteAction;
use App\Enums\RecordType;
use Illuminate\Http\Request;

class AssetController extends RecordController
{
    public function __construct(Request $request)
    {
        $recordType = RecordType::Asset;
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