<?php

declare(strict_types=1);

namespace App\Domain\Customer\Models;

use App\Domain\AssetOption\Models\AssetOption;
use App\Domain\AssetOption\Models\AssetOptionValue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerAssetSpecSheetOptionSelection extends Model
{
    protected $table = 'customer_asset_spec_sheet_option_selections';

    protected $fillable = [
        'customer_asset_spec_sheet_share_id',
        'option_id',
        'option_value_id',
        'option_name',
        'value_label',
        'cost',
        'price',
    ];

    protected $casts = [
        'cost' => 'decimal:2',
        'price' => 'decimal:2',
    ];

    public function share(): BelongsTo
    {
        return $this->belongsTo(CustomerAssetSpecSheetShare::class, 'customer_asset_spec_sheet_share_id');
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(AssetOption::class, 'option_id');
    }

    public function value(): BelongsTo
    {
        return $this->belongsTo(AssetOptionValue::class, 'option_value_id');
    }
}
