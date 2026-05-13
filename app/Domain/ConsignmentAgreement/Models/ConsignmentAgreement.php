<?php

namespace App\Domain\ConsignmentAgreement\Models;

use App\Domain\AssetUnit\Models\AssetUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ConsignmentAgreement extends Model
{
    protected $table = 'consignment_agreements';

    protected $guarded = ['id'];

    protected $appends = ['display_name'];

    protected $casts = [
        'agreement_date' => 'date',
        'boat_title_signed_delivered' => 'boolean',
        'asking_boat' => 'decimal:2',
        'asking_motor' => 'decimal:2',
        'asking_other' => 'decimal:2',
        'asking_sold' => 'decimal:2',
        'minimum_boat' => 'decimal:2',
        'minimum_motor' => 'decimal:2',
        'minimum_other' => 'decimal:2',
        'minimum_sold' => 'decimal:2',
        'signed_at' => 'datetime',
        'signature_method' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $record): void {
            if (empty($record->uuid)) {
                $record->uuid = (string) Str::uuid();
            }
        });
    }

    public function assetUnit(): BelongsTo
    {
        return $this->belongsTo(AssetUnit::class, 'asset_unit_id');
    }

    public function getDisplayNameAttribute(): string
    {
        return 'CON-'.strtoupper(Str::substr((string) $this->uuid, 0, 8));
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

    public function scopeUnsigned($query)
    {
        return $query->whereNull('signed_at');
    }

    public function scopeSigned($query)
    {
        return $query->whereNotNull('signed_at');
    }
}
