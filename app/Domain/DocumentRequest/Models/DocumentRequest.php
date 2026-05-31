<?php

declare(strict_types=1);

namespace App\Domain\DocumentRequest\Models;

use App\Domain\Contact\Models\Contact;
use App\Domain\Customer\Models\Customer;
use App\Domain\Document\Models\Document;
use App\Domain\DocumentRequest\Enums\DocumentRequestStatus;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class DocumentRequest extends Model
{
    protected $fillable = [
        'contact_id',
        'customer_profile_id',
        'source_type',
        'source_id',
        'requested_by_user_id',
        'title',
        'description',
        'status',
        'fulfilled_document_id',
        'sent_at',
        'fulfilled_at',
    ];

    protected $casts = [
        'contact_id' => 'integer',
        'customer_profile_id' => 'integer',
        'source_id' => 'integer',
        'requested_by_user_id' => 'integer',
        'fulfilled_document_id' => 'integer',
        'sent_at' => 'datetime',
        'fulfilled_at' => 'datetime',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function customerProfile(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_profile_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function fulfilledDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'fulfilled_document_id');
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function isPending(): bool
    {
        return $this->status === DocumentRequestStatus::Pending;
    }
}
