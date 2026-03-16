<?php

namespace App\Domain\PortalAccess\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PortalAccess extends Model
{
    protected $table = 'portal_accesses';

    protected $fillable = [
        'uuid',
        'token',
        'customer_id',
        'record_type',
        'record_id',
        'permissions',
        'expires_at',
        'last_used_at',
        'revoked_at',
        'created_by_user_id',
    ];

    protected $casts = [
        'permissions' => 'array',
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Boot
    |--------------------------------------------------------------------------
    */

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {

            if (!$model->uuid) {
                $model->uuid = Str::uuid();
            }

            if (!$model->token) {
                $model->token = Str::random(64);
            }

        });
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function customer()
    {
        return $this->belongsTo(\App\Domain\Customer\Models\Customer::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by_user_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isExpired(): bool
    {
        if (!$this->expires_at) {
            return false;
        }

        return now()->greaterThan($this->expires_at);
    }

    public function isRevoked(): bool
    {
        return !is_null($this->revoked_at);
    }

    public function isValid(): bool
    {
        return !$this->isExpired() && !$this->isRevoked();
    }

    public function markUsed(): void
    {
        $this->update([
            'last_used_at' => now()
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | URL Helper
    |--------------------------------------------------------------------------
    */

    public function url(): string
    {
        return url('/portal/view/' . $this->token);
    }
}
