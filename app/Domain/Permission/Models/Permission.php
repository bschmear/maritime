<?php

declare(strict_types=1);

namespace App\Domain\Permission\Models;

use App\Domain\Role\Models\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'key',
        'domain',
        'action',
        'label',
        'description',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permissions');
    }
}
