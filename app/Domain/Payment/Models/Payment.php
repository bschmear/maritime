<?php

namespace App\Domain\Payment\Models;

use App\Domain\Invoice\Models\Invoice;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Payment extends Model
{
    use SoftDeletes;

    protected $table = 'payments';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $appends = ['display_name'];

    protected $casts = [
        'amount' => 'decimal:2',
        'surcharge_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'processor_response' => 'array',
        'paid_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Payment $payment): void {
            if (empty($payment->uuid)) {
                $payment->uuid = (string) Str::uuid();
            }
            if (empty($payment->sequence)) {
                $next = (int) (static::query()->max('sequence') ?? 999);

                $payment->sequence = $next + 1;
            }
        });
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    /** Snake-case alias for eager loads and Inertia (relationship key `recorded_by`). */
    public function recorded_by(): BelongsTo
    {
        return $this->recordedBy();
    }

    public function getDisplayNameAttribute()
    {
        return 'PMT-'.($this->sequence ?: $this->id ?: '???');
    }
}
