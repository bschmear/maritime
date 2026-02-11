<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountSettings extends Model
{
    protected $table = 'account_settings';

    protected $fillable = [
        'timezone',
        'logo_file',
        'logo_file_extension',
        'logo_file_size',
        'brand_color',
        'date_format',
        'time_format',
        'currency',
        'week_starts_on_monday',
        'auto_assign_work_orders',
        'settings',
    ];

    protected $casts = [
        'logo_file_size' => 'integer',
        'week_starts_on_monday' => 'boolean',
        'auto_assign_work_orders' => 'boolean',
        'settings' => 'array',
    ];

    protected $appends = ['logo_url'];

    /**
     * Get the account settings for the current tenant.
     * Creates default settings if none exist.
     * Caching is currently disabled to avoid tenancy cache tagging issues.
     */
    public static function getCurrent(): self
    {
        // For now, skip caching entirely to avoid tenancy cache tagging issues
        // TODO: Re-enable caching when Redis is properly configured
        $settings = static::first();

        if (!$settings) {
            $settings = static::create([
                'timezone' => 'America/Chicago',
                'date_format' => 'Y-m-d',
                'time_format' => 'H:i',
                'currency' => 'USD',
                'week_starts_on_monday' => false,
                'auto_assign_work_orders' => false,
                'brand_color' => '#3B82F6', // Blue-500
            ]);
        }

        return $settings;
    }

    /**
     * Get the full logo URL.
     */
    public function getLogoUrlAttribute(): ?string
    {
        if (!$this->logo_file) {
            return null;
        }

        $cdnUrl = config('filesystems.disks.s3.cdn_url');
        if ($cdnUrl) {
            // Remove trailing slash from CDN URL to avoid double slashes
            $cdnUrl = rtrim($cdnUrl, '/');
            return $cdnUrl . '/' . $this->logo_file;
        }

        // Generate temporary signed URL with cache headers (valid for 7 days)
        return \Illuminate\Support\Facades\Storage::disk('s3')->temporaryUrl(
            $this->logo_file,
            now()->addDays(7),
            [
                'ResponseCacheControl' => 'public, max-age=604800',
            ]
        );
    }

    /**
     * Clear the account settings cache.
     * Currently a no-op since caching is disabled.
     */
    public static function clearCache(): void
    {
        // Caching is currently disabled to avoid tenancy cache tagging issues
        // TODO: Re-enable when Redis is properly configured
    }

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        // Clear cache when settings are updated
        static::saved(function () {
            static::clearCache();
        });

        static::deleted(function () {
            static::clearCache();
        });

        static::deleting(function ($settings) {
            if ($settings->logo_file && \Illuminate\Support\Facades\Storage::disk('s3')->exists($settings->logo_file)) {
                \Illuminate\Support\Facades\Storage::disk('s3')->delete($settings->logo_file);
            }
        });
    }
}