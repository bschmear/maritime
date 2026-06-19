<?php

namespace App\Support\InboundEmail;

class LeadNameSplitter
{
    /**
     * @return array{first_name: ?string, last_name: ?string}
     */
    public static function split(?string $name): array
    {
        $name = trim((string) $name);
        if ($name === '') {
            return ['first_name' => null, 'last_name' => null];
        }

        $parts = preg_split('/\s+/', $name) ?: [];
        if (count($parts) === 1) {
            return ['first_name' => $parts[0], 'last_name' => null];
        }

        $firstName = array_shift($parts);
        $lastName = implode(' ', $parts);

        return [
            'first_name' => $firstName,
            'last_name' => $lastName !== '' ? $lastName : null,
        ];
    }
}
