<?php

declare(strict_types=1);

namespace App\Domain\FleetMaintenance\Models;

use App\Domain\Fleet\Models\Fleet;
use App\Domain\MaintenanceType\Models\MaintenanceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class FleetMaintenance extends Model
{
    protected $table = 'fleet_maintenance_logs';

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
            'performed_at' => 'date',
            'cost' => 'decimal:2',
            'mileage' => 'integer',
            'hours' => 'integer',
        ];
    }

    public function fleet(): BelongsTo
    {
        return $this->belongsTo(Fleet::class, 'fleet_id');
    }

    /**
     * @return BelongsToMany<MaintenanceType, $this>
     */
    public function maintenanceTypes(): BelongsToMany
    {
        return $this->belongsToMany(
            MaintenanceType::class,
            'fleet_maintenance_log_maintenance_type',
            'fleet_maintenance_log_id',
            'maintenance_type_id'
        )->withTimestamps();
    }
}
