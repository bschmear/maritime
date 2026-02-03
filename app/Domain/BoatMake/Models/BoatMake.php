<?php

namespace App\Domain\BoatMake\Models;

use Illuminate\Database\Eloquent\Model;

class BoatMake extends Model
{
    protected $table = 'boat_make';

    protected $fillable = [
        'title',
        'slug',
        'is_custom',
        'logo',
        'active',
    ];

    protected $casts = [
        'is_custom' => 'boolean',
        'active' => 'boolean',
    ];

    public function items()
    {
        return $this->hasMany(InventoryItem::class, 'make', 'id');
    }
}