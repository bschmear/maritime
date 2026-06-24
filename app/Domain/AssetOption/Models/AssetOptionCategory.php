<?php

declare(strict_types=1);

namespace App\Domain\AssetOption\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AssetOptionCategory extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'sort_order',
        'active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'active' => 'boolean',
    ];

    public function options(): HasMany
    {
        return $this->hasMany(AssetOption::class, 'category_id');
    }

    public static function firstOrCreateByName(string $name, int $sortOrder = 0): self
    {
        $name = trim($name);
        $baseSlug = Str::slug($name) ?: 'category';
        $slug = $baseSlug;
        $suffix = 1;

        while (static::query()->where('slug', $slug)->where('name', '!=', $name)->exists()) {
            $slug = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        return static::query()->firstOrCreate(
            ['name' => $name],
            [
                'slug' => $slug,
                'sort_order' => $sortOrder,
                'active' => true,
            ]
        );
    }
}
