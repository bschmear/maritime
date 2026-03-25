<?php

namespace App\Domain\BoatShow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoatShowLead extends Model
{
    use SoftDeletes;

    protected $table = 'boat_show_leads';

    protected $fillable = [
        'boat_show_event_id',
        'contact_id',
        'source',        // e.g., 'boat show', 'VIP invite'
        'notes',
        'status',
        'assigned_to_id',
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

    public function event(): BelongsTo
    {
        return $this->belongsTo(
            \App\Domain\BoatShowEvent\Models\BoatShowEvent::class,
            'boat_show_event_id'
        );
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(
            \App\Models\Contact::class,
            'contact_id'
        );
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(
            \App\Models\User::class,
            'assigned_to_id'
        );
    }
}