<?php

namespace App\Domain\BoatShowEvent\Support;

use App\Domain\BoatShow\Models\BoatShow;
use App\Domain\BoatShowEvent\Models\BoatShowEvent;

class BoatShowEventDisplayName
{
    public static function build(?string $boatShowName, int|string|null $year, ?string $booth): string
    {
        $showName = trim((string) $boatShowName);
        $yearPart = $year !== null && $year !== '' ? (string) $year : '';
        $boothPart = trim((string) $booth);

        $parts = array_filter([$showName, $yearPart], fn ($p) => $p !== '');

        $name = implode(' ', $parts);

        if ($boothPart !== '') {
            $name = $name !== '' ? "{$name} — Booth {$boothPart}" : "Booth {$boothPart}";
        }

        return $name !== '' ? $name : 'Boat show event';
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function resolve(array $validated, ?BoatShowEvent $existing = null): string
    {
        $useCustom = filter_var($validated['use_custom_display_name'] ?? false, FILTER_VALIDATE_BOOLEAN)
            && trim((string) ($validated['display_name'] ?? '')) !== '';

        if ($useCustom) {
            return trim((string) $validated['display_name']);
        }

        $boatShowId = $validated['boat_show_id'] ?? $existing?->boat_show_id;
        $boatShowName = null;

        if ($boatShowId) {
            $boatShowName = BoatShow::query()->whereKey($boatShowId)->value('display_name');
        }

        $year = array_key_exists('year', $validated) ? $validated['year'] : $existing?->year;
        $booth = array_key_exists('booth', $validated) ? $validated['booth'] : $existing?->booth;

        return self::build($boatShowName, $year, $booth);
    }
}
