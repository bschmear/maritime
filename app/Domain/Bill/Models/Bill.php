<?php

declare(strict_types=1);

namespace App\Domain\Bill\Models;

use App\Domain\Bill\Support\BillStatusResolver;
use App\Domain\BillItem\Models\BillItem;
use App\Domain\BillPayment\Models\BillPaymentLine;
use App\Domain\ChartOfAccount\Models\ChartOfAccount;
use App\Domain\Financing\Models\Financing;
use App\Domain\Vendor\Models\Vendor;
use App\Enums\Bill\Status;
use App\Enums\Financing\BillType as FinancingBillType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Bill extends Model
{
    use SoftDeletes;

    protected $table = 'bills';

    protected $appends = ['display_name'];

    protected $fillable = [
        'uuid',
        'sequence',
        'quickbooks_bill_id',
        'quickbooks_sync_token',
        'vendor_id',
        'financing_id',
        'financing_bill_type',
        'quickbooks_vendor_id',
        'chart_of_account_id',
        'doc_number',
        'txn_date',
        'due_date',
        'ap_account_ref_id',
        'ap_account_ref_name',
        'department_ref_id',
        'department_ref_name',
        'total_amt',
        'balance',
        'currency_code',
        'exchange_rate',
        'private_note',
        'status',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'txn_date' => 'date',
            'due_date' => 'date',
            'total_amt' => 'decimal:2',
            'balance' => 'decimal:2',
            'exchange_rate' => 'decimal:6',
            'meta' => 'array',
            'financing_bill_type' => FinancingBillType::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Bill $bill): void {
            if (empty($bill->uuid)) {
                $bill->uuid = (string) Str::uuid();
            }
            if ($bill->sequence === null) {
                $next = (int) (DB::table('bills')->max('sequence') ?? 999);
                $bill->sequence = $next + 1;
            }
        });
    }

    public function getDisplayNameAttribute(): string
    {
        return 'BILL-'.$this->sequence;
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function financing(): BelongsTo
    {
        return $this->belongsTo(Financing::class, 'financing_id');
    }

    public function chartOfAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'chart_of_account_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BillItem::class)->orderBy('position')->orderBy('id');
    }

    public function billPaymentLines(): HasMany
    {
        return $this->hasMany(BillPaymentLine::class);
    }

    public function isQuickbooksManaged(): bool
    {
        return filled($this->quickbooks_bill_id);
    }

    public function isPaid(): bool
    {
        return $this->status === Status::Paid->value;
    }

    /**
     * Bills synced from QuickBooks or marked paid cannot be fully edited in Helmful.
     *
     * @var list<string>
     */
    public const RESTRICTED_EDIT_ALLOWED_FIELDS = [
        'vendor_id',
        'chart_of_account_id',
    ];

    public function hasRestrictedEditing(): bool
    {
        return $this->isQuickbooksManaged() || $this->isPaid();
    }

    public function quickbooksBillUrl(): ?string
    {
        $id = (string) ($this->quickbooks_bill_id ?? '');
        if ($id === '') {
            return null;
        }

        $stored = is_array($this->meta) ? ($this->meta['quickbooks_bill_url'] ?? null) : null;
        if (is_string($stored) && $stored !== '') {
            return $stored;
        }

        $host = config('services.quickbooks.environment') === 'production'
            ? 'https://qbo.intuit.com'
            : 'https://sandbox.qbo.intuit.com';

        return "{$host}/app/bill?txnId={$id}";
    }

    public function resolveStatusFromAmounts(bool $void = false): Status
    {
        return BillStatusResolver::resolve(
            (float) $this->balance,
            $this->due_date,
            $void || $this->status === Status::Void->value,
        );
    }

    public function refreshStatus(bool $void = false): void
    {
        $this->status = $this->resolveStatusFromAmounts($void)->value;
        $this->save();
    }

    public function applyPayment(float $amount): void
    {
        $payment = round($amount, 2);
        $balance = max(0, round((float) $this->balance - $payment, 2));
        $this->balance = $balance;
        $this->status = BillStatusResolver::resolveValue($balance, $this->due_date);
        $this->save();
    }
}
