<?php

declare(strict_types=1);

namespace App\Domain\Financing\Models;

use App\Domain\AssetUnit\Models\AssetUnit;
use App\Domain\Bill\Models\Bill;
use App\Domain\Financing\Support\FinancingMetrics;
use App\Domain\Vendor\Models\Vendor;
use App\Enums\Financing\Status;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Financing extends Model
{
    protected $table = 'financings';

    protected $fillable = [
        'uuid',
        'sequence',
        'asset_unit_id',
        'vendor_id',
        'dealer_name',
        'dealer_cin',
        'status',
        'principal_amount',
        'current_balance',
        'annual_interest_rate',
        'loan_term_months',
        'financed_at',
        'interest_start_date',
        'next_payment_date',
        'monthly_payment_amount',
        'lender_status',
        'aging_days',
        'curtailment_current_due',
        'past_due_curtailment',
        'supplier_name',
        'supplier_cin',
        'lender_invoice_number',
        'model_year',
        'model_number',
        'serial_vin',
        'days_alert_threshold',
        'interest_alert_threshold',
        'alert_notified_at',
        'last_imported_at',
        'notes',
    ];

    protected $appends = [
        'display_name',
    ];

    protected function casts(): array
    {
        return [
            'status' => Status::class,
            'principal_amount' => 'decimal:2',
            'current_balance' => 'decimal:2',
            'annual_interest_rate' => 'decimal:4',
            'monthly_payment_amount' => 'decimal:2',
            'curtailment_current_due' => 'decimal:2',
            'past_due_curtailment' => 'decimal:2',
            'interest_alert_threshold' => 'decimal:2',
            'financed_at' => 'date',
            'interest_start_date' => 'date',
            'next_payment_date' => 'date',
            'alert_notified_at' => 'datetime',
            'last_imported_at' => 'datetime',
            'aging_days' => 'integer',
            'loan_term_months' => 'integer',
            'days_alert_threshold' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Financing $financing): void {
            if (empty($financing->uuid)) {
                $financing->uuid = (string) Str::uuid();
            }
            if ($financing->sequence === null) {
                $next = (int) (DB::table('financings')->max('sequence') ?? 999);
                $financing->sequence = $next + 1;
            }
        });
    }

    public function getDisplayNameAttribute(): string
    {
        return 'FIN-'.($this->sequence ?: $this->id ?: '???');
    }

    public function assetUnit(): BelongsTo
    {
        return $this->belongsTo(AssetUnit::class, 'asset_unit_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class, 'financing_id');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', Status::Active);
    }

    public function metrics(): FinancingMetrics
    {
        return new FinancingMetrics($this);
    }
}
