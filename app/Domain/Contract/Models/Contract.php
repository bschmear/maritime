<?php

namespace App\Domain\Contract\Models;

use App\Domain\Contact\Models\Contact;
use App\Domain\Document\Models\Document;
use App\Domain\Estimate\Models\Estimate;
use App\Domain\Transaction\Models\Transaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contract extends Model
{
    use SoftDeletes;

    protected $table = 'contracts';

    protected $guarded = ['id'];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'billing_latitude' => 'decimal:7',
        'billing_longitude' => 'decimal:7',
        'signature_required' => 'boolean',
        'signed_at' => 'datetime',
        'meta' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function estimate(): BelongsTo
    {
        return $this->belongsTo(Estimate::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function paperSignatureDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'paper_signature_document_id');
    }
}
