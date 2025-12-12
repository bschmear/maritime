<?php
namespace App\Http\Controllers\Tenant;
use App\Http\Controllers\Tenant\RecordController;
use App\Domain\Quote\Models\Quote as RecordModel;
use App\Domain\Quote\Actions\CreateQuote as CreateAction;
use App\Domain\Quote\Actions\UpdateQuote as UpdateAction;
use App\Domain\Quote\Actions\DeleteQuote as DeleteAction;
use Illuminate\Http\Request;

class QuoteController extends RecordController
{
    protected $recordType = 'Quote';
    protected $table = null;

    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'quotes',
            'Quote',
            new RecordModel(),
            new CreateAction(),
            new UpdateAction(),
            new DeleteAction(),
            $this->recordType // Domain name for schema lookup
        );
    }
}