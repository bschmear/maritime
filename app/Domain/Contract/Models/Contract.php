<?php

namespace App\Domain\Contract\Models;

use App\Casts\PaymentTermsCast;
use App\Domain\Customer\Models\Customer;
use App\Domain\Document\Models\Document;
use App\Domain\Estimate\Models\Estimate;
use App\Domain\Transaction\Models\Transaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Contract extends Model
{
    use SoftDeletes;

    protected $table = 'contracts';

    protected $guarded = ['id'];

    protected $appends = ['display_name'];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'billing_latitude' => 'decimal:7',
        'billing_longitude' => 'decimal:7',
        'payment_term' => PaymentTermsCast::class,
        'signature_required' => 'boolean',
        'signed_at' => 'datetime',
        'meta' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($record) {
            if (empty($record->uuid)) {
                $record->uuid = (string) Str::uuid();
            }
            if (empty($record->sequence)) {
                $next = (int) (DB::table('contracts')->max('sequence') ?? 999);
                $record->sequence = $next + 1;
            }
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function estimate(): BelongsTo
    {
        return $this->belongsTo(Estimate::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function getDisplayNameAttribute()
    {
        return 'CTR-'.($this->sequence ?: $this->id ?: '???');
    }

    public function paperSignatureDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'paper_signature_document_id');
    }

    public function getSignatureUrlAttribute(): ?string
    {
        if (! $this->signature_file) {
            return null;
        }

        try {
            return Storage::disk('s3')->temporaryUrl($this->signature_file, now()->addHours(2));
        } catch (\Exception $e) {
            return Storage::disk('s3')->url($this->signature_file);
        }
    }
}
