<?php

namespace App\Domain\BoatShowEvent\Models;

use App\Domain\BoatShow\Models\BoatShow;
use App\Domain\BoatShow\Models\BoatShowLead;
use App\Domain\BoatShowLayout\Models\BoatShowLayout;
use App\Domain\Checklist\Models\Checklist;
use App\Domain\Task\Models\Task;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BoatShowEvent extends Model
{
    use SoftDeletes;

    protected $table = 'boat_show_events';

    protected $fillable = [
        'boat_show_id',
        'display_name',
        'year',
        'starts_at',
        'ends_at',
        'venue',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'country',
        'postal_code',
        'latitude',
        'longitude',
        'booth',
        'active',
        'meta',
    ];

    protected $casts = [
        'starts_at' => 'date',
        'ends_at' => 'date',
        'active' => 'boolean',
        'meta' => 'array',
    ];

    protected static function booted()
    {
        static::creating(function ($record) {
            // if (empty($record->uuid)) {
            //     $record->uuid = (string) Str::uuid();
            // }
            // if (empty($record->sequence)) {
            //     $next = (int) (DB::table('boat_show_events')->max('sequence') ?? 999);
            //     $record->sequence = $next + 1;
            // }
        });
    }

    public function show(): BelongsTo
    {
        return $this->belongsTo(BoatShow::class, 'boat_show_id');
    }

    public function layouts(): HasMany
    {
        return $this->hasMany(BoatShowLayout::class, 'boat_show_event_id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(BoatShowLead::class, 'boat_show_event_id');
    }

    public function tasks()
    {
        return $this->morphMany(Task::class, 'relatable');
    }

    public function checklist(): MorphOne
    {
        return $this->morphOne(Checklist::class, 'checklistable');
    }
}
