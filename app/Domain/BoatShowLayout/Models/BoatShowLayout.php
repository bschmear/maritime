<?php

namespace App\Domain\BoatShowLayout\Models;

use App\Domain\BoatShowEvent\Models\BoatShowEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BoatShowLayout extends Model
{
    use SoftDeletes;

    protected $table = 'boat_show_layouts';

    protected $fillable = [
        'boat_show_event_id',
        'name',
        'width_ft',
        'height_ft',
        'grid_size',
        'scale',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(BoatShowEvent::class, 'boat_show_event_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BoatShowLayoutItem::class, 'layout_id');
    }
}
