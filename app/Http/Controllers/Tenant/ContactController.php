<?php
namespace App\Http\Controllers\Tenant;
use App\Http\Controllers\Tenant\RecordController;
use Domain\Contact\Models\Contact as RecordModel;
use Domain\Contact\Actions\CreateContact as CreateAction;
use Domain\Contact\Actions\UpdateContact as UpdateAction;
use Domain\Contact\Actions\DestroyContact as DeleteAction;
use Illuminate\Http\Request;

class ContactController extends RecordController
{
    protected $recordType = 'Contact';
    protected $table = 'contacts';

    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'contacts', //$recordType
            'Contact', //$typeTitle
            new RecordModel(),
            new CreateAction(),
            new UpdateAction(),
            new DeleteAction(),
            $this->recordType //$domainName
        );
    }
}
