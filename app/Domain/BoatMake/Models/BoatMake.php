<?php

namespace App\Domain\BoatMake\Models;

use App\Domain\InventoryItem\Models\InventoryItem;
use App\Domain\Vendor\Models\Vendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BoatMake extends Model
{
    protected $table = 'boat_make';

    protected $fillable = [
        'display_name',
        'slug',
        'is_custom',
        'logo',
        'active',
        'asset_types',
        'brand_key',
        'vendor_id',
    ];

    protected $casts = [
        'is_custom' => 'boolean',
        'active' => 'boolean',
        'asset_types' => 'array',
    ];

    public function items()
    {
        return $this->hasMany(InventoryItem::class, 'boat_make_id', 'id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function invoiceImportProfile(): HasOne
    {
        return $this->hasOne(BoatMakeInvoiceImportProfile::class, 'boat_make_id');
    }
}
