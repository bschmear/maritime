<?php

namespace App\Domain\AddOn\Models;

use App\Enums\Transaction\AddOnType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class AddOn extends Model
{
    use SoftDeletes;

    protected $table = 'addons';

    protected $guarded = ['id'];

    protected $casts = [
        'default_price' => 'decimal:2',
        'type' => AddOnType::class,
    ];

    protected $appends = ['display_name'];

    protected static function booted()
    {
        static::creating(function ($record) {
            if (empty($record->uuid)) {
                $record->uuid = (string) Str::uuid();
            }
        });
    }

    public function getDisplayNameAttribute()
    {
        return $this->name;
    }
}
