<?php

namespace App\Domain\BoatShow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BoatShow extends Model
{
    use SoftDeletes;

    protected $table = 'boat_shows';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'website',
        'logo',
        'banner',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function events(): HasMany
    {
        return $this->hasMany(
            \App\Domain\BoatShow\Models\BoatShowEvent::class
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->name;
    }
}