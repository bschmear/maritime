<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\AssetOptionCategory\Actions\CreateAssetOptionCategory as CreateAction;
use App\Domain\AssetOptionCategory\Actions\DeleteAssetOptionCategory as DeleteAction;
use App\Domain\AssetOptionCategory\Actions\UpdateAssetOptionCategory as UpdateAction;
use App\Domain\AssetOptionCategory\Models\AssetOptionCategory as RecordModel;
use Illuminate\Http\Request;

class AssetOptionCategoryController extends RecordController
{
    protected $recordType = 'AssetOptionCategory';

    protected $table = null;

    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'asset-option-categories',
            'Asset option category',
            new RecordModel,
            new CreateAction,
            new UpdateAction,
            new DeleteAction,
            $this->recordType
        );
    }
}
