<?php

declare(strict_types=1);

namespace App\Domain\Subsidiary\Support;

final class GoogleReviewUrl
{
    public static function normalize(mixed $raw): ?string
    {
        if (! is_string($raw)) {
            return null;
        }

        $url = trim($raw);
        if ($url === '') {
            return null;
        }

        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }

        return $url;
    }

    /**
     * @return list<string|callable>
     */
    public static function validationRules(): array
    {
        return [
            'nullable',
            'string',
            'max:500',
            function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value === null || $value === '') {
                    return;
                }

                $normalized = self::normalize(is_string($value) ? $value : null);
                if ($normalized === null || ! filter_var($normalized, FILTER_VALIDATE_URL)) {
                    $fail('Enter a valid Google review link (for example https://g.page/r/your-business/review).');
                }
            },
        ];
    }
}
