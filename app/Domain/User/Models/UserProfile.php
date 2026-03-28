<?php

namespace App\Domain\User\Models;

use App\Domain\Role\Models\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends Model
{
    protected $connection = 'tenant';

    protected $table = 'users';

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'current_role');
    }
}
