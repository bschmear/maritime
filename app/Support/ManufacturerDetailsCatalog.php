<?php

declare(strict_types=1);

namespace App\Support;

use App\Domain\InventoryCatalog\Models\InventoryBoatMake;
use App\Domain\InventoryCatalog\Models\InventoryBoatType;
use Illuminate\Support\Facades\Log;

/**
 * Curated manufacturer website URLs, descriptions, and boat categories keyed by catalog slug.
 *
 * @see app/Domain/BoatMake/Schema/manufacturer_details.json
 */
final class ManufacturerDetailsCatalog
{
    public static function jsonPath(): string
    {
        return base_path('app/Domain/BoatMake/Schema/manufacturer_details.json');
    }

    /**
     * @return array<string, array{url: string, description: string, boat_type_keys: list<string>}>
     */
    public static function allBySlug(): array
    {
        $path = self::jsonPath();
        if (! is_readable($path)) {
            return [];
        }

        $raw = json_decode((string) file_get_contents($path), true);
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $slug => $row) {
            if (! is_string($slug) || trim($slug) === '') {
                continue;
            }
            if (! is_array($row)) {
                continue;
            }

            $url = isset($row['url']) && is_string($row['url']) ? trim($row['url']) : '';
            $description = isset($row['description']) && is_string($row['description']) ? trim($row['description']) : '';
            $boatTypeKeys = self::normalizeBoatTypeKeys($row['boat_type_keys'] ?? []);

            if ($url === '' && $description === '' && $boatTypeKeys === []) {
                continue;
            }

            $out[trim($slug)] = [
                'url' => $url,
                'description' => $description,
                'boat_type_keys' => $boatTypeKeys,
            ];
        }

        return $out;
    }

    /**
     * @return array{url: string, description: string, boat_type_keys: list<string>}|null
     */
    public static function forSlug(string $slug): ?array
    {
        return self::allBySlug()[trim($slug)] ?? null;
    }

    /**
     * @return list<string>
     */
    public static function boatTypeKeysForSlug(string $slug): array
    {
        return self::forSlug($slug)['boat_type_keys'] ?? [];
    }

    /**
     * @return array{website_url?: string, description?: string}
     */
    public static function inventoryPayload(string $slug, bool $overwriteDescription, ?string $existingDescription = null): array
    {
        $details = self::forSlug($slug);
        if ($details === null) {
            return [];
        }

        $payload = [];
        if ($details['url'] !== '') {
            $payload['website_url'] = $details['url'];
        }
        if ($details['description'] !== '') {
            $shouldSet = $overwriteDescription || $existingDescription === null || trim($existingDescription) === '';
            if ($shouldSet) {
                $payload['description'] = $details['description'];
            }
        }

        return $payload;
    }

    /**
     * Sync many-to-many boat categories for an inventory brand from manufacturer_details.json.
     *
     * @return int Number of categories attached
     */
    public static function syncBoatTypesForMake(InventoryBoatMake $make, bool $replaceExisting = true): int
    {
        $keys = self::boatTypeKeysForSlug($make->slug);
        if ($keys === []) {
            return 0;
        }

        $types = InventoryBoatType::query()
            ->whereIn('slug', $keys)
            ->get(['id', 'slug']);

        $foundSlugs = $types->pluck('slug')->all();
        $missing = array_values(array_diff($keys, $foundSlugs));
        if ($missing !== []) {
            Log::warning('ManufacturerDetailsCatalog: unknown boat_type_keys for brand', [
                'brand_slug' => $make->slug,
                'missing_keys' => $missing,
            ]);
        }

        if ($types->isEmpty()) {
            return 0;
        }

        $orderedIds = [];
        foreach ($keys as $key) {
            $type = $types->firstWhere('slug', $key);
            if ($type !== null) {
                $orderedIds[] = (int) $type->id;
            }
        }
        $orderedIds = array_values(array_unique($orderedIds));

        $syncPayload = [];
        foreach ($orderedIds as $index => $typeId) {
            $syncPayload[$typeId] = ['is_primary' => $index === 0];
        }

        if ($replaceExisting) {
            $make->boatTypes()->sync($syncPayload);
        } else {
            $make->boatTypes()->syncWithoutDetaching($syncPayload);
        }

        $make->update(['boat_type_id' => $orderedIds[0]]);

        return count($orderedIds);
    }

    /**
     * @return list<string>
     */
    private static function normalizeBoatTypeKeys(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $keys = [];
        foreach ($raw as $key) {
            if (! is_string($key)) {
                continue;
            }
            $key = trim($key);
            if ($key === '') {
                continue;
            }
            $keys[] = $key;
        }

        return array_values(array_unique($keys));
    }
}
