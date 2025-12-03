<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Tenant;

class Account extends Model
{
    use HasFactory;

    /**
     * The database connection name for the model.
     * Accounts are stored in the central/public schema, not tenant schemas.
     *
     * @var string|null
     */
    protected $connection = 'pgsql';

    protected $fillable = [
        'name',
        'owner_id',
        'tenant_id',
    ];

    /**
     * The owner of the account.
     */
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * The tenant associated with this account.
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * Domains for this account's tenant.
     */
    public function domains()
    {
        return $this->hasMany(\Stancl\Tenancy\Database\Models\Domain::class, 'tenant_id', 'tenant_id');
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
