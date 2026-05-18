<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\License\Actions\CreateLicense as CreateAction;
use App\Domain\License\Actions\DeleteLicense as DeleteAction;
use App\Domain\License\Actions\UpdateLicense as UpdateAction;
use App\Domain\License\Models\License as RecordModel;
use Illuminate\Http\Request;

class LicenseController extends RecordController
{
    protected $recordType = 'License';

    protected $table = null;

    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'licenses',
            'License',
            new RecordModel,
            new CreateAction,
            new UpdateAction,
            new DeleteAction,
            $this->recordType // Domain name for schema lookup
        );
    }
}
