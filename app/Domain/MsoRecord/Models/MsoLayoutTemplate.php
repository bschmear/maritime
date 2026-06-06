<?php

declare(strict_types=1);

namespace App\Domain\MsoRecord\Models;

use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MsoLayoutTemplate extends Model
{
    protected $table = 'mso_layout_templates';

    protected $fillable = [
        'name',
        'layout',
        'created_by_id',
    ];

    protected $casts = [
        'layout' => 'array',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
