<?php

declare(strict_types=1);

namespace App\Domain\MaintenanceType\Models;

use App\Domain\FleetMaintenance\Models\FleetMaintenance;
use App\Enums\Fleet\MaintenanceTypeAppliesTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MaintenanceType extends Model
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
            'applies_to' => MaintenanceTypeAppliesTo::class,
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsToMany<FleetMaintenance, $this>
     */
    public function fleetMaintenanceLogs(): BelongsToMany
    {
        return $this->belongsToMany(
            FleetMaintenance::class,
            'fleet_maintenance_log_maintenance_type',
            'maintenance_type_id',
            'fleet_maintenance_log_id'
        )->withTimestamps();
    }
}
