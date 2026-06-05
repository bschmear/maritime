<?php

declare(strict_types=1);

namespace App\Http\Requests\Portal;

class VendorLoginRequest extends PortalLoginRequest
{
    protected function guardName(): string
    {
        return 'vendor';
    }
}
