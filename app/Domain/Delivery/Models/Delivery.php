<?php

namespace App\Domain\Delivery\Models;

use App\Domain\Fleet\Models\Fleet;
use App\Enums\Deliveries\Status as DeliveryStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class Delivery extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'customer_id',
        'asset_unit_id',
        'work_order_id',
        'transaction_id',
        'scheduled_at',
        'estimated_arrival_at',
        'time_to_leave_by',
        'estimated_travel_duration_seconds',
        'delivery_duration_minutes',
        'en_route_at',
        'delivered_at',
        'status',
        'technician_id',
        'recipient_name',
        'signature_path',
        'signed_at',
        'signed_ip',
        'signed_user_agent',
        'signature_file',
        'signature_hash',
        'internal_notes',
        'customer_notes',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postal_code',
        'country',
        'latitude',
        'longitude',
        'subsidiary_id',
        'location_id',
        'fleet_truck_id',
        'fleet_trailer_id',
        'delivery_location_id',
        'delivery_to_type',
        'contact_address_id',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'estimated_arrival_at' => 'datetime',
        'time_to_leave_by' => 'datetime',
        'en_route_at' => 'datetime',
        'delivered_at' => 'datetime',
        'signed_at' => 'datetime',
        'delivery_duration_minutes' => 'integer',
    ];

    protected $appends = ['display_name'];

    protected static function booted()
    {
        static::creating(function ($delivery) {
            $next = (int) (DB::table('deliveries')->max('sequence') ?? 999);
            $delivery->sequence = $next + 1;
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function subsidiary()
    {
        return $this->belongsTo(\App\Domain\Subsidiary\Models\Subsidiary::class);
    }

    public function location()
    {
        return $this->belongsTo(\App\Domain\Location\Models\Location::class);
    }

    public function fleetTruck(): BelongsTo
    {
        return $this->belongsTo(Fleet::class, 'fleet_truck_id');
    }

    public function fleetTrailer(): BelongsTo
    {
        return $this->belongsTo(Fleet::class, 'fleet_trailer_id');
    }

    public function customer()
    {
        return $this->belongsTo(\App\Domain\Customer\Models\Customer::class);
    }

    public function assetUnit()
    {
        return $this->belongsTo(\App\Domain\AssetUnit\Models\AssetUnit::class);
    }

    public function asset_unit()
    {
        return $this->belongsTo(\App\Domain\AssetUnit\Models\AssetUnit::class);
    }

    public function workOrder()
    {
        return $this->belongsTo(\App\Domain\WorkOrder\Models\WorkOrder::class);
    }

    public function work_order()
    {
        return $this->belongsTo(\App\Domain\WorkOrder\Models\WorkOrder::class);
    }

    public function transaction()
    {
        return $this->belongsTo(\App\Domain\Transaction\Models\Transaction::class);
    }

    public function deliveryLocation()
    {
        return $this->belongsTo(\App\Domain\DeliveryLocation\Models\DeliveryLocation::class);
    }

    public function delivery_location()
    {
        return $this->belongsTo(\App\Domain\DeliveryLocation\Models\DeliveryLocation::class, 'delivery_location_id');
    }

    public function technician()
    {
        return $this->belongsTo(\App\Domain\User\Models\User::class, 'technician_id');
    }

    public function items()
    {
        return $this->hasMany(DeliveryItem::class)->orderBy('position');
    }

    public function checklistItems()
    {
        return $this->hasMany(\App\Domain\DeliveryChecklistItem\Models\DeliveryChecklistItem::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    public function scopeDelivered($query)
    {
        return $query->where('status', 'delivered');
    }

    public function scopeUpcoming($query)
    {
        return $query->where('scheduled_at', '>=', now())
            ->whereNull('delivered_at');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function markAsDelivered()
    {
        $this->update([
            'status' => DeliveryStatus::Delivered->value,
            'delivered_at' => now(),
        ]);
    }

    /**
     * True when the delivery has items and every item has a delivered_at stamp.
     */
    public function allItemsDelivered(): bool
    {
        $total = $this->items()->count();
        if ($total === 0) {
            return false;
        }

        $undelivered = $this->items()->whereNull('delivered_at')->count();

        return $undelivered === 0;
    }

    /**
     * Reflect per-item delivery state onto the delivery's status field.
     *
     * - All items delivered → status=delivered, stamp delivered_at if empty.
     * - Some but not all → if currently scheduled/confirmed, bump to en_route.
     * - Items exist but none delivered → if currently delivered, step back to en_route.
     */
    public function syncStatusFromItems(): self
    {
        $total = $this->items()->count();
        if ($total === 0) {
            return $this;
        }

        $delivered = $this->items()->whereNotNull('delivered_at')->count();

        if ($delivered === $total) {
            $this->status = DeliveryStatus::Delivered->value;
            if (empty($this->delivered_at)) {
                $this->delivered_at = now();
            }

            return $this;
        }

        if ($delivered > 0) {
            if (in_array($this->status, ['scheduled', 'confirmed'], true)) {
                $this->status = 'en_route';
            }

            return $this;
        }

        // delivered == 0
        if ($this->status === 'delivered') {
            $this->status = 'en_route';
            $this->delivered_at = null;
        }

        return $this;
    }

    public function getDisplayNameAttribute()
    {
        return 'DLV-'.($this->sequence ?: $this->id ?: '???');
    }

    /**
     * Same approach as {@see \App\Domain\Contract\Models\Contract}: keys live under private/ on S3,
     * so we must use a pre-signed URL — plain {@see \Illuminate\Contracts\Filesystem\Filesystem::url()} is not readable.
     */
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

    public function getRouteKeyName(): string
    {
        return 'id';
    }
}
