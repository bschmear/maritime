<?php

namespace App\Domain\InventoryImage\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use App\Models\User;

class InventoryImage extends Model
{
    protected $table = 'inventory_images';

    /**
     * Mass assignable fields
     */
    protected $fillable = [
        'imageable_type',
        'imageable_id',
        'display_name',
        'description',
        'file',
        'file_extension',
        'file_size',
        'sort_order',
        'role',
        'is_primary',
        'created_by_id',
        'updated_by_id',
    ];

    /**
     * Casts
     */
    protected $casts = [
        'file_size' => 'integer',
        'sort_order' => 'integer',
        'is_primary' => 'boolean',
    ];

    protected $appends = ['url'];

    /**
     * Polymorphic parent
     */
    public function imageable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Creator relationships
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }

    /**
     * Scopes
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /**
     * Accessors
     */
    public function getUrlAttribute(): string
    {
        if (!$this->file) {
            return '';
        }

        $cdnUrl = config('filesystems.disks.s3.cdn_url');
        if ($cdnUrl) {
            // Remove trailing slash from CDN URL to avoid double slashes
            $cdnUrl = rtrim($cdnUrl, '/');
            return $cdnUrl . '/' . $this->file;
        }

        // Generate temporary signed URL with cache headers (valid for 7 days)
        return Storage::disk('s3')->temporaryUrl(
            $this->file,
            now()->addDays(7),
            [
                'ResponseCacheControl' => 'public, max-age=604800',
            ]
        );
    }

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($image) {
            if ($image->file && Storage::disk('s3')->exists($image->file)) {
                Storage::disk('s3')->delete($image->file);
            }
        });
    }
}
