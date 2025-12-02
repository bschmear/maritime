<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Account extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'owner_id',
    ];

    /**
     * The owner of the account.
     */
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Users belonging to this account.
     */
    public function users()
    {
        return $this->belongsToMany(User::class)
                    ->withPivot('role')
                    ->withTimestamps();
    }

    /**
     * Get users with a specific role.
     */
    public function admins()
    {
        return $this->users()->wherePivot('role', 'admin');
    }

    public function members()
    {
        return $this->users()->wherePivot('role', 'member');
    }
}
