<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Models;

use App\Domain\Contact\Models\Contact;
use App\Domain\Location\Models\Location;
use App\Domain\Subsidiary\Models\Subsidiary;
use App\Domain\User\Models\User;
use App\Domain\Vendor\Models\Vendor;
use App\Enums\Shipment\Status;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Shipment extends Model
{
    protected $fillable = [
        'uuid',
        'contact_id',
        'vendor_id',
        'recipient_name',
        'recipient_email',
        'subsidiary_id',
        'location_id',
        'created_by_user_id',
        'status',
        'from_address',
        'to_address',
        'parcel',
        'easypost_shipment_id',
        'easypost_tracker_id',
        'carrier',
        'service',
        'rate_cents',
        'tracking_code',
        'label_url',
        'public_tracking_url',
        'rates_snapshot',
        'tracking_events',
        'purchased_at',
        'notified_at',
        'notes',
    ];

    protected $casts = [
        'status' => Status::class,
        'from_address' => 'array',
        'to_address' => 'array',
        'parcel' => 'array',
        'rates_snapshot' => 'array',
        'tracking_events' => 'array',
        'purchased_at' => 'datetime',
        'notified_at' => 'datetime',
        'rate_cents' => 'integer',
    ];

    protected $appends = ['display_name'];

    protected static function booted(): void
    {
        static::creating(function (Shipment $shipment): void {
            if (! filled($shipment->uuid)) {
                $shipment->uuid = (string) Str::uuid();
            }
            if ($shipment->status === null) {
                $shipment->status = Status::Draft;
            }
        });
    }

    public function getDisplayNameAttribute(): string
    {
        $tracking = $this->tracking_code;
        if (filled($tracking)) {
            return 'Shipment '.$tracking;
        }

        return 'Shipment #'.$this->id;
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function subsidiary(): BelongsTo
    {
        return $this->belongsTo(Subsidiary::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function isPurchased(): bool
    {
        return in_array($this->status, [Status::Purchased, Status::InTransit, Status::Delivered], true);
    }
}
