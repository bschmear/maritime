<?php

declare(strict_types=1);

namespace App\Domain\Customer\Models;

use App\Domain\Asset\Models\Asset;
use App\Domain\AssetVariant\Models\AssetVariant;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CustomerAssetSpecSheetShare extends Model
{
    protected $table = 'customer_asset_spec_sheet_shares';

    protected $fillable = [
        'uuid',
        'customer_profile_id',
        'asset_id',
        'asset_variant_id',
        'sent_at',
        'sent_by_user_id',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (CustomerAssetSpecSheetShare $share): void {
            if ($share->uuid === null || $share->uuid === '') {
                $share->uuid = (string) Str::uuid();
            }
        });
    }

    public function customerProfile(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_profile_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    public function assetVariant(): BelongsTo
    {
        return $this->belongsTo(AssetVariant::class, 'asset_variant_id');
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }
}
