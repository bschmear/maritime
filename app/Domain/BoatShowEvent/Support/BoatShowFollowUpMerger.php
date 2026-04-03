<?php

declare(strict_types=1);

namespace App\Domain\BoatShowEvent\Support;

/**
 * Replaces {{ token }} placeholders in subject/body (HTML).
 */
final class BoatShowFollowUpMerger
{
    /**
     * @param  array<string, string>  $data  keys must match full tokens e.g. '{{ lead_name }}'
     */
    public static function merge(string $text, array $data): string
    {
        if ($data === []) {
            return $text;
        }

        return str_replace(array_keys($data), array_values($data), $text);
    }

    /**
     * Sample values for test emails and previews.
     *
     * @return array<string, string>
     */
    public static function sampleData(): array
    {
        $user = auth()->user();

        return [
            '{{ lead_name }}' => 'Jane Boater',
            '{{ lead_email }}' => 'jane@example.com',
            '{{ event_name }}' => 'Spring Marina Expo',
            '{{ event_venue }}' => 'Waterfront Convention Center',
            '{{ boat_show_name }}' => 'Great Lakes Boat Show',
            '{{ dealer_name }}' => 'Harbor Marine Sales',
            '{{ salesperson_name }}' => $user ? (string) ($user->name ?? $user->email ?? 'Our team') : 'Alex Smith',
            '{{ today }}' => now()->format('F j, Y'),
            '{{ selected_asset_list }}' => '<ul><li>2024 Sea Ray SLX 250</li><li>Yamaha 300 HP outboard</li><li>Tandem axle trailer</li></ul>',
        ];
    }
}
