<?php

namespace App\Support\InboundEmail;

class InboundEmailAddressParser
{
    public static function extract(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        if (preg_match('/<([^>]+)>/', $value, $matches)) {
            $value = trim($matches[1]);
        }

        $value = strtolower(trim($value));

        return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : null;
    }

    /**
     * SendGrid may deliver multiple recipients comma-separated.
     * Prefer an address on the configured inbound domain when present.
     */
    public static function extractFirst(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $candidates = [];
        foreach (preg_split('/,/', $value) as $part) {
            $address = self::extract(trim($part));
            if ($address !== null) {
                $candidates[] = $address;
            }
        }

        if ($candidates === []) {
            return null;
        }

        $domain = strtolower((string) config('inbound_email.domain', 'inbound.helmful.com'));
        foreach ($candidates as $candidate) {
            if (str_ends_with($candidate, '@'.$domain)) {
                return $candidate;
            }
        }

        return $candidates[0];
    }
}
