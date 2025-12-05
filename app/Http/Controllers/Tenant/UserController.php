<?php
namespace App\Http\Controllers\Tenant;
use App\Http\Controllers\Tenant\RecordController;
use Domain\User\Models\User as RecordModel;
use Domain\User\Actions\CreateUser as CreateAction;
use Domain\User\Actions\UpdateUser as UpdateAction;
use Domain\User\Actions\DeleteUser as DeleteAction;
use Illuminate\Http\Request;

class UserController extends RecordController
{
    protected $recordType = 'User';
    protected $table = null;

    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'users',
            'User',
            new RecordModel(),
            new CreateAction(),
            new UpdateAction(),
            new DeleteAction(),
            $this->recordType // Domain name for schema lookup
        );
    }
}