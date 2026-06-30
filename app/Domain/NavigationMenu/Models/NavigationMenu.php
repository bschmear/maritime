<?php

declare(strict_types=1);

namespace App\Domain\NavigationMenu\Models;

use App\Domain\Role\Models\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NavigationMenu extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'name',
        'role_id',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(NavigationMenuItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function rootItems(): HasMany
    {
        return $this->items()->whereNull('parent_id');
    }
}
