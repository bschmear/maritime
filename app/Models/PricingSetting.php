<?php

namespace App\Models;

use App\Support\PlanFeatureList;
use Illuminate\Database\Eloquent\Model;

class PricingSetting extends Model
{
    /**
     * @var string|null
     */
    protected $connection = 'pgsql';

    protected $fillable = [
        'all_tiers_included',
    ];

    protected $casts = [
        'all_tiers_included' => 'array',
    ];

    /**
     * @return array{title: string, subtitle: string, features: array<int, array{title: string, description: string}>}
     */
    public static function allTiersSection(): array
    {
        $config = config('pricing.all_tiers', []);
        $defaults = [
            'title' => (string) ($config['title'] ?? 'All tiers include'),
            'subtitle' => (string) ($config['subtitle'] ?? ''),
            'features' => PlanFeatureList::normalize($config['features'] ?? []),
        ];

        $stored = static::query()->value('all_tiers_included');
        if (! is_array($stored) || $stored === []) {
            return $defaults;
        }

        if (array_is_list($stored)) {
            return [
                ...$defaults,
                'features' => PlanFeatureList::normalize($stored),
            ];
        }

        return [
            'title' => (string) ($stored['title'] ?? $defaults['title']),
            'subtitle' => (string) ($stored['subtitle'] ?? $defaults['subtitle']),
            'features' => PlanFeatureList::normalize($stored['features'] ?? []),
        ];
    }
}
