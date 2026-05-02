<?php

declare(strict_types=1);

namespace App\Domain\WarrantyClaim\Models;

use App\Domain\Attachment\Concerns\HasLinkedInventoryImages;
use App\Domain\Location\Models\Location;
use App\Domain\Subsidiary\Models\Subsidiary;
use App\Domain\Vendor\Models\Vendor;
use App\Domain\WorkOrder\Models\WorkOrder;
use App\Enums\WarrantyClaim\Status;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
// use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WarrantyClaim extends Model
{
    use HasLinkedInventoryImages;

    protected $table = 'warrantyclaims';

    protected $guarded = ['id'];

    protected $appends = ['display_name'];

    protected $fillable = [
        'vendor_id',
        'work_order_id',
        'subsidiary_id',
        'location_id',
        'status',
        'total_amount',
        'submitted_at',
        'approved_at',
        'paid_at',
        'voided_at',
        'rejection_reason',
        'notes',
    ];

    protected $casts = [
        'status' => Status::class,
        'total_amount' => 'decimal:2',
        'subsidiary_id' => 'integer',
        'location_id' => 'integer',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function (WarrantyClaim $record) {
            if (empty($record->uuid)) {
                $record->uuid = (string) Str::uuid();
            }
            if (empty($record->sequence)) {
                $next = (int) (DB::table('warrantyclaims')->max('sequence') ?? 999);
                $record->sequence = $next + 1;
            }
        });
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function subsidiary(): BelongsTo
    {
        return $this->belongsTo(Subsidiary::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(WarrantyClaimLineItem::class)->orderBy('id');
    }

    public function getDisplayNameAttribute()
    {
        return 'WCL-'.($this->sequence ?: $this->id ?: '???');
    }
}
