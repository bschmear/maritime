<?php

namespace App\Domain\Document\Models;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'key_points' => 'array',
        'ai_processed_at' => 'datetime',
    ];

    public function created_by()
    {
        return $this->belongsTo(\App\Domain\User\Models\User::class, 'created_by_id');
    }

    public function updated_by()
    {
        return $this->belongsTo(\App\Domain\User\Models\User::class, 'updated_by_id');
    }

    public function assigned_user()
    {
        return $this->belongsTo(\App\Domain\User\Models\User::class, 'assigned_id');
    }
}
