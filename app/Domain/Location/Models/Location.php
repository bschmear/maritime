<?php

namespace App\Domain\Location\Models;

use App\Domain\AssetUnit\Models\AssetUnit;
use App\Domain\Fleet\Models\Fleet;
use App\Domain\Subsidiary\Models\Subsidiary;
use App\Domain\Transaction\Models\Transaction;
use App\Domain\User\Models\User;
use App\Models\Concerns\HasDocuments;
use App\Models\Concerns\HasSystemLogs;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    use HasDocuments, HasSystemLogs;

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

    public function managerUser()
    {
        return $this->belongsTo(User::class, 'manager_user_id')->select('id', 'display_name', 'email');
    }

    public function deliveryApprover()
    {
        return $this->belongsTo(User::class, 'delivery_approver_user_id')->select('id', 'display_name', 'email');
    }

    public function subsidiaries()
    {
        return $this->belongsToMany(
            Subsidiary::class,
            'location_subsidiary'
        )->withTimestamps();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'location_user'
        )->withTimestamps();
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'location_id');
    }

    public function fleets(): HasMany
    {
        return $this->hasMany(Fleet::class, 'location_id');
    }

    public function layouts(): HasMany
    {
        return $this->hasMany(LocationLayout::class);
    }

    public function assetUnits(): HasMany
    {
        return $this->hasMany(AssetUnit::class, 'location_id');
    }
}
