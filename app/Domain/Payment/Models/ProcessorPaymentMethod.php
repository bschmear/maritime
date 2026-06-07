<?php

namespace App\Domain\Payment\Models;

use App\Support\Tenant\PaymentConfigurationCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessorPaymentMethod extends Model
{
    protected $table = 'processor_payment_methods';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'is_enabled' => 'boolean',
    ];

    public function configuration(): BelongsTo
    {
        return $this->belongsTo(PaymentConfiguration::class, 'configuration_id');
    }

    public function methodConfig(): BelongsTo
    {
        return $this->belongsTo(PaymentMethodConfig::class, 'payment_method_code', 'code');
    }

    protected static function booted(): void
    {
        $forget = function (self $method): void {
            $accountSettingsId = PaymentConfiguration::query()
                ->where('id', $method->configuration_id)
                ->value('account_settings_id');

            if ($accountSettingsId !== null) {
                PaymentConfigurationCache::forgetForAccount((int) $accountSettingsId);
            }
        };

        static::saved($forget);
        static::deleted($forget);
    }
}
