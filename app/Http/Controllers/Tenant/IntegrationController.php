<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Integration\Actions\CreateIntegration as CreateAction;
use App\Domain\Integration\Actions\DeleteIntegration as DeleteAction;
use App\Domain\Integration\Actions\UpdateIntegration as UpdateAction;
use App\Domain\Integration\Models\Integration as RecordModel;
use Illuminate\Http\Request;

class IntegrationController extends RecordController
{
    protected $recordType = 'Integration';

    protected $table = null;

    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'integrations',
            'Integration',
            new RecordModel,
            new CreateAction,
            new UpdateAction,
            new DeleteAction,
            $this->recordType // Domain name for schema lookup
        );
    }
}
