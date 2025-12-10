<?php

namespace App\Domain\Location\Models;

use Illuminate\Database\Eloquent\Model;
use App\Domain\User\Models\User;

class Location extends Model
{
    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array<string, string>
     */
    protected $dates = [
        'created_at',
        'updated_at',
    ];


    public function created_by()
    {
        return $this->belongsTo(User::class, 'created_by_id')->select('id', 'display_name');
    }
    public function updated_by()
    {
        return $this->belongsTo(User::class, 'updated_by_id')->select('id', 'display_name');
    }
    public function manager_user()
    {
        return $this->belongsTo(User::class, 'manager_user_id')->select('id', 'display_name');
    }
}
