<?php

namespace App\Domain\Qualification\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Domain\Lead\Models\Lead;
use App\Domain\User\Models\User;
use App\Domain\Opportunity\Models\Opportunity;
use Illuminate\Support\Facades\DB;

class Qualification extends Model
{
    use SoftDeletes;

    protected $table = 'qualifications';

    /**
     * Mass assignable fields
     */
    protected $guarded = ['id'];

    /**
     * Casts
     */
    protected $casts = [
        'lead_id' => 'integer',
        'user_id' => 'integer',
        'createdby_id' => 'integer',
        'status' => 'integer',
        'intended_use' => 'integer',
        'ownership_type' => 'integer',
        'preferred_length' => 'integer',
        'max_weight' => 'integer',
        'needs_engine' => 'boolean',
        'needs_trailer' => 'boolean',
        'budget_min' => 'decimal:2',
        'budget_max' => 'decimal:2',
        'purchase_timeline' => 'integer',
        'requires_delivery' => 'boolean',
        'lead_source' => 'integer',
        'qualified_at' => 'datetime',
        'converted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $appends = ['display_name'];

    protected static function booted()
    {
        static::creating(function ($record) {
            $next = (int) (DB::table('qualifications')->max('sequence') ?? 999);
            $record->sequence = $next + 1;
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'createdby_id');
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
    }

    public function desired_brand(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\BoatMake\Models\BoatMake::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers / Derived Values
    |--------------------------------------------------------------------------
    */

    public function markAsConverted(Opportunity $opportunity): void
    {
        $this->converted_at = now();
        $this->saveQuietly();

        $this->opportunities()->save($opportunity);
    }

    public function getDisplayNameAttribute()
    {
        return 'QLF-' . ($this->sequence ?: $this->id ?: '???');
    }
}
