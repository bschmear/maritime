<?php

namespace App\Domain\ServiceTicket\Models;

use App\Domain\AssetUnit\Models\AssetUnit;
use App\Domain\Attachment\Concerns\HasLinkedInventoryImages;
use App\Domain\Customer\Models\Customer;
use App\Domain\Document\Models\Document;
use App\Domain\Location\Models\Location;
use App\Domain\ServiceTicketRevision\Models\ServiceTicketRevision;
use App\Domain\ServiceTicketServiceItem\Models\ServiceTicketServiceItem;
use App\Domain\Subsidiary\Models\Subsidiary;
use App\Domain\Transaction\Models\Transaction;
use App\Domain\WorkOrder\Models\WorkOrder;
use App\Support\SignatureStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceTicket extends Model
{
    use HasLinkedInventoryImages;
    use SoftDeletes;

    protected $table = 'service_tickets';

    protected $guarded = ['id'];

    protected $casts = [
        'status' => 'integer',
        'expedite' => 'boolean',
        'approved' => 'boolean',
        'requires_reauthorization' => 'boolean',

        'pickup_delivery_requested_at' => 'date',

        'estimated_labor_hours' => 'decimal:2',
        'estimated_labor_amount' => 'decimal:2',
        'estimated_parts_amount' => 'decimal:2',
        'estimated_subtotal' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'estimated_tax' => 'decimal:2',
        'estimated_total' => 'decimal:2',
        'revised_estimated_total' => 'decimal:2',

        'signature_method' => 'integer',

        'signed_at' => 'datetime',
        'declined_at' => 'datetime',
        'reauthorized_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->service_ticket_number)) {
                $model->service_ticket_number = static::generateNextTicketNumber();
            }
        });
    }

    /**
     * Generate the next service ticket number.
     */
    protected static function generateNextTicketNumber(): string
    {
        $lastTicket = static::orderBy('id', 'desc')->first();

        if ($lastTicket && $lastTicket->service_ticket_number) {
            // Extract the number from ST-XXXX format
            if (preg_match('/ST-(\d+)/', $lastTicket->service_ticket_number, $matches)) {
                $nextNumber = (int) $matches[1] + 1;
            } else {
                // Fallback if format is different
                $nextNumber = (int) $lastTicket->id + 1000;
            }
        } else {
            // Start with ST-1000
            $nextNumber = 1000;
        }

        return 'ST-'.$nextNumber;
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function subsidiary(): BelongsTo
    {
        return $this->belongsTo(Subsidiary::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function assetUnit(): BelongsTo
    {
        return $this->belongsTo(AssetUnit::class);
    }

    public function asset_unit(): BelongsTo
    {
        return $this->belongsTo(AssetUnit::class);
    }

    public function paperSignatureDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'paper_signature_document_id');
    }

    public function serviceItems(): HasMany
    {
        return $this->hasMany(ServiceTicketServiceItem::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(ServiceTicketRevision::class);
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    public function getSignatureUrlAttribute(): ?string
    {
        return SignatureStorage::url($this->signature_file);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function recalculateEstimates(): void
    {
        $items = $this->serviceItems()->where('inactive', false)->get();

        $laborAmount = 0;
        $partsAmount = 0;
        $laborHours = 0;

        foreach ($items as $item) {
            $lineTotal = (float) $item->total_price;

            if ($item->billing_type === 1) { // Hourly
                $laborAmount += $lineTotal;
                $laborHours += (float) ($item->estimated_hours ?? 0);
            } else {
                $partsAmount += $lineTotal;
            }
        }

        $subtotal = $laborAmount + $partsAmount;
        $taxRate = (float) ($this->tax_rate ?? 0);
        $tax = $subtotal * ($taxRate / 100);
        $total = $subtotal + $tax;

        $this->update([
            'estimated_labor_hours' => $laborHours,
            'estimated_labor_amount' => $laborAmount,
            'estimated_parts_amount' => $partsAmount,
            'estimated_subtotal' => $subtotal,
            'estimated_tax' => $tax,
            'estimated_total' => $total,
        ]);
    }
}
