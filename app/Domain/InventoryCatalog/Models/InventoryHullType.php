<?php

declare(strict_types=1);

namespace App\Domain\InventoryCatalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryHullType extends Model
{
    protected $connection = 'inventory';

    protected $table = 'hull_type';

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
        return $this->hasMany(InventoryBoatMake::class, 'hull_type_id');
    }
}
