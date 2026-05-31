<?php

declare(strict_types=1);

namespace App\Domain\ConsignmentAgreement\Models;

use App\Domain\AssetUnit\Models\AssetUnit;
use App\Domain\Contact\Models\Contact;
use App\Domain\Contact\Models\ContactAddress;
use App\Support\SignatureStorage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ConsignmentAgreement extends Model
{
    protected $fillable = [
        'asset_unit_id',
        'agreement_date',
        'boat_description',
        'motor_description',
        'other_description',
        'boat_title_signed_delivered',
        'owner_contact_id',
        'owner_contact_address_id',
        'notes',
        'policies_snapshot',
        'asking_boat',
        'asking_motor',
        'asking_other',
        'asking_sold',
        'minimum_boat',
        'minimum_motor',
        'minimum_other',
        'minimum_sold',
        'signed_at',
        'signed_name',
        'signed_ip',
        'signed_user_agent',
        'signature_file',
        'signature_hash',
        'customer_signature',
        'signature_method',
    ];

    protected $casts = [
        'agreement_date' => 'date',
        'boat_title_signed_delivered' => 'boolean',
        'policies_snapshot' => 'array',
        'sequence' => 'integer',
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

    protected $appends = ['display_name'];

    protected static function booted(): void
    {
        static::creating(function (ConsignmentAgreement $model): void {
            if ($model->uuid === null || $model->uuid === '') {
                $model->uuid = (string) Str::uuid();
            }
            $next = (int) (DB::table('consignment_agreements')->max('sequence') ?? 999);
            $model->sequence = $next + 1;
        });
    }

    public function getDisplayNameAttribute(): string
    {
        if ($this->sequence !== null) {
            return (string) (int) $this->sequence;
        }

        return $this->getKey() !== null ? (string) (int) $this->getKey() : '';
    }

    /**
     * URL for the drawn signature image. Not appended globally — use {@see Model::append()} when serializing for views that need it.
     */
    public function getSignatureUrlAttribute(): ?string
    {
        return SignatureStorage::url($this->signature_file);
    }

    public function assetUnit()
    {
        return $this->belongsTo(AssetUnit::class, 'asset_unit_id');
    }

    public function ownerContact()
    {
        return $this->belongsTo(Contact::class, 'owner_contact_id');
    }

    public function ownerContactAddress()
    {
        return $this->belongsTo(ContactAddress::class, 'owner_contact_address_id');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSigned(Builder $query): Builder
    {
        return $query->whereNotNull('signed_at');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeUnsigned(Builder $query): Builder
    {
        return $query->whereNull('signed_at');
    }
}
