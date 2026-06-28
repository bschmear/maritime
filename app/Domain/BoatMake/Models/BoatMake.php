<?php

namespace App\Domain\BoatMake\Models;

use App\Domain\Document\Models\Document;
use App\Domain\InventoryItem\Models\InventoryItem;
use App\Domain\Vendor\Models\Vendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

class BoatMake extends Model
{
    protected $table = 'boat_make';

    protected $fillable = [
        'display_name',
        'slug',
        'is_custom',
        'use_default_logo',
        'default_brand_image',
        'website_url',
        'description',
        'custom_logo_id',
        'active',
        'asset_types',
        'brand_key',
        'vendor_id',
    ];

    protected $casts = [
        'is_custom' => 'boolean',
        'use_default_logo' => 'boolean',
        'active' => 'boolean',
        'asset_types' => 'array',
    ];

    protected $appends = [
        'logo_url',
    ];

    public function items()
    {
        return $this->hasMany(InventoryItem::class, 'boat_make_id', 'id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function customLogo(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'custom_logo_id');
    }

    public function invoiceImportProfile(): HasOne
    {
        return $this->hasOne(BoatMakeInvoiceImportProfile::class, 'boat_make_id');
    }

    public function getLogoUrlAttribute(): ?string
    {
        if ($this->use_default_logo && filled($this->default_brand_image)) {
            return $this->default_brand_image;
        }

        if (! $this->custom_logo_id) {
            return null;
        }

        $document = $this->relationLoaded('customLogo')
            ? $this->customLogo
            : Document::find($this->custom_logo_id);

        if (! $document || ! $document->file) {
            return null;
        }

        $cdnUrl = config('filesystems.disks.s3.cdn_url');
        if ($cdnUrl) {
            return rtrim($cdnUrl, '/').'/'.$document->file;
        }

        return Storage::disk('s3')->temporaryUrl(
            $document->file,
            now()->addDays(7)
        );
    }
}
