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
        return ['Lead', 'LeadProfile', 'Contact'];
    }

    /**
     * @return class-string<Lead|Contact>|null
     */
    public static function toClass(string $type): ?string
    {
        $type = trim(str_replace('/', '\\', $type));

        while (str_contains($type, '\\\\')) {
            $type = str_replace('\\\\', '\\', $type);
        }

        $lower = strtolower($type);

        if (in_array($lower, ['lead', 'leadprofile', 'lead_profile', 'lead_profiles'], true)) {
            return Lead::class;
        }

        if ($lower === 'contact') {
            return Contact::class;
        }

        if ($type === Lead::class) {
            return Lead::class;
        }

        if ($type === Contact::class) {
            return Contact::class;
        }

        return null;
    }
}
