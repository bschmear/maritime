<?php

namespace App\Support;

use App\Models\Plan;
use Illuminate\Support\Str;

class PlanFeatureList
{
    /**
     * @return array<int, array{title: string, description: string}>
     */
    public static function normalize(mixed $included): array
    {
        if (! is_array($included)) {
            return [];
        }

        $normalized = [];

        foreach ($included as $item) {
            if (is_string($item)) {
                $title = trim($item);
                if ($title !== '') {
                    $normalized[] = [
                        'title' => $title,
                        'description' => '',
                    ];
                }

                continue;
            }

            if (! is_array($item)) {
                continue;
            }

            $title = trim((string) ($item['title'] ?? $item['name'] ?? $item['label'] ?? ''));
            if ($title === '') {
                continue;
            }

            $normalized[] = [
                'title' => $title,
                'description' => trim((string) ($item['description'] ?? '')),
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<int, array{title?: string, description?: string}|string>  $included
     * @return array<int, array{title: string, description: string}>
     */
    public static function validateAndNormalize(array $included): array
    {
        return self::normalize($included);
    }

    /**
     * @return array<int, string>
     */
    public static function titles(mixed $included): array
    {
        return array_values(array_map(
            fn (array $feature) => $feature['title'],
            self::normalize($included),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public static function toPublicArray(Plan $plan): array
    {
        $features = self::normalize($plan->included);

        return [
            'id' => $plan->id,
            'name' => $plan->name,
            'description' => $plan->description,
            'coming_soon' => (bool) ($plan->coming_soon ?? false),
            'monthly_price' => $plan->monthly_price,
            'yearly_price' => $plan->yearly_price,
            'popular' => (bool) $plan->popular,
            'seat_limit' => $plan->seat_limit,
            'seat_extra' => $plan->seat_extra,
            'features' => $features,
            'feature_titles' => array_column($features, 'title'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function toWelcomeArray(Plan $plan): array
    {
        $public = self::toPublicArray($plan);

        return [
            'id' => $public['id'],
            'name' => $public['name'],
            'description' => $public['description'],
            'coming_soon' => $public['coming_soon'],
            'price' => [
                'monthly' => $plan->monthly_price ?? 0,
                'annual' => $plan->yearly_price ?? 0,
            ],
            'features' => $public['feature_titles'],
            'feature_details' => $public['features'],
            'cta' => $plan->popular ? 'Start '.$plan->name.' Trial' : 'Get '.$plan->name,
            'ctaLink' => route('checkout.plans', ['plan' => $plan->id, 'billing' => 'monthly']),
            'popular' => $public['popular'],
            'seatLimit' => $public['seat_limit'],
        ];
    }

    public static function featureKey(string $title): string
    {
        return Str::slug($title) ?: 'feature';
    }
}
