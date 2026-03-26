<?php

namespace App\Domain\ChecklistTemplate\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChecklistTemplate extends Model
{
    protected $table = 'checklist_templates';

    protected $fillable = [
        'name',
        'context',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function items(): HasMany
    {
        return $this->hasMany(ChecklistTemplateItem::class)
            ->orderBy('position');
    }
}