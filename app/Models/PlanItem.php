<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlanItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'plan_id',
        'name',
        'stripe_price_id',
        'price',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
        'price' => 'decimal:2',
    ];

    /**
     * The plan this item belongs to
     */
    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }
}
