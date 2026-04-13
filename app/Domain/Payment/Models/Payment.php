<?php

namespace App\Domain\Payment\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Payment extends Model
{
    use SoftDeletes;

    protected $table = 'payments';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'amount' => 'decimal:2',
        'surcharge_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'processor_response' => 'array',
        'paid_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Payment $payment): void {
            if (empty($payment->uuid)) {
                $payment->uuid = (string) Str::uuid();
            }
            if ($payment->sequence === null || $payment->sequence === '') {
                $next = (int) static::query()->max('sequence');

                $payment->sequence = $next + 1;
            }
        });
    }
}
