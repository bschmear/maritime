<?php

namespace Domain\Vendor\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Vendor extends Model
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
        // Status and enums
        'status_id' => 'integer',
        'rating' => 'integer',
        'credit_limit' => 'decimal:2',
        'is_verified' => 'boolean',

        // Optional foreign keys
        'assigned_user_id' => 'integer',

        // JSON fields
        'tags' => 'array',
        'contacts' => 'array',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array<string, string>
     */
    protected $dates = [
        'last_contacted_at',
        'next_followup_at',
        'contract_start',
        'contract_end',
        'verified_at',
    ];

    /**
     * Accessor for full contact name.
     */
    protected function contactFullName(): Attribute
    {
        return Attribute::make(
            get: fn () => trim($this->contact_first_name . ' ' . $this->contact_last_name)
        );
    }

    /**
     * Example relation: assigned user.
     */
    public function assignedUser()
    {
        return $this->belongsTo(\Domain\User\Models\User::class, 'assigned_user_id');
    }
}
