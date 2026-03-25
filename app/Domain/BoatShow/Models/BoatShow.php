<?php

namespace App\Domain\BoatShow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BoatShow extends Model
{
    use SoftDeletes;

    protected $table = 'boat_shows';

    protected $fillable = [
        'display_name',
        'slug',
        'description',
        'website',
        'logo',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    protected static function booted()
    {
        static::creating(function ($record) {
            if (empty($record->uuid)) {
                $record->uuid = (string) Str::uuid();
            }
            if (empty($record->sequence)) {
                $next = (int) (DB::table('boat_shows')->max('sequence') ?? 999);
                $record->sequence = $next + 1;
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function events(): HasMany
    {
        return $this->hasMany(
            \App\Domain\BoatShowEvent\Models\BoatShowEvent::class,
            'boat_show_id'
        );
    }
}
