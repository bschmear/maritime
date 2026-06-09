<?php

namespace App\Domain\AssetUnit\Models;

use App\Domain\Asset\Models\Asset;
use App\Domain\AssetVariant\Models\AssetVariant;
use App\Domain\ConsignmentAgreement\Models\ConsignmentAgreement;
use App\Domain\Customer\Models\Customer;
use App\Domain\Delivery\Models\Delivery;
use App\Domain\Document\Models\Document;
use App\Domain\InventoryImage\Models\InventoryImage;
use App\Domain\Location\Models\Location;
use App\Domain\MsoRecord\Models\MsoRecord;
use App\Domain\ServiceTicket\Models\ServiceTicket;
use App\Domain\Subsidiary\Models\Subsidiary;
use App\Domain\Transaction\Models\Transaction;
use App\Domain\Transaction\Models\TransactionLineItem;
use App\Domain\Vendor\Models\Vendor;
use App\Domain\WorkOrder\Models\WorkOrder;
use App\Models\Concerns\HasDocuments;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class AssetUnit extends Model
{
    use HasDocuments;

    protected $fillable = [
        'asset_id',
        'asset_variant_id',
        'serial_number',
        'hin',
        'sku',
        'condition',
        'status',
        'inactive',
        'is_customer_owned',
        'is_consignment',
        'engine_hours',
        'last_service_at',
        'warranty_expires_at',
        'cost',
        'asking_price',
        'sold_price',
        'price_history',
        'vendor_id',
        'customer_id',
        'location_id',
        'subsidiary_id',
        'in_service_at',
        'out_of_service_at',
        'sold_at',
        'attributes',
        'notes',
    ];

    protected $casts = [
        'condition' => 'integer',
        'status' => 'integer',
        'inactive' => 'boolean',
        'is_customer_owned' => 'boolean',
        'is_consignment' => 'boolean',
        'engine_hours' => 'decimal:1',
        'last_service_at' => 'date',
        'warranty_expires_at' => 'date',
        'cost' => 'decimal:2',
        'asking_price' => 'decimal:2',
        'sold_price' => 'decimal:2',
        'price_history' => 'array',
        'in_service_at' => 'datetime',
        'out_of_service_at' => 'datetime',
        'sold_at' => 'datetime',
        'attributes' => 'array',
    ];

    protected $appends = ['display_name'];

    /**
     * Unit belongs to an inventory item
     */
    public function asset()
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    public function assetVariant()
    {
        return $this->belongsTo(AssetVariant::class, 'asset_variant_id');
    }

    /**
     * Picker search: hull ID (HIN), serial, SKU, catalog asset name, or variant name.
     */
    public function scopeWhereMatchesPickerSearch(Builder $query, string $rawSearch): Builder
    {
        $term = trim($rawSearch);
        if ($term === '') {
            return $query;
        }

        $searchTerm = '%'.strtolower($term).'%';
        $table = $query->getModel()->getTable();

        return $query->where(function ($q) use ($searchTerm, $term, $table) {
            $q->whereRaw('LOWER(COALESCE('.$table.'.serial_number, \'\')) LIKE ?', [$searchTerm])
                ->orWhereRaw('LOWER(COALESCE('.$table.'.hin, \'\')) LIKE ?', [$searchTerm])
                ->orWhereRaw('LOWER(COALESCE('.$table.'.sku, \'\')) LIKE ?', [$searchTerm])
                ->orWhereRaw('CAST('.$table.'.id AS TEXT) LIKE ?', [$searchTerm])
                ->orWhereHas('asset', fn ($aq) => $aq->whereRaw('LOWER(COALESCE(display_name, \'\')) LIKE ?', [$searchTerm]))
                ->orWhereHas('assetVariant', function ($vq) use ($searchTerm) {
                    $vq->whereRaw('LOWER(COALESCE(display_name, \'\')) LIKE ?', [$searchTerm])
                        ->orWhereRaw('LOWER(COALESCE(name, \'\')) LIKE ?', [$searchTerm]);
                });

            if (ctype_digit($term)) {
                $q->orWhere($table.'.id', '=', (int) $term);
            }
        });
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function subsidiary()
    {
        return $this->belongsTo(Subsidiary::class, 'subsidiary_id');
    }

    /**
     * Optional vendor (consignment)
     */
    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    /**
     * Physical location of the unit
     */
    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Generate a display name for the unit
     * Format: Asset Name - Unit Identifier
     */
    public function getDisplayNameAttribute()
    {
        $assetName = $this->asset?->display_name ?? 'Unknown Asset';
        $unitIdentifier = '';

        // Priority: Hull ID (HIN) > Serial Number > SKU > "Unit #{id}"
        if (! empty($this->hin)) {
            $unitIdentifier = "Hull ID: {$this->hin}";
        } elseif (! empty($this->serial_number)) {
            $unitIdentifier = "SN: {$this->serial_number}";
        } elseif (! empty($this->sku)) {
            $unitIdentifier = "SKU: {$this->sku}";
        } else {
            $unitIdentifier = "Unit #{$this->id}";
        }

        return "{$assetName} - {$unitIdentifier}";
    }

    public function images()
    {
        return $this->morphMany(InventoryImage::class, 'imageable');
    }

    public function consignmentAgreements(): HasMany
    {
        return $this->hasMany(ConsignmentAgreement::class, 'asset_unit_id');
    }

    public function serviceTickets(): HasMany
    {
        return $this->hasMany(ServiceTicket::class, 'asset_unit_id');
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class, 'asset_unit_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class, 'asset_unit_id');
    }

    /** Deal lines that reference this physical unit. */
    public function transactionLineItems(): HasMany
    {
        return $this->hasMany(TransactionLineItem::class, 'asset_unit_id');
    }

    public function msoRecords(): HasMany
    {
        return $this->hasMany(MsoRecord::class, 'asset_unit_id')->orderByDesc('created_at');
    }

    public function msoSourceDocument(): ?Document
    {
        return $this->documents()
            ->wherePivot('role', 'mso')
            ->orderBy('documentables.sort_order')
            ->orderBy('documents.id')
            ->first();
    }

    /**
     * Transactions that include this unit on at least one line item (not a direct FK on transactions).
     */
    public function transactions(): HasManyThrough
    {
        return $this->hasManyThrough(
            Transaction::class,
            TransactionLineItem::class,
            'asset_unit_id',
            'id',
            'id',
            'parent_id',
        )->where(
            (new TransactionLineItem)->getTable().'.parent_type',
            Transaction::class,
        );
    }
}
