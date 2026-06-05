<?php

declare(strict_types=1);

namespace App\Http\Requests\Portal;

class CustomerLoginRequest extends PortalLoginRequest
{
    protected function guardName(): string
    {
        return 'customer';
    }
}
