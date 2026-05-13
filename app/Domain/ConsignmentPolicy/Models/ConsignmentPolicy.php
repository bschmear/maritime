<?php

namespace App\Domain\ConsignmentPolicy\Models;

use Illuminate\Database\Eloquent\Model;

class ConsignmentPolicy extends Model
{
    protected $table = 'consignment_policies';

    protected $guarded = ['id'];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
