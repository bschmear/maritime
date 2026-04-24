<?php

namespace App\Domain\DeliveryChecklistCategory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryChecklistCategory extends Model
{
    protected $fillable = [
        'name',
        'color',
    ];

    /**
     * Idempotent defaults for new tenants (see DeliveryChecklistCategorySeeder).
     *
     * @return list<array{name: string, color: string}>
     */
    public static function defaultDefinitions(): array
    {
        return [
            ['name' => 'Boat has been delivered with', 'color' => 'blue'],
            ['name' => 'Engine has been delivered with', 'color' => 'green'],
            ['name' => 'Pre Delivery Checklist', 'color' => 'blue'],
            ['name' => 'Upon Delivery', 'color' => 'green'],
        ];
    }

    public static function ensureDefaultsExist(): void
    {
        foreach (static::defaultDefinitions() as $row) {
            static::firstOrCreate(
                ['name' => $row['name']],
                ['color' => $row['color']],
            );
        }
    }

    public function items(): HasMany
    {
        return $this->hasMany(DeliveryChecklistTemplateItem::class, 'category_id');
    }

    public function deliveryChecklistItems(): HasMany
    {
        return $this->hasMany(\App\Domain\DeliveryChecklistItem\Models\DeliveryChecklistItem::class, 'category_id');
    }
}