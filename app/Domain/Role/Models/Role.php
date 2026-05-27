<?php

namespace App\Domain\Role\Models;

use App\Domain\Permission\Models\Permission;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $connection = 'tenant';

    protected static function booted(): void
    {
        // Legacy `roles.permissions` JSON column shares a name with the `permissions()` relation.
        // Drop the attribute so relationship / eager loads work until the column is migrated away.
        static::retrieved(function (Role $role): void {
            if (array_key_exists('permissions', $role->attributes)) {
                unset($role->attributes['permissions']);
            }
        });
    }

    protected $fillable = [
        'display_name',
        'slug',
        'description',
    ];

    /**
     * Users that have this role.
     */
    public function users(): HasMany
    {
        return $this->hasMany(\App\Domain\User\Models\User::class, 'current_role');
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->relationLoaded('permissions')) {
            return $this->permissions->contains('key', $permission);
        }

        return $this->permissions()->where('key', $permission)->exists();
    }
}
