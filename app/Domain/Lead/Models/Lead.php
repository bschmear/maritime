<?php

namespace App\Domain\Lead\Models;

use App\Domain\Task\Models\Task;
use Illuminate\Database\Eloquent\Model;

class Lead extends Model
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
        'status_id' => 'integer',
        'source_id' => 'integer',
        'priority_id' => 'integer',
        'purchase_timeline' => 'integer',
        'preferred_contact_time' => 'integer',
        'preferred_contact_method' => 'integer',
        'converted_customer_id' => 'integer',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array<string, string>
     */
    protected $dates = [
        'last_contacted_at',
        'next_followup_at',
        'converted_at',
    ];

    public function tasks()
    {
        return $this->morphMany(Task::class, 'relatable');
    }
}
