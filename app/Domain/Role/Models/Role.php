<?php

namespace Domain\Role\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $fillable = [
        'display_name',
        'slug',
        'description',
        'permissions',
    ];

    protected $casts = [
        'permissions' => 'array',
    ];

    /**
     * Users that have this role.
     */
    public function users(): HasMany
    {
        return $this->hasMany(\Domain\User\Models\User::class, 'current_role');
    }
}
