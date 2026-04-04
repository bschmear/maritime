# Asset Variants + Specification System (Scalable Architecture Guide)

## Overview

This document defines the architecture for handling:

- Assets (top-level products)
- Variants (configurations of assets)
- Units (physical items)
- Specifications (dynamic/custom fields)
- High-performance filtering/search

This system is designed to scale to large datasets while maintaining flexibility.

---

# 🧠 Core Mental Model


specification_values = editable truth (flexible, normalized)
resolved_variant_specs = computed truth (fast, queryable)
asset_variants = primary filtering entry point


---

# 🧱 Data Model

## 1. Assets

Top-level product definition.

```sql
assets
- id
- name
- category_id
- created_at
- updated_at
2. Asset Variants

Represents a specific configuration of an asset.

asset_variants
- id
- asset_id (FK)
- sku
- name (optional)
- created_at
- updated_at
3. Asset Units

Physical inventory items.

asset_units
- id
- variant_id (FK)
- serial_number
- status
- location_id
- created_at
- updated_at
🧩 Specification System (Source of Truth)
4. Specifications (Definitions)
specifications
- id
- name                // "length", "color", "weight"
- type                // string, number, boolean, select
- applies_to          // asset | variant | unit | all
- is_filterable       // boolean
- created_at
- updated_at
5. Specification Values (Polymorphic)
specification_values
- id
- specification_id (FK)

- specable_type       // 'Asset', 'AssetVariant', 'AssetUnit'
- specable_id

- value_string
- value_number
- value_boolean

- created_at
- updated_at
Notes
This is the only place specs are edited
Supports full flexibility
Not optimized for querying
⚠️ Problem This Solves

Polymorphic specs alone create issues:

Complex joins
Poor performance at scale
Difficult filtering
No built-in inheritance
🚀 Resolved Layer (Critical for Scale)
6. Resolved Variant Specs
resolved_variant_specs
- id
- asset_id
- variant_id
- specification_id

- value_string
- value_number
- value_boolean

- created_at
- updated_at
🧠 Resolution Logic

When building this table:

IF variant has spec:
    use variant value
ELSE IF asset has spec:
    use asset value
ELSE:
    null (or skip row)
Example
Asset:
Material = Rubber
Variant:
Length = 50
Color = Orange
Resolved:
variant_id	spec	value
10	material	rubber
10	length	50
10	color	orange
⚡ Querying (Fast Filtering)

All filtering happens on:

resolved_variant_specs
Example Query

Find all variants:

Length = 50
Color = Orange
SELECT v.*
FROM asset_variants v

JOIN resolved_variant_specs length
  ON length.variant_id = v.id
 AND length.specification_id = :length_id

JOIN resolved_variant_specs color
  ON color.variant_id = v.id
 AND color.specification_id = :color_id

WHERE length.value_number = 50
  AND color.value_string = 'orange'
🧠 Indexing Strategy

Add indexes:

(specification_id, value_string)
(specification_id, value_number)
(variant_id, specification_id)
(asset_id)
🔄 Sync Strategy (Most Important Part)
When to Recompute resolved_variant_specs
1. Asset Spec Updated
Recompute ALL variants under that asset
2. Variant Spec Updated
Recompute ONLY that variant
3. Variant Created
Generate resolved specs using asset defaults
Implementation Approach
Use:
Model observers
Queue jobs (required for scale)
Example Flow
Asset Spec Updated
AssetObserver::updated()
    → dispatch RebuildAssetVariantsSpecsJob(asset_id)
Variant Spec Updated
AssetVariantObserver::updated()
    → dispatch RebuildVariantSpecsJob(variant_id)
🧩 Rebuild Algorithm (Pseudo Code)
function rebuildVariantSpecs($variant) {
    $asset = $variant->asset;

    $assetSpecs = getSpecs($asset);
    $variantSpecs = getSpecs($variant);

    $finalSpecs = mergeSpecs($assetSpecs, $variantSpecs);

    delete existing resolved specs for variant;

    insert $finalSpecs into resolved_variant_specs;
}
Merge Logic
function mergeSpecs($assetSpecs, $variantSpecs) {
    return array_merge(
        $assetSpecs,
        $variantSpecs // overrides
    );
}
🚫 What NOT To Do
❌ Do NOT query directly from specification_values
Too slow
Complex joins
No inheritance logic
❌ Do NOT duplicate specs manually across tables
Leads to inconsistencies
❌ Do NOT hardcode spec columns (weight, height, etc.)
Breaks flexibility
⚙️ Optional Enhancements
1. Filterable Specs Only

Only include:

specifications.is_filterable = true

in resolved_variant_specs

2. JSON Cache (Optional)

On asset_variants:

specs_cache JSON

Used for:

API responses
quick reads
3. Unit-Level Specs (Future)

Add:

resolved_unit_specs

Resolution order:

Unit → Variant → Asset
🧭 Final Architecture Summary
assets
  ↓
asset_variants
  ↓
asset_units

specifications
  ↓
specification_values (polymorphic, editable)

↓ (processed via jobs)

resolved_variant_specs (fast, queryable)