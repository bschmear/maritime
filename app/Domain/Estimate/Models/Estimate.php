<?php

namespace App\Domain\Estimate\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Estimate extends Model
{
    use SoftDeletes;

    protected $table = 'estimates';

    protected $guarded = ['id'];

    protected $casts = [
        'issue_date' => 'date',
        'expiration_date' => 'date',
        'tax_rate' => 'decimal:3',
    ];

    protected $appends = ['display_name'];

    protected static function booted()
    {
        static::creating(function ($record) {
            if (empty($record->uuid)) {
                $record->uuid = (string) Str::uuid();
            }
            if (empty($record->sequence)) {
                $next = (int) (DB::table('estimates')->max('sequence') ?? 9999);
                $record->sequence = $next + 1;
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Opportunity\Models\Opportunity::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Customer\Models\Customer::class);
    }

    public function salesperson(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\User\Models\User::class, 'user_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\User\Models\User::class, 'user_id');
    }

    public function primaryVersion(): BelongsTo
    {
        return $this->belongsTo(EstimateVersion::class, 'primary_version_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(EstimateVersion::class);
    }

    public function getDisplayNameAttribute()
    {
        return 'EST-' . ($this->sequence ?: $this->id ?: '???');
    }
}
