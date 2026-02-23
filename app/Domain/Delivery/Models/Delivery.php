<?php

namespace App\Domain\Delivery\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;

class Delivery extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'customer_id',
        'asset_unit_id',
        'work_order_id',
        'scheduled_at',
        'estimated_arrival_at',
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
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'estimated_arrival_at' => 'datetime',
        'delivered_at' => 'datetime',
        'signed_at' => 'datetime',
    ];


    protected static function booted()
    {
        static::creating(function ($delivery) {
            DB::transaction(function () use ($delivery) {
                // Advisory lock keyed on the deliveries table OID to serialise sequence generation
                DB::statement("SELECT pg_advisory_xact_lock('deliveries'::regclass::int)");

                $next = (int) (DB::table('deliveries')->max('sequence') ?? 999);
                $delivery->sequence = $next + 1;
            });
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

    public function assetUnit()
    {
        return $this->belongsTo(\App\Domain\AssetUnit\Models\AssetUnit::class);
    }

    public function workOrder()
    {
        return $this->belongsTo(\App\Domain\WorkOrder\Models\WorkOrder::class);
    }

    public function technician()
    {
        return $this->belongsTo(\App\Domain\User\Models\User::class, 'technician_id');
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
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);
    }

    public function getDisplayNameAttribute()
    {
        return 'DLV-' . $this->sequence;
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
