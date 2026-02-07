<?php

namespace App\Domain\Subsidiary\Models;

use Illuminate\Database\Eloquent\Model;
use App\Enums\Timezone;

class Subsidiary extends Model
{
    /**
     * The attributes that aren't mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'inactive'                 => 'boolean',
        'latitude'                 => 'decimal:7',
        'longitude'                => 'decimal:7',
        'default_labor_rate'       => 'decimal:2',
        'next_work_order_number'   => 'integer',
        'settings'                 => 'array',
    ];

    /**
     * Accessor for full address.
     */
    public function getFullAddressAttribute(): string
    {
        $parts = [
            $this->address_line_1,
            $this->address_line_2,
            $this->city,
            $this->state,
            $this->postal_code,
            $this->country,
        ];

        return implode(', ', array_filter($parts));
    }

    /**
     * Accessor for timezone label.
     */
    public function getTimezoneLabelAttribute(): ?string
    {
        if ($this->timezone && Timezone::tryFrom($this->timezone)) {
            return Timezone::from($this->timezone)->label();
        }

        return null;
    }
}
