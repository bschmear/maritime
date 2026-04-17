<?php

namespace App\Domain\DeliveryLocation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DeliveryLocation extends Model
{
    use SoftDeletes;

    protected $table = 'delivery_locations';

    protected $fillable = [
        'uuid',
        'subsidiary_id',
        'name',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postal_code',
        'country',
        'latitude',
        'longitude',
        'contact_name',
        'contact_phone',
        'notes',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    protected $appends = ['display_name'];

    protected static function booted(): void
    {
        static::creating(function (DeliveryLocation $location) {
            if (empty($location->uuid)) {
                $location->uuid = (string) Str::uuid();
            }
            if (empty($location->sequence)) {
                $next = (int) (DB::table('delivery_locations')->max('sequence') ?? 999);
                $location->sequence = $next + 1;
            }
        });
    }

    public function subsidiary(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Subsidiary\Models\Subsidiary::class);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->attributes['name']
            ?: ('DLC-'.($this->sequence ?: ($this->id ?? '???')));
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }
}
