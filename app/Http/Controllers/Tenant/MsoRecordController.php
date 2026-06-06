<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\MsoRecord\Actions\CreateMsoRecord as CreateAction;
use App\Domain\MsoRecord\Actions\DeleteMsoRecord as DeleteAction;
use App\Domain\MsoRecord\Actions\UpdateMsoRecord as UpdateAction;
use App\Domain\MsoRecord\Models\MsoRecord as RecordModel;
use Illuminate\Http\Request;

class MsoRecordController extends RecordController
{
    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'msorecords',
            'MSO',
            new RecordModel,
            new CreateAction,
            new UpdateAction,
            new DeleteAction,
            'MsoRecord'
        );
    }
}
