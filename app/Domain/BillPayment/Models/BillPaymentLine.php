<?php

declare(strict_types=1);

namespace App\Domain\BillPayment\Models;

use App\Domain\Bill\Models\Bill;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillPaymentLine extends Model
{
    protected $table = 'bill_payment_lines';

    protected $fillable = [
        'bill_payment_id',
        'bill_id',
        'quickbooks_bill_id',
        'amount',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'position' => 'integer',
        ];
    }

    public function billPayment(): BelongsTo
    {
        return $this->belongsTo(BillPayment::class, 'bill_payment_id');
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }
}
