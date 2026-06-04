<?php

namespace App\Domain\UserFavorite\Models;

use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserFavorite extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'user_id',
        'label',
        'route',
        'route_params',
        'route_params_hash',
        'sort_order',
    ];

    protected $casts = [
        'route_params' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (UserFavorite $favorite) {
            $favorite->route_params_hash = self::hashRouteParams($favorite->route_params);
        });
    }

    /**
     * Stable hash for uniqueness across databases (PostgreSQL cannot unique-index json).
     *
     * @param  array<string, mixed>|null  $params
     */
    public static function hashRouteParams(?array $params): string
    {
        if ($params === null || $params === []) {
            return '';
        }

        $normalized = $params;
        ksort($normalized);

        return hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function getRouteParameters(): array
    {
        if (is_array($this->route_params)) {
            return $this->route_params;
        }

        return [];
    }
}
