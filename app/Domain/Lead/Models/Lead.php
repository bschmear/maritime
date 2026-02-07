<?php

namespace App\Domain\Lead\Models;

use Illuminate\Database\Eloquent\Model;
use App\Domain\Task\Models\Task;
use App\Domain\User\Models\User;
use App\Domain\Customer\Models\Customer;
use App\Models\Concerns\HasDocuments;

class Lead extends Model
{
    use HasDocuments;

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
        'created_at',
        'updated_at',
    ];

    public function tasks()
    {
        return $this->morphMany(Task::class, 'relatable');
    }

    public function assigned_user()
    {
        return $this->belongsTo(User::class, 'assigned_user_id')
            ->select('id', 'display_name');
    }

    public function created_by_user()
    {
        return $this->belongsTo(User::class, 'created_by_user_id')
            ->select('id', 'display_name');
    }

    public function last_updated_by_user()
    {
        return $this->belongsTo(User::class, 'last_updated_by_user_id')
            ->select('id', 'display_name');
    }

    public function converted_customer()
    {
        return $this->belongsTo(Customer::class, 'converted_customer_id')
            ->select('id', 'display_name');
    }
}
