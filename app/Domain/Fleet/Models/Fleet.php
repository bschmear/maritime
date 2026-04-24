<?php

declare(strict_types=1);

namespace App\Domain\Fleet\Models;

use App\Domain\FleetMaintenance\Models\FleetMaintenance;
use App\Domain\Location\Models\Location;
use App\Enums\Fleet\FleetStatus;
use App\Enums\Fleet\FleetType;
use App\Enums\Fleet\FuelType;
use App\Enums\Fleet\WeightUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fleet extends Model
{
    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => FleetType::class,
            'status' => FleetStatus::class,
            'fuel_type' => FuelType::class,
            'weight_unit' => WeightUnit::class,
            'last_maintenance_at' => 'date',
            'next_maintenance_due_at' => 'date',
            'year' => 'integer',
            'weight_capacity' => 'integer',
            'towing_capacity' => 'integer',
            'payload_capacity' => 'integer',
            'gvwr' => 'integer',
            'axle_count' => 'integer',
            'specs' => 'array',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    /**
     * @return HasMany<FleetMaintenance, $this>
     */
    public function maintenanceLogs(): HasMany
    {
        return $this->hasMany(FleetMaintenance::class, 'fleet_id');
    }

    public function isTruck(): bool
    {
        return $this->type === FleetType::Truck;
    }

    public function isTrailer(): bool
    {
        return $this->type === FleetType::Trailer;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeTrucks($query)
    {
        return $query->where('type', FleetType::Truck);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeTrailers($query)
    {
        return $query->where('type', FleetType::Trailer);
    }
}
