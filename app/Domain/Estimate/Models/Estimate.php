<?php

namespace App\Domain\Estimate\Models;

use App\Domain\Transaction\Models\Transaction;
use App\Enums\Estimate\EstimateStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
        'sent_at' => 'datetime',
        'signed_at' => 'datetime',
        'approved_at' => 'datetime',
        'declined_at' => 'datetime',
    ];

    protected $appends = ['display_name', 'is_locked'];

    protected static function booted()
    {
        static::creating(function ($record) {
            if (empty($record->uuid)) {
                $record->uuid = (string) Str::uuid();
            }
            if (empty($record->sequence)) {
                $next = (int) (DB::table('estimates')->max('sequence') ?? 999);
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

    /** The estimate this record was revised from (parent). */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function revisedFrom(): BelongsTo
    {
        return $this->belongsTo(Estimate::class, 'revised_from_id');
    }

    /** The newer revision that supersedes this estimate (child). */
    public function revision(): HasOne
    {
        return $this->hasOne(Estimate::class, 'revised_from_id');
    }

    /**
     * Locked when sent or pending approval — must create a revision to make changes.
     */
    public function getIsLockedAttribute(): bool
    {
        return in_array((int) $this->status, [
            EstimateStatus::PendingApproval->id(),
            EstimateStatus::PendingApproval->id(),
        ]);
    }

    public function getDisplayNameAttribute()
    {
        return 'EST-'.($this->sequence ?: $this->id ?: '???');
    }

    public function getSignatureUrlAttribute(): ?string
    {
        if (! $this->signature_file) {
            return null;
        }

        try {
            return Storage::disk('s3')->temporaryUrl($this->signature_file, now()->addHours(2));
        } catch (\Exception $e) {
            return Storage::disk('s3')->url($this->signature_file);
        }
    }
}
