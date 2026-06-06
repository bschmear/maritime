<?php

declare(strict_types=1);

namespace App\Domain\MsoRecord\Models;

use App\Domain\AssetUnit\Models\AssetUnit;
use App\Domain\Document\Models\Document;
use App\Domain\Transaction\Models\Transaction;
use App\Domain\Transaction\Models\TransactionLineItem;
use App\Domain\User\Models\User;
use App\Enums\MsoRecord\Status;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MsoRecord extends Model
{
    protected $table = 'mso_records';

    protected $fillable = [
        'asset_unit_id',
        'transaction_id',
        'transaction_line_item_id',
        'source_document_id',
        'output_document_id',
        'layout_template_id',
        'details',
        'status',
        'created_by_id',
        'submitted_at',
    ];

    protected $casts = [
        'details' => 'array',
        'status' => Status::class,
        'submitted_at' => 'datetime',
    ];

    protected $appends = ['display_name'];

    public function getDisplayNameAttribute(): string
    {
        return 'MSO #'.$this->id;
    }

    public function assetUnit(): BelongsTo
    {
        return $this->belongsTo(AssetUnit::class, 'asset_unit_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    public function transactionLineItem(): BelongsTo
    {
        return $this->belongsTo(TransactionLineItem::class, 'transaction_line_item_id');
    }

    public function sourceDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'source_document_id');
    }

    public function outputDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'output_document_id');
    }

    public function layoutTemplate(): BelongsTo
    {
        return $this->belongsTo(MsoLayoutTemplate::class, 'layout_template_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
