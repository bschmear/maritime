<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HelpArticle extends Model
{
    protected $connection = 'pgsql';

    protected $fillable = [
        'category_id',
        'title',
        'slug',
        'body',
        'excerpt',
        'video_url',
        'article_type',
        'sort_order',
        'featured',
        'active',
        'published_at',
    ];

    protected $casts = [
        'featured' => 'boolean',
        'active' => 'boolean',
        'published_at' => 'datetime',
        'sort_order' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(HelpCategory::class, 'category_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('published_at')
                ->orWhere('published_at', '<=', now());
        });
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('featured', true);
    }
}
