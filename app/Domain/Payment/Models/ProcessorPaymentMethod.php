<?php

namespace App\Domain\Payment\Models;

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
}
