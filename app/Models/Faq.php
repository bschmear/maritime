<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    use HasFactory;

    protected $fillable = [
        'question',
        'answer',
        'featured',
        'sort_order',
    ];

    protected $casts = [
        'featured' => 'boolean',
        'sort_order' => 'integer',
    ];
}
