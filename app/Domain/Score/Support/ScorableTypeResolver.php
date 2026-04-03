<?php

declare(strict_types=1);

namespace App\Domain\Score\Support;

use App\Domain\Contact\Models\Contact;
use App\Domain\Lead\Models\Lead;

final class ScorableTypeResolver
{
    /**
     * @return list<string>
     */
    public static function allowedShortNames(): array
    {
        return ['Lead', 'Contact'];
    }

    /**
     * @return class-string<Lead|Contact>|null
     */
    public static function toClass(string $type): ?string
    {
        return match ($type) {
            'Lead', Lead::class => Lead::class,
            'Contact', Contact::class => Contact::class,
            default => null,
        };
    }
}
