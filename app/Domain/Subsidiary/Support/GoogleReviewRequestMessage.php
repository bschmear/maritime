<?php

declare(strict_types=1);

namespace App\Domain\Subsidiary\Support;

final class GoogleReviewRequestMessage
{
    public static function default(?string $subsidiaryName = null): string
    {
        $name = trim((string) $subsidiaryName);

        if ($name !== '') {
            return "We appreciate your business with {$name}. We'd appreciate it if you could leave us a Google review.";
        }

        return 'We appreciate your business. We\'d appreciate it if you could leave us a Google review.';
    }

    public static function normalize(mixed $raw, ?string $subsidiaryName = null): string
    {
        if (! is_string($raw) || trim($raw) === '') {
            return self::default($subsidiaryName);
        }

        return trim($raw);
    }

    /**
     * @return list<string>
     */
    public static function validationRules(): array
    {
        return ['required', 'string', 'min:10', 'max:2000'];
    }
}
