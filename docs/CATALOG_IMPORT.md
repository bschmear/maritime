# Inventory catalog import (tenant assets)

When a tenant brand has a matching `brand_key` to the inventory `boat_make.slug`, catalog models can be imported into tenant `assets` via [`CatalogImportService`](app/Domain/InventoryCatalog/Services/CatalogImportService.php) (used from the boat make UI and [`ImportDiscoveredBoatModels`](app/Domain/BoatMake/Actions/ImportDiscoveredBoatModels.php)).

## Hull / boat type columns

Import maps inventory `catalog_data` string keys to tenant asset integer columns:

| `catalog_data` key       | Tenant `assets` column |
|--------------------------|-------------------------|
| `boat_type_key`          | `boat_type`             |
| `hull_type_key`          | `hull_type`             |
| `hull_material_key`      | `hull_material`         |

Values must match slugs on `App\Enums\Inventory\BoatType`, `HullType`, and `HullMaterial`. Invalid or missing keys are stored as `NULL` on the asset. Keys are read from **`catalog_data` merged with `attributes`** on the inventory row (same order as stored JSON: catalog first, then attributes overrides). Those three keys are **not** duplicated inside tenant `attributes` JSON after import (they live only on the columns).

## Dimensions, capacity, and power

Tenant columns `length`, `beam`, `width`, `persons`, `maximum_power`, and `fuel_tank` prefer inventory table columns (`length_mm`, `width_mm`, `capacity_persons`, `max_hp`, `fuel_capacity_l`). When those are null—common for series rows that only define a `length_range_mm` in JSON but still attach per-model **`specifications`** in `meta.json`—the importer falls back to the nested `specifications` object inside the same merged catalog/attributes layer (e.g. `length_mm`, `width_mm`, `fuel_capacity_l`).

## Tenant spec builder values

When default tenant definitions still exist (`boat_weight`, `max_people`, `max_hp`, `engine_shaft`), import also writes matching `asset_spec_values` rows:

| Inventory source | Tenant definition | Notes |
|------------------|-------------------|--------|
| `weight_kg` | `boat_weight` | Stored in **lb** (kg × 2.20462) |
| `capacity_persons` | `max_people` | Integer |
| `max_hp` | `max_hp` | Integer |
| `engine_shaft` | `engine_shaft` | Select value (e.g. `L`, `XL`) |

Variant rows receive `length` / `width` (mm) from inventory variants when present, otherwise the parent asset values. Spec values sync on the variant when the catalog model has variants, otherwise on the asset.

To backfill spec values on assets that were imported before this mapping existed, run in tinker (per make):

```php
app(\App\Domain\InventoryCatalog\Services\CatalogImportService::class)
    ->resyncImportedSpecs(\App\Domain\BoatMake\Models\BoatMake::find($makeId));
```

## Asset type (`type`)

Tenant `assets.type` must be a valid `App\Enums\Inventory\AssetType` value (1–4). If the inventory row has a missing or invalid `type`, import defaults to **1** (boat).

## Backfilling already-imported rows

`CatalogImportService::import()` only creates or updates assets that are **not** already linked (`catalog_asset_key` not yet present on a tenant asset for that make). Fixing the mapper does **not** automatically update assets that were imported earlier.

To backfill `boat_type`, `hull_type`, `hull_material`, dimensions, and `type` for existing rows you can:

1. **Re-run import after clearing catalog keys** (only if acceptable for your data): delete or null `catalog_asset_key` on the tenant assets you want to re-import, then import again from the UI/command; or  
2. **Run a one-off SQL/script** that joins tenant `assets` to inventory `assets` on slug + make and copies resolved enum IDs; or  
3. **Add a product feature** (e.g. “Refresh from catalog”) that calls `updateOrCreate` with the new payload for selected keys without the `already_imported` skip—out of scope unless you implement it.

## Related commands

- `inventory:seed-asset-catalog` seeds the **inventory** database from `app/AssetInformation/{slug}/meta.json` (not tenant assets).



1. Dry run (recommended)
Validates JSON and reports counts without writing:

php artisan inventory:seed-asset-catalog --brand=ranieri --dry-run
For every brand under app/AssetInformation/:

php artisan inventory:seed-asset-catalog --dry-run
2. Apply to the inventory database
php artisan inventory:seed-asset-catalog --brand=ranieri
Or all brands:

php artisan inventory:seed-asset-catalog
This upserts boat_make, assets, and asset_variants from app/AssetInformation/{slug}/meta.json. Ranieri is already in manufacturers.json as "ranieri": "Ranieri" and has a BoatMake enum entry, so the ranieri folder should be picked up.

Options
Flag	Purpose
--brand=ranieri
Only that manufacturer folder
--dry-run
Validate only, no DB writes
--keep-orphan-variants
Don’t delete inventory variants removed from meta.json
Prerequisites
Inventory migrations must be applied on the inventory connection (the command checks for columns like catalog_data, features, length_mm on assets). If that fails, run your inventory migrations first.

Optional: refresh make rows from the catalog list:

```bash
php artisan inventory:seed-makes
```

### Troubleshooting seed errors

**`Lookup boat_type.slug not found in inventory DB: raft`**

The slug must exist in inventory `boat_type` (seeded from `app/Domain/BoatMake/Schema/boat_types.json`). If the enum has a case but the JSON file was missing it, run inventory migrations so lookup rows are upserted, then re-seed the brand:

```bash
php artisan migrate --database=inventory --path=database/migrations/inventory
php artisan inventory:seed-asset-catalog --brand=achilles
```