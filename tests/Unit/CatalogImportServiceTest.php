<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\InventoryCatalog\Models\InventoryCatalogAsset;
use App\Domain\InventoryCatalog\Services\CatalogImportService;
use App\Enums\Inventory\BoatType;
use App\Enums\Inventory\HullMaterial;
use App\Enums\Inventory\HullType;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class CatalogImportServiceTest extends TestCase
{
    public function test_map_inventory_asset_sets_enum_columns_from_catalog_data(): void
    {
        $src = new InventoryCatalogAsset;
        $src->forceFill([
            'type' => 1,
            'display_name' => 'Test Model',
            'slug' => 'brand--model1',
            'inactive' => false,
            'make_id' => 1,
            'model' => 'Test Model',
            'year' => null,
            'length_mm' => 5000,
            'width_mm' => 2000,
            'height_mm' => null,
            'weight_kg' => null,
            'capacity_persons' => 6,
            'max_hp' => 150,
            'fuel_capacity_l' => 200,
            'engine_shaft' => null,
            'water_tank' => null,
            'category' => null,
            'engine_details' => null,
            'attributes' => null,
            'catalog_data' => [
                'boat_type_key' => 'power-tender',
                'hull_type_key' => 'rib',
                'hull_material_key' => 'fiberglass',
            ],
            'features' => null,
            'description' => null,
            'default_cost' => null,
            'default_price' => null,
            'has_variants' => false,
        ]);

        $svc = new CatalogImportService;
        $m = new ReflectionMethod(CatalogImportService::class, 'mapInventoryAssetToTenantPayload');
        $m->setAccessible(true);
        /** @var array<string, mixed> $payload */
        $payload = $m->invoke($svc, $src, 99);

        $this->assertSame(BoatType::from('power-tender')->id(), $payload['boat_type']);
        $this->assertSame(HullType::from('rib')->id(), $payload['hull_type']);
        $this->assertSame(HullMaterial::from('fiberglass')->id(), $payload['hull_material']);

        $attrs = $payload['attributes'];
        $this->assertIsArray($attrs);
        $this->assertArrayNotHasKey('boat_type_key', $attrs);
        $this->assertArrayNotHasKey('hull_type_key', $attrs);
        $this->assertArrayNotHasKey('hull_material_key', $attrs);
    }

    public function test_map_inventory_asset_sets_null_enums_when_keys_missing(): void
    {
        $src = new InventoryCatalogAsset;
        $src->forceFill([
            'type' => 1,
            'display_name' => 'X',
            'slug' => 'x',
            'inactive' => false,
            'make_id' => 1,
            'model' => 'X',
            'catalog_data' => [],
            'has_variants' => false,
        ]);

        $svc = new CatalogImportService;
        $m = new ReflectionMethod(CatalogImportService::class, 'mapInventoryAssetToTenantPayload');
        $m->setAccessible(true);
        /** @var array<string, mixed> $payload */
        $payload = $m->invoke($svc, $src, 1);

        $this->assertNull($payload['boat_type']);
        $this->assertNull($payload['hull_type']);
        $this->assertNull($payload['hull_material']);
    }

    public function test_map_inventory_asset_sets_null_for_invalid_boat_type_slug(): void
    {
        $src = new InventoryCatalogAsset;
        $src->forceFill([
            'type' => 1,
            'display_name' => 'X',
            'slug' => 'x',
            'inactive' => false,
            'make_id' => 1,
            'model' => 'X',
            'catalog_data' => [
                'boat_type_key' => 'not-a-real-boat-type-slug',
                'hull_type_key' => 'rib',
                'hull_material_key' => 'fiberglass',
            ],
            'has_variants' => false,
        ]);

        $svc = new CatalogImportService;
        $m = new ReflectionMethod(CatalogImportService::class, 'mapInventoryAssetToTenantPayload');
        $m->setAccessible(true);
        /** @var array<string, mixed> $payload */
        $payload = $m->invoke($svc, $src, 1);

        $this->assertNull($payload['boat_type']);
        $this->assertSame(HullType::from('rib')->id(), $payload['hull_type']);
        $this->assertSame(HullMaterial::from('fiberglass')->id(), $payload['hull_material']);
    }

    public function test_map_inventory_asset_resolves_dimensions_from_nested_specifications_when_columns_null(): void
    {
        $src = new InventoryCatalogAsset;
        $src->forceFill([
            'type' => 1,
            'display_name' => 'Walker Bay 22',
            'slug' => 'walker-bay--walker-bay-22',
            'inactive' => false,
            'make_id' => 1,
            'model' => 'Walker Bay 22',
            'length_mm' => null,
            'width_mm' => null,
            'capacity_persons' => null,
            'max_hp' => null,
            'fuel_capacity_l' => null,
            'catalog_data' => [
                'boat_type_key' => 'power-rib',
                'hull_type_key' => 'deep-vee',
                'hull_material_key' => 'fiberglass',
                'specifications' => [
                    'length_mm' => 6600,
                    'width_mm' => 2870,
                    'capacity_persons' => 12,
                    'max_hp' => 225,
                    'fuel_capacity_l' => 227,
                ],
            ],
            'has_variants' => false,
        ]);

        $svc = new CatalogImportService;
        $m = new ReflectionMethod(CatalogImportService::class, 'mapInventoryAssetToTenantPayload');
        $m->setAccessible(true);
        /** @var array<string, mixed> $payload */
        $payload = $m->invoke($svc, $src, 1);

        $this->assertSame(6600, $payload['length']);
        $this->assertSame(2870, $payload['beam']);
        $this->assertSame(2870, $payload['width']);
        $this->assertSame(12, $payload['persons']);
        $this->assertSame(225, $payload['maximum_power']);
        $this->assertSame('227', $payload['fuel_tank']);
    }

    public function test_map_inventory_asset_resolves_enum_keys_from_attributes_when_catalog_data_empty(): void
    {
        $src = new InventoryCatalogAsset;
        $src->forceFill([
            'type' => 1,
            'display_name' => 'X',
            'slug' => 'x',
            'inactive' => false,
            'make_id' => 1,
            'model' => 'X',
            'catalog_data' => null,
            'attributes' => [
                'boat_type_key' => 'power-rib',
                'hull_type_key' => 'rib',
                'hull_material_key' => 'aluminum',
            ],
            'has_variants' => false,
        ]);

        $svc = new CatalogImportService;
        $m = new ReflectionMethod(CatalogImportService::class, 'mapInventoryAssetToTenantPayload');
        $m->setAccessible(true);
        /** @var array<string, mixed> $payload */
        $payload = $m->invoke($svc, $src, 1);

        $this->assertSame(BoatType::from('power-rib')->id(), $payload['boat_type']);
        $this->assertSame(HullType::from('rib')->id(), $payload['hull_type']);
        $this->assertSame(HullMaterial::from('aluminum')->id(), $payload['hull_material']);
    }

    public function test_map_inventory_asset_defaults_type_to_one_when_invalid(): void
    {
        $src = new InventoryCatalogAsset;
        $src->forceFill([
            'type' => 99,
            'display_name' => 'X',
            'slug' => 'x',
            'inactive' => false,
            'make_id' => 1,
            'model' => 'X',
            'catalog_data' => [],
            'has_variants' => false,
        ]);

        $svc = new CatalogImportService;
        $m = new ReflectionMethod(CatalogImportService::class, 'mapInventoryAssetToTenantPayload');
        $m->setAccessible(true);
        /** @var array<string, mixed> $payload */
        $payload = $m->invoke($svc, $src, 1);

        $this->assertSame(1, $payload['type']);
    }
}
