<?php

namespace App\Domain\PaymentRefund\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PaymentRefund extends Model
{
    protected $table = 'payment_refunds';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'amount' => 'decimal:2',
        'processor_response' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (PaymentRefund $refund): void {
            if (empty($refund->uuid)) {
                $refund->uuid = (string) Str::uuid();
            }
            if ($refund->sequence === null || $refund->sequence === '') {
                $next = (int) static::query()->max('sequence');
                $refund->sequence = $next + 1;
            }
        });
    }
}
