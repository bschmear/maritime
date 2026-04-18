<?php

namespace App\Domain\User\Models;

use App\Domain\Integration\Models\Integration;
use App\Domain\Role\Models\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserProfile extends Model
{
    protected $connection = 'tenant';

    protected $table = 'users';

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'current_role');
    }

    public function integrations(): HasMany
    {
        return $this->hasMany(Integration::class, 'user_id');
    }
}
