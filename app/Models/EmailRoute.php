<?php

namespace App\Models;

use App\Enums\InboundEmail\RouteAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailRoute extends Model
{
    protected $connection = 'pgsql';

    protected $fillable = [
        'tenant_id',
        'address',
        'action',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'action' => RouteAction::class,
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function ingestions(): HasMany
    {
        return $this->hasMany(AiEmailIngestion::class);
    }

    /**
     * @param  Builder<EmailRoute>  $query
     * @return Builder<EmailRoute>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
