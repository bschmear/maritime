<?php

namespace App\Domain\Customer\Models;

use App\Domain\Task\Models\Task;
use App\Domain\AssetUnit\Models\AssetUnit;
use App\Domain\PortalAccess\Models\PortalAccess;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\Concerns\HasDocuments;

class Customer extends Authenticatable
{
    use HasDocuments, Notifiable;

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'status_id' => 'integer',
        'source_id' => 'integer',
        'priority_id' => 'integer',
        'purchase_timeline' => 'integer',
        'preferred_contact_time' => 'integer',
        'preferred_contact_method' => 'integer',
        'email_verified_at' => 'datetime',
    ];

    protected $dates = [
        'last_contacted_at',
        'next_followup_at',
    ];

    public function hasPortalAccount(): bool
    {
        return !is_null($this->password);
    }

    public function tasks()
    {
        return $this->morphMany(Task::class, 'relatable');
    }

    public function leads()
    {
        return $this->hasMany(\App\Domain\Lead\Models\Lead::class, 'converted_customer_id');
    }

    public function asset_units()
    {
        return $this->hasMany(AssetUnit::class, 'customer_id');
    }

    public function assetUnits()
    {
        return $this->hasMany(AssetUnit::class, 'customer_id');
    }

    public function opportunities()
    {
        return $this->hasMany(\App\Domain\Opportunity\Models\Opportunity::class);
    }

    public function estimates()
    {
        return $this->hasMany(\App\Domain\Estimate\Models\Estimate::class);
    }

    public function assigned_user()
    {
        return $this->belongsTo(\App\Domain\User\Models\User::class, 'assigned_user_id')->select('id', 'display_name');
    }


    public function created_by_user()
    {
        return $this->belongsTo(User::class, 'created_by_user_id')->select('id', 'display_name');
    }

    public function last_updated_by_user()
    {
        return $this->belongsTo(User::class, 'last_updated_by_user_id')->select('id', 'display_name');
    }

    public function documents()
    {
        return $this->morphToMany(\App\Domain\Document\Models\Document::class, 'documentable')
            ->withTimestamps();
    }

    public function scores()
    {
        return $this->morphMany(\App\Domain\Score\Models\Score::class, 'scorable');
    }

    public function currentScores()
    {
        return $this->morphMany(\App\DomainScore\Models\Score::class, 'scorable')->where('is_current', true);
    }

    public function latestScore()
    {
        return $this->belongsTo(\App\DomainScore\Models\Score::class, 'latest_score_id');
    }

    public function portalAccesses()
    {
        return $this->hasMany(PortalAccess::class);
    }
}
