<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Canonical manufacturer list (display + stable slug) from Domain/BoatMake/Schema/manufacturers.json.
 *
 * File format: JSON object `{ "slug": "Display Name", ... }` (slug is the inventory / brand key).
 * Legacy format: JSON array of display name strings (slugs derived with collision suffixes) is still supported.
 */
final class ManufacturerCatalog
{
    public static function jsonPath(): string
    {
        return base_path('app/Domain/BoatMake/Schema/manufacturers.json');
    }

    /**
     * @return list<array{display_name: string, slug: string}>
     */
    public static function entries(): array
    {
        $path = self::jsonPath();
        if (! is_readable($path)) {
            return [];
        }

        $raw = json_decode((string) file_get_contents($path), true);
        if (! is_array($raw)) {
            return [];
        }

        if (array_is_list($raw)) {
            return self::entriesFromLegacyNameList($raw);
        }

        $out = [];
        foreach ($raw as $slug => $displayName) {
            if (! is_string($slug) || trim($slug) === '') {
                continue;
            }
            if (! is_string($displayName) || trim($displayName) === '') {
                continue;
            }
            $out[] = ['slug' => trim($slug), 'display_name' => trim($displayName)];
        }

        usort($out, static fn (array $a, array $b): int => strcasecmp($a['display_name'], $b['display_name']));

        return $out;
    }

    /**
     * @param  list<mixed>  $raw
     * @return list<array{display_name: string, slug: string}>
     */
    private static function entriesFromLegacyNameList(array $raw): array
    {
        $used = [];
        $out = [];
        foreach ($raw as $name) {
            if (! is_string($name) || trim($name) === '') {
                continue;
            }
            $name = trim($name);
            $base = Str::slug($name);
            $slug = $base !== '' ? $base : 'make';
            $n = 2;
            while (isset($used[$slug])) {
                $slug = $base.'-'.$n;
                $n++;
            }
            $used[$slug] = true;
            $out[] = ['display_name' => $name, 'slug' => $slug];
        }

        return $out;
    }

    public static function slugByDisplayName(string $displayName): ?string
    {
        foreach (self::entries() as $row) {
            if (strcasecmp($row['display_name'], $displayName) === 0) {
                return $row['slug'];
            }
        }

        return null;
    }

    /**
     * @return array{display_name: string, slug: string}|null
     */
    public static function findRowByNormalizedDisplayName(string $normalizedDisplayName): ?array
    {
        foreach (self::entries() as $row) {
            if (mb_strtolower($row['display_name']) === $normalizedDisplayName) {
                return $row;
            }
        }

        return null;
    }
}
