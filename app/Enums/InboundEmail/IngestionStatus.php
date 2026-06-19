<?php

namespace App\Enums\InboundEmail;

enum IngestionStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
