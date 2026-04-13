<?php

namespace App\Domain\Payment\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentMethodConfig extends Model
{
    protected $table = 'payment_methods_config';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'is_active' => 'boolean',
        'position' => 'integer',
    ];

    public function processorPaymentMethods(): HasMany
    {
        return $this->hasMany(ProcessorPaymentMethod::class, 'payment_method_code', 'code');
    }
}
