<?php

namespace App\Domain\Invoice\Models;

use App\Domain\Asset\Models\Asset;
use App\Domain\InventoryItem\Models\InventoryItem;
use App\Domain\InvoiceItem\Models\InvoiceItem;
use App\Models\Concerns\HasDocuments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Invoice extends Model
{
    use HasDocuments, SoftDeletes;

    protected $table = 'invoices';

    protected $appends = ['display_name'];

    protected $fillable = [
        'transaction_id',
        'contract_id',
        'contact_id',

        'uuid',
        'sequence',

        'status',

        'subtotal',
        'tax_total',
        'discount_total',
        'fees_total',
        'total',

        'amount_paid',
        'amount_due',

        'currency',

        'payment_term',
        'due_at',

        'customer_name',
        'customer_email',
        'customer_phone',

        'billing_address_line1',
        'billing_address_line2',
        'billing_city',
        'billing_state',
        'billing_postal',
        'billing_country',

        'notes',
        'meta',

        'sent_at',
        'viewed_at',
        'paid_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'fees_total' => 'decimal:2',
        'total' => 'decimal:2',

        'amount_paid' => 'decimal:2',
        'amount_due' => 'decimal:2',

        'meta' => 'array',

        'due_at' => 'datetime',
        'sent_at' => 'datetime',
        'viewed_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($invoice) {
            if (empty($invoice->uuid)) {
                $invoice->uuid = (string) Str::uuid();
            }
            $next = (int) (DB::table('invoices')->max('sequence') ?? 999);
            $invoice->sequence = $next + 1;
        });
    }

    /**
     * Eager loads for invoice document UIs (tenant show, public view, emails).
     *
     * @return array<string, callable>
     */
    public static function documentEagerLoads(): array
    {
        return [
            'items' => fn ($q) => $q->orderBy('position')->orderBy('id')->with([
                'itemable' => function (MorphTo $morph) {
                    $morph->constrain([
                        Asset::class => fn ($query) => $query->select(['id', 'display_name', 'name']),
                        InventoryItem::class => fn ($query) => $query->select(['id', 'display_name', 'name']),
                    ]);
                },
            ]),
            'transaction' => fn ($q) => $q->select(['id', 'sequence', 'subsidiary_id', 'location_id'])
                ->with([
                    'subsidiary' => fn ($sq) => $sq->select(['id', 'display_name']),
                    'location' => fn ($lq) => $lq->select(['id', 'display_name']),
                ]),
            'contract' => fn ($q) => $q->select(['id', 'sequence']),
            'contact' => fn ($q) => $q->select(['id', 'display_name', 'first_name', 'last_name', 'email', 'phone', 'mobile']),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('position');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Transaction\Models\Transaction::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Contract\Models\Contract::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Contact\Models\Contact::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', ['sent', 'viewed', 'partial']);
    }

    public function scopeOverdue($query)
    {
        return $query
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->where('status', '!=', 'paid');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function markAsSent(): void
    {
        $data = ['sent_at' => now()];
        if ($this->status === 'draft') {
            $data['status'] = 'sent';
        }
        $this->update($data);
    }

    public function markAsViewed(): void
    {
        if ($this->status === 'void') {
            return;
        }

        $data = [];
        if (! $this->viewed_at) {
            $data['viewed_at'] = now();
        }
        if (in_array($this->status, ['draft', 'sent'], true)) {
            $data['status'] = 'viewed';
        }
        if ($data !== []) {
            $this->update($data);
        }
    }

    public function applyPayment(float $amount): void
    {
        $newPaid = $this->amount_paid + $amount;
        $newDue = max(0, $this->total - $newPaid);

        $status = match (true) {
            $newDue <= 0 => 'paid',
            $newPaid > 0 => 'partial',
            default => $this->status,
        };

        $this->update([
            'amount_paid' => $newPaid,
            'amount_due' => $newDue,
            'status' => $status,
            'paid_at' => $newDue <= 0 ? now() : $this->paid_at,
        ]);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isOverdue(): bool
    {
        return $this->due_at && $this->due_at->isPast() && ! $this->isPaid();
    }

    /*
    |--------------------------------------------------------------------------
    | Formatting (nice for UI)
    |--------------------------------------------------------------------------
    */

    public function getFormattedTotalAttribute(): string
    {
        return number_format($this->total, 2);
    }

    public function getFormattedAmountDueAttribute(): string
    {
        return number_format($this->amount_due, 2);
    }

    public function getDisplayNameAttribute()
    {
        return 'INV-'.($this->sequence ?: $this->id ?: '???');
    }
}
