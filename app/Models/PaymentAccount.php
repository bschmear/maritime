<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentAccount extends Model
{
    protected $fillable = [
        'account_settings_id',
        'provider',
        'is_active',
        'data',
        'external_account_id',
        'charges_enabled',
        'payouts_enabled',
        'connected_at',
    ];

    protected $casts = [
        'data' => 'array',
        'is_active' => 'boolean',
        'charges_enabled' => 'boolean',
        'payouts_enabled' => 'boolean',
        'connected_at' => 'datetime',
    ];

    public function scopeProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
