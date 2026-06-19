<?php

namespace App\Domain\InboundEmail;

use App\Contracts\InboundEmail\InboundEmailAction;
use App\Domain\InboundEmail\Actions\CreateLeadFromInboundEmail;
use App\Enums\InboundEmail\RouteAction;
use App\Models\EmailRoute;
use InvalidArgumentException;

class InboundEmailActionFactory
{
    public function make(EmailRoute $route): InboundEmailAction
    {
        return match ($route->action) {
            RouteAction::CreateLead => app(CreateLeadFromInboundEmail::class),
            default => throw new InvalidArgumentException('Unsupported inbound email action: '.$route->action->value),
        };
    }
}
