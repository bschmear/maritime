<?php

namespace App\Enums\Surveys;

enum InvitationStatus: string
{
    case Scheduled = 'scheduled';
    case Sent = 'sent';
    case Cancelled = 'cancelled';
    case Failed = 'failed';
}
