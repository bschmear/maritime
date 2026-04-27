<?php

declare(strict_types=1);

namespace App\Domain\InventoryCatalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryBoatType extends Model
{
    protected $connection = 'inventory';

    protected $table = 'boat_type';

    protected $fillable = [
        'display_name',
        'slug',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function boatMakes(): HasMany
    {
        return $this->hasMany(InventoryBoatMake::class, 'boat_type_id');
    }
}
