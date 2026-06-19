<?php

declare(strict_types=1);

namespace App\Domain\BillItem\Models;

use App\Domain\Bill\Models\Bill;
use App\Domain\ChartOfAccount\Models\ChartOfAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillItem extends Model
{
    protected $table = 'bill_items';

    protected $fillable = [
        'bill_id',
        'quickbooks_line_id',
        'amount',
        'description',
        'detail_type',
        'chart_of_account_id',
        'expense_account_ref_id',
        'expense_account_ref_name',
        'item_ref_id',
        'item_ref_name',
        'quantity',
        'unit_price',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'position' => 'integer',
        ];
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function chartOfAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'chart_of_account_id');
    }
}
