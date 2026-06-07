<?php

namespace App\Models;

use App\Casts\PaymentTermsCast;
use App\Domain\Payment\Models\PaymentConfiguration;
use App\Enums\SMS;
use App\Support\Tenant\AccountSettingsCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

class AccountSettings extends Model
{
    private static ?self $resolved = null;

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
        'service_ticket_ack_text',
        'estimate_threshold_percent',
        'service_ticket_signed_notify_user_id',
        'default_contract_terms',
        'default_payment_term',
        'default_payment_terms',
        'default_delivery_terms',
        'workday_hours',
        'start_time',
        'allow_overlap',
        'consignment_fee_percent',
        'consignment_terms',
        'sms_enabled',
        'sandbox_mode',
        /** When true, the tenant setup wizard is finished and should not show on the dashboard. */
        'onboarding_complete',
        /** When true, the post-onboarding “Account” overview modal has been dismissed. */
        'account_overviewed',
        /** When true, the workspace setup tour is finished and the tour widget is hidden. */
        'account_setup_complete',
    ];

    protected $casts = [
        'logo_file_size' => 'integer',
        'week_starts_on_monday' => 'boolean',
        'auto_assign_work_orders' => 'boolean',
        'workday_hours' => 'integer',
        'allow_overlap' => 'boolean',
        'settings' => 'array',
        'default_payment_term' => PaymentTermsCast::class,
        'consignment_fee_percent' => 'decimal:2',
        'sms_enabled' => 'boolean',
        'sandbox_mode' => 'boolean',
        'onboarding_complete' => 'boolean',
        'account_overviewed' => 'boolean',
        'account_setup_complete' => 'boolean',
    ];

    protected $appends = ['logo_url'];

    /**
     * Get the account settings for the current tenant.
     * Creates default settings if none exist.
     *
     * Request memo + Redis cache (see {@see AccountSettingsCache}).
     */
    public static function getCurrent(): self
    {
        if (self::$resolved !== null) {
            return self::$resolved;
        }

        return self::$resolved = AccountSettingsCache::get();
    }

    public static function defaultConsignmentTerms(): string
    {
        return <<<'TEXT'
This consignment agreement authorizes the dealer to offer the described property for sale on behalf of the owner. The owner retains title until a sale is completed and agrees to cooperate with reasonable requests for showings, documentation, and keeping the property in saleable condition.

The dealer will use commercially reasonable efforts to market and sell the property at the asking and minimum prices stated on this agreement. Sale proceeds, less the agreed consignment fee and any documented expenses approved in writing, will be remitted to the owner upon closing.

Either party may terminate this agreement in accordance with its terms and applicable law. Until termination or sale, the owner remains responsible for insurance, applicable storage or yard charges, and the accuracy of the information provided on this agreement.
TEXT;
    }

    /**
     * Seed default consignment narrative terms when none have been configured yet.
     */
    public static function ensureConsignmentDefaults(): void
    {
        $settings = static::getCurrent();

        if (blank($settings->consignment_terms)) {
            $settings->consignment_terms = static::defaultConsignmentTerms();
            $settings->save();
        }
    }

    /**
     * Master SMS toggle for the current tenant (account_settings row).
     */
    public function smsGloballyEnabled(): bool
    {
        return (bool) $this->sms_enabled;
    }

    public function smsSandboxMode(): bool
    {
        return (bool) $this->sandbox_mode;
    }

    /**
     * Whether this tenant wants SMS for a notification category.
     */
    public function wantsSms(SMS|string $type): bool
    {
        if (! $this->smsGloballyEnabled()) {
            return false;
        }

        $enum = $type instanceof SMS ? $type : SMS::tryFrom($type);
        if ($enum === null) {
            return false;
        }

        $pref = $this->smsNotificationPreference;
        if ($pref === null) {
            return false;
        }

        return (bool) $pref->getAttribute($enum->notifyColumn());
    }

    public function smsNotificationPreference(): HasOne
    {
        return $this->hasOne(SmsNotificationPreference::class);
    }

    public function getOrCreateSmsNotificationPreference(): SmsNotificationPreference
    {
        return $this->smsNotificationPreference()->firstOrCreate(
            ['account_settings_id' => $this->id],
            collect(SMS::cases())->mapWithKeys(
                fn (SMS $case) => [$case->notifyColumn() => false],
            )->all(),
        );
    }

    /**
     * Get the full logo URL.
     */
    public function getLogoUrlAttribute(): ?string
    {
        if (! $this->logo_file) {
            return null;
        }

        $cdnUrl = config('filesystems.disks.s3.cdn_url');
        if ($cdnUrl) {
            // Remove trailing slash from CDN URL to avoid double slashes
            $cdnUrl = rtrim($cdnUrl, '/');

            return $cdnUrl.'/'.$this->logo_file;
        }

        // Generate temporary signed URL with cache headers (valid for 7 days)
        return Storage::disk('s3')->temporaryUrl(
            $this->logo_file,
            now()->addDays(7),
            [
                'ResponseCacheControl' => 'public, max-age=604800',
            ]
        );
    }

    /**
     * Clear the account settings cache.
     */
    public static function clearCache(): void
    {
        self::$resolved = null;
        AccountSettingsCache::forget();
    }

    /**
     * Payment configuration rows for this account.
     */
    public function paymentConfigurations(): HasMany
    {
        return $this->hasMany(PaymentConfiguration::class, 'account_settings_id');
    }

    protected static function boot()
    {
        parent::boot();

        static::saved(function (self $settings) {
            static::$resolved = $settings;
            static::clearCache();
        });

        static::deleted(function () {
            static::clearCache();
        });

        static::deleting(function ($settings) {
            if ($settings->logo_file && Storage::disk('s3')->exists($settings->logo_file)) {
                Storage::disk('s3')->delete($settings->logo_file);
            }
        });
    }
}
