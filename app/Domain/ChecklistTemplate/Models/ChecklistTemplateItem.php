<?php

namespace App\Domain\ChecklistTemplate\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChecklistTemplateItem extends Model
{
    protected $table = 'checklist_template_items';

    protected $fillable = [
        'checklist_template_id',
        'label',
        'required',
        'position',
    ];

    protected $casts = [
        'required' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function template(): BelongsTo
    {
        return $this->belongsTo(ChecklistTemplate::class, 'checklist_template_id');
    }
}