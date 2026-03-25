<?php

namespace App\Domain\BoatShowLayout\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BoatShowLayout extends Model
{
    protected $table = 'boat_show_layouts';

    protected $fillable = [
        'boat_show_id',
        'space_width',
        'space_height',
        'meta', // store JSON for additional layout settings
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function boatShow(): BelongsTo
    {
        return $this->belongsTo(
            \App\Domain\BoatShow\Models\BoatShow::class,
            'boat_show_id'
        );
    }

    public function boats(): HasMany
    {
        return $this->hasMany(
            \App\Domain\BoatShowLayout\Models\BoatShowLayoutBoat::class,
            'layout_id'
        );
    }
}