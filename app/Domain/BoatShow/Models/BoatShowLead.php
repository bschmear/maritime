<?php

declare(strict_types=1);

namespace App\Domain\BoatShow\Models;

use App\Domain\BoatShowEvent\Models\BoatShowEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class BoatShowLead extends Model
{
    protected $table = 'boat_show_leads';

    protected $fillable = [
        'boat_show_id',
        'boat_show_event_id',
        'leadable_type',
        'leadable_id',
        'captured_by_id',
        'captured_at',
        'meta',
    ];

    protected $casts = [
        'captured_at' => 'datetime',
        'meta' => 'array',
    ];

    public function boatShow(): BelongsTo
    {
        return $this->belongsTo(BoatShow::class, 'boat_show_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(BoatShowEvent::class, 'boat_show_event_id');
    }

    public function leadable(): MorphTo
    {
        return $this->morphTo();
    }
}
