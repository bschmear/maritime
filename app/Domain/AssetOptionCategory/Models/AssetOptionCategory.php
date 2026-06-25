<?php

declare(strict_types=1);

namespace App\Domain\AssetOptionCategory\Models;

use App\Domain\AssetOption\Models\AssetOption;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AssetOptionCategory extends Model
{
    protected $table = 'asset_option_categories';

    protected $fillable = [
        'name',
        'slug',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    protected $appends = [
        'display_name',
    ];

    /**
     * Idempotent defaults for new tenants (see AssetOptionCategorySeeder).
     *
     * @return list<string>
     */
    public static function defaultDefinitions(): array
    {
        return [
            'Appearance & Comfort',
            'Electrical & Electronics',
            'Engines & Performance',
            'Miscellaneous',
        ];
    }

    public static function ensureDefaultsExist(): void
    {
        foreach (static::defaultDefinitions() as $name) {
            static::firstOrCreateByName($name);
        }
    }

    public function getDisplayNameAttribute(): string
    {
        return (string) ($this->attributes['name'] ?? '');
    }

    public function options(): HasMany
    {
        return $this->hasMany(AssetOption::class, 'category_id');
    }

    public static function firstOrCreateByName(string $name): self
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
                'active' => true,
            ]
        );
    }

    public static function uniqueSlugForName(string $name, ?int $ignoreId = null): string
    {
        $name = trim($name);
        $baseSlug = Str::slug($name) ?: 'category';
        $slug = $baseSlug;
        $suffix = 1;

        while (static::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where('name', '!=', $name)
            ->exists()) {
            $slug = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
