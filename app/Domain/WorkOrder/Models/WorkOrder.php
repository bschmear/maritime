<?php

namespace App\Domain\WorkOrder\Models;

use App\Domain\AssetUnit\Models\AssetUnit;
use App\Domain\Attachment\Concerns\HasLinkedInventoryImages;
use App\Domain\Checklist\Models\Checklist;
use App\Domain\Customer\Models\Customer;
use App\Domain\InventoryItem\Models\InventoryItem;
use App\Domain\InventoryUnit\Models\InventoryUnit;
use App\Domain\Location\Models\Location;
use App\Domain\ServiceTicket\Models\ServiceTicket;
use App\Domain\Subsidiary\Models\Subsidiary;
use App\Domain\Task\Models\Task;
use App\Domain\User\Models\User;
use App\Domain\WarrantyClaim\Models\WarrantyClaim;
use App\Domain\WorkOrderServiceItem\Models\WorkOrderServiceItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class WorkOrder extends Model
{
    use HasLinkedInventoryImages;
    use SoftDeletes;

    protected $table = 'work_orders';

    /**
     * Mass assignable fields
     * Keep this permissive since you plan for dynamic/custom fields
     */
    protected $guarded = ['id'];

    /**
     * Human-readable label (WO-1001). There is no display_name column; work_order_number is assigned on create.
     */
    protected $appends = ['display_name'];

    /**
     * Casts
     */
    protected $casts = [
        'status' => 'integer',
        'priority' => 'integer',
        'type' => 'integer',

        'billable' => 'boolean',
        'draft' => 'boolean',
        'warranty' => 'boolean',
        'has_warranty' => 'boolean',
        'warranty_closed' => 'boolean',
        'requires_manager_approval' => 'boolean',

        'technician_submitted_at' => 'datetime',
        'manager_signed_off_at' => 'datetime',

        'scheduled_start_at' => 'datetime',
        'scheduled_end_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'due_at' => 'datetime',

        'estimated_tax' => 'decimal:2',
        'tax_rate' => 'decimal:2',

        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (WorkOrder $workOrder) {
            if (empty($workOrder->uuid)) {
                $workOrder->uuid = (string) Str::uuid();
            }
            if ($workOrder->work_order_number === null || $workOrder->work_order_number === '') {
                $maxNumber = static::withTrashed()->max('work_order_number');
                $workOrder->work_order_number = $maxNumber !== null
                    ? ((int) $maxNumber) + 1
                    : 1000;
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function serviceTicket(): BelongsTo
    {
        return $this->belongsTo(
            ServiceTicket::class
        );
    }

    public function service_ticket(): BelongsTo
    {
        return $this->belongsTo(
            ServiceTicket::class
        );
    }

    public function subsidiary(): BelongsTo
    {
        return $this->belongsTo(
            Subsidiary::class
        );
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(
            Customer::class
        );
    }

    public function assetUnit(): BelongsTo
    {
        return $this->belongsTo(
            AssetUnit::class
        );
    }

    public function asset_unit(): BelongsTo
    {
        return $this->belongsTo(
            AssetUnit::class
        );
    }

    public function inventoryUnit(): BelongsTo
    {
        return $this->belongsTo(
            InventoryUnit::class
        );
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(
            InventoryItem::class
        );
    }

    public function inventory_item(): BelongsTo
    {
        return $this->belongsTo(
            InventoryItem::class,
            'inventory_item_id'
        );
    }

    public function serviceItems()
    {
        return $this->hasMany(WorkOrderServiceItem::class);
    }

    public function warrantyClaims(): HasMany
    {
        return $this->hasMany(WarrantyClaim::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'assigned_user_id'
        );
    }

    public function assigned_user(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'assigned_user_id'
        );
    }

    public function managerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    public function manager_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    public function technicianSubmittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_submitted_by');
    }

    public function managerSignedOffBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_signed_off_by');
    }

    public function approvalChecklist(): MorphOne
    {
        return $this->morphOne(Checklist::class, 'checklistable');
    }

    public function requested_by_user(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'requested_by_user_id'
        );
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(
            Location::class
        );
    }

    public function tasks(): MorphMany
    {
        return $this->morphMany(Task::class, 'relatable');
    }

    // public function lineItems(): HasMany
    // {
    //     return $this->hasMany(
    //         WorkOrderLineItem::class
    //     );
    // }

    /*
    |--------------------------------------------------------------------------
    | Helpers / Derived Values
    |--------------------------------------------------------------------------
    */

    public function recalculateTotals(): void
    {
        $labor = $this->lineItems()
            ->where('type', LineItemType::LABOR)
            ->sum('total_cost');

        $parts = $this->lineItems()
            ->where('type', LineItemType::PART)
            ->sum('total_cost');

        $this->labor_cost = $labor;
        $this->parts_cost = $parts;
        $this->total_cost = $labor + $parts;

        $this->saveQuietly();
    }

    public function getDisplayNameAttribute(): string
    {
        return 'WO-'.($this->work_order_number ?: $this->id ?: '???');
    }
}
