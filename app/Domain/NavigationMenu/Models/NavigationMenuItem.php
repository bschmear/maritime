<?php

declare(strict_types=1);

namespace App\Domain\NavigationMenu\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NavigationMenuItem extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'navigation_menu_id',
        'parent_id',
        'label',
        'route_name',
        'permission_key',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function menu(): BelongsTo
    {
        return $this->belongsTo(NavigationMenu::class, 'navigation_menu_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('id');
    }
}
