<?php

declare(strict_types=1);

namespace App\Domain\BillPayment\Models;

use App\Domain\Bill\Models\Bill;
use App\Domain\Vendor\Models\Vendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BillPayment extends Model
{
    use SoftDeletes;

    protected $table = 'billpayments';

    protected $appends = ['display_name'];

    protected $fillable = [
        'uuid',
        'sequence',
        'quickbooks_bill_payment_id',
        'quickbooks_sync_token',
        'vendor_id',
        'quickbooks_vendor_id',
        'doc_number',
        'txn_date',
        'total_amt',
        'pay_type',
        'ap_account_ref_id',
        'ap_account_ref_name',
        'bank_account_ref_id',
        'bank_account_ref_name',
        'cc_account_ref_id',
        'cc_account_ref_name',
        'check_print_status',
        'currency_code',
        'exchange_rate',
        'private_note',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'txn_date' => 'date',
            'total_amt' => 'decimal:2',
            'exchange_rate' => 'decimal:6',
            'meta' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (BillPayment $payment): void {
            if (empty($payment->uuid)) {
                $payment->uuid = (string) Str::uuid();
            }
            if ($payment->sequence === null) {
                $next = (int) (DB::table('billpayments')->max('sequence') ?? 999);
                $payment->sequence = $next + 1;
            }
        });
    }

    public function getDisplayNameAttribute(): string
    {
        return 'BPAY-'.$this->sequence;
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BillPaymentLine::class, 'bill_payment_id')->orderBy('position')->orderBy('id');
    }

    public function isQuickbooksManaged(): bool
    {
        return filled($this->quickbooks_bill_payment_id);
    }

    /**
     * Bills paid by this payment (via line items).
     */
    public function bills(): BelongsToMany
    {
        return $this->belongsToMany(Bill::class, 'bill_payment_lines', 'bill_payment_id', 'bill_id')
            ->withPivot(['amount', 'position', 'quickbooks_bill_id'])
            ->withTimestamps();
    }
}
