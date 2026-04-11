<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\ContactAddress\Actions\CreateContactAddress;
use App\Domain\ContactAddress\Actions\DeleteContactAddress;
use App\Domain\ContactAddress\Actions\UpdateContactAddress;
use App\Domain\ContactAddress\Models\ContactAddress as RecordModel;
use Illuminate\Http\Request;

class ContactAddressController extends RecordController
{
    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'contactaddresses',
            'ContactAddress',
            new RecordModel,
            new CreateContactAddress,
            new UpdateContactAddress,
            new DeleteContactAddress,
            'ContactAddress'
        );
    }
}
