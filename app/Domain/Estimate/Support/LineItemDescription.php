<?php

declare(strict_types=1);

namespace App\Domain\Estimate\Support;

final class LineItemDescription
{
    /**
     * @param  array<string, mixed>  $lineData
     */
    public static function merge(array $lineData): ?string
    {
        $catalog = trim((string) ($lineData['catalog_description'] ?? ''));
        $notes = trim((string) ($lineData['notes'] ?? ''));

        if ($catalog !== '' && $notes !== '') {
            return $catalog."\n\n".$notes;
        }
        if ($catalog !== '') {
            return $catalog;
        }
        if ($notes !== '') {
            return $notes;
        }

        $legacy = trim((string) ($lineData['description'] ?? ''));

        return $legacy !== '' ? $legacy : null;
    }
}
