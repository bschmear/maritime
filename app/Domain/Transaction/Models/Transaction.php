<?php

namespace App\Domain\Transaction\Models;

use App\Domain\Contract\Models\Contract;
use App\Domain\Customer\Models\Customer;
use App\Domain\Estimate\Models\Estimate;
use App\Domain\Opportunity\Models\Opportunity;
use App\Domain\User\Models\User;
use App\Models\Concerns\HasDocuments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Transaction extends Model
{
    use HasDocuments, SoftDeletes;

    protected $table = 'transactions';

    protected $guarded = ['id'];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'total' => 'decimal:2',
        'tax_rate' => 'decimal:3',
        'discount_total' => 'decimal:2',
        'fees_total' => 'decimal:2',
        'billing_latitude' => 'decimal:7',
        'billing_longitude' => 'decimal:7',
        'closed_at' => 'datetime',
        'won_at' => 'datetime',
        'lost_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'needs_contract' => 'boolean',
        'needs_delivery' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Transaction $transaction) {
            if (empty($transaction->uuid)) {
                $transaction->uuid = (string) Str::uuid();
            }
            if (empty($transaction->sequence)) {
                $next = (int) (DB::table('transactions')->max('sequence') ?? 999);
                $transaction->sequence = $next + 1;
            }
        });

        static::deleting(function (Transaction $transaction) {
            // Soft (and hard) delete: FK nullOnDelete only runs on hard delete; clear estimate links always.
            Estimate::query()
                ->where('transaction_id', $transaction->getKey())
                ->update(['transaction_id' => null]);
        });
    }

    public function estimate(): BelongsTo
    {
        return $this->belongsTo(Estimate::class);
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(TransactionItem::class)->orderBy('position')->orderBy('id');
    }

    public function contract(): HasOne
    {
        return $this->hasOne(Contract::class);
    }
}
