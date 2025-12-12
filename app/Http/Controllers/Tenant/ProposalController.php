<?php
namespace App\Http\Controllers\Tenant;
use App\Http\Controllers\Tenant\RecordController;
use App\Domain\Proposal\Models\Proposal as RecordModel;
use App\Domain\Proposal\Actions\CreateProposal as CreateAction;
use App\Domain\Proposal\Actions\UpdateProposal as UpdateAction;
use App\Domain\Proposal\Actions\DeleteProposal as DeleteAction;
use Illuminate\Http\Request;

class ProposalController extends RecordController
{
    protected $recordType = 'Proposal';
    protected $table = null;

    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'proposals',
            'Proposal',
            new RecordModel(),
            new CreateAction(),
            new UpdateAction(),
            new DeleteAction(),
            $this->recordType // Domain name for schema lookup
        );
    }
}