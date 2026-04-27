# Boat catalog and tenant assets — architecture plan (v2)

This document replaces the earlier **file-only** mental model (`models.json` / `meta.json` as the system of record). The system of record for makes, model lines, and variants is the **inventory database** (`maritime_inventory`). AI and static JSON are inputs used to **seed or extend** that database; tenants **import** rows into their schema as **Assets** and **Asset variants**, linked by stable keys.

---

## 1. Core mental model (lock this in)

| Layer | Role |
|--------|------|
| **Canonical manufacturers** | Curated list (e.g. [manufacturers.json](/app/Domain/BoatMake/Schema/manufacturers.json)): each row has `display_name` + **`slug` = brand key** (unique globally). |
| **Inventory DB** | Holds `boat_make` (or `makes`) + `assets` + `asset_variants` aligned with your listing/catalog app. Brand key lives on the make row as **`slug`**. Model lines are `assets` (with `make_id`); trims/sizes are `asset_variants` with a stable **`key`**. |
| **Tenant DB** | User selects which brands they carry; `boat_make` rows store the same **`brand_key`** (slug) as inventory. Imported catalog rows become tenant **`assets`** / **`asset_variants`** with matching keys so duplicates are impossible across re-imports and listing sync. |
| **AI fallback** | When a chosen brand has **no** (or incomplete) catalog rows in the inventory DB, call OpenAI with the strict JSON schema (see §2–3), **write results to the inventory DB first**, then import into the tenant. |

---

## 2. Canonical data contract (AI / API shape)

Unchanged intent: model meta is either **single specifications** or **variants array**, never both. See [open_ai_payload.php](./open_ai_payload.php) and [BoatMetaAIService](/app/Services/BoatMetaAIService.php) for the strict schema, validation, and normalization.

---

## 3. AI schema

Continue using **`response_format: json_schema`** and server-side validation; do not persist until validated. After validation, map into **inventory DB tables** (not `meta.json`).

---

## 4. Inventory database schema (your DDL + required additions)

You provided:

- **`assets`**: model line / catalog asset (`make_id`, `model`, `slug`, specs, `has_variants`, etc.).
- **`asset_variants`**: includes **`key`** (varchar) — use this as the **global dedupe id** for a trim/size line under a model.

**Gap in the pasted DDL:** `assets.make_id` implies a **parent make table**. Add an explicit table in the inventory DB, for example:

- **`boat_make`** (or `makes`): `id`, **`slug`** (unique, **brand key**), `display_name`, `active`, timestamps — and optionally `logo`, `asset_types` if you want parity with tenant.

Seed **`slug` + `display_name`** from the manufacturer list (`manufacturers.json`: slug keys + display labels; legacy array format can derive slugs; store both so the UI can show labels while keys stay stable).

**Model-line dedupe:** Your `assets` table has `slug` but no `key` column. Either:

- Treat **`assets.slug`** as the canonical **model catalog key** (unique per `make_id`, or globally unique if prefixed, e.g. `walker-bay--g15`), **or**
- Add an optional **`key`** column on `assets` mirroring variants.

**Variants:** Keep using **`asset_variants.key`**; enforce **unique (`asset_id`, `key`)** in inventory (and the same in tenant after migration).

---

## 5. Tenant database changes

### 5.1 `boat_make`

- Add **`brand_key`** (string, nullable for legacy): stores the inventory make **`slug`**. Must match inventory `boat_make.slug` for that brand.
- Optional: unique index on `brand_key` per tenant schema so the same brand is not added twice.
- **`slug`** on tenant `boat_make` can stay aligned with `brand_key` for URLs, or remain display-derived — but **listing consistency** should be driven by **`brand_key`**.

### 5.2 `assets` and `asset_variants` (tenant)

- Add **`catalog_asset_key`** (or reuse `slug` with a documented convention) on **`assets`**: must match inventory **`assets.slug`** (or `assets.key` if you add it).
- Add **`key`** on **`asset_variants`** if not present (inventory already has it): match inventory **`asset_variants.key`**.
- Import logic: **upsert** by `(make_id, catalog_asset_key)` for assets and `(asset_id, key)` for variants so re-running “import all” does not duplicate.

### 5.3 Laravel connection

- Register an **`inventory`** connection in [config/database.php](/config/database.php) using `INVENTORY_*` from `.env`.
- Migrations for inventory tables live in a dedicated path (e.g. `database/migrations/inventory`) and run with `--database=inventory`.

---

## 6. UX flows

### 6.1 “What brands do you work with?”

- Replace free-text “add boat make” with a **multi-select** from the canonical manufacturer list (each option: `display_name` + **`slug` / brand_key`**).
- On confirm: create or activate one **`boat_make`** per selected slug, setting **`brand_key`** = inventory slug (and `display_name` from list).

### 6.2 After brands are added — import catalog as assets

- For each brand with `brand_key`, read from **inventory DB**: makes + assets (+ variants) for that `make_id`.
- Prompt: **“Import all models, details, and variants for this brand?”** (or pick specific models in a follow-up UI).
- **Grey out** models/variants already present in the tenant: join on **`catalog_asset_key`** and variant **`key`** (and `make_id` / `boat_make.id`).

### 6.3 Creating tenant rows

- **Asset (model line):** map inventory `assets` columns into tenant `assets`; set `make_id` to the tenant’s `boat_make.id` for that brand; set **`catalog_asset_key`** / `slug` from inventory.
- **Asset variants:** for each inventory `asset_variant`, create tenant variant with same **`key`**; set `has_variants` on the parent asset when appropriate.
- Map AI `specifications` / variant specs into existing columns (`length`, `beam`, `persons`, `maximum_power`, `fuel_tank`, etc.) and optionally **`attributes`** JSON for fields without columns (e.g. height, weight) until you add columns.

### 6.4 Brand with no (or partial) inventory data

- If inventory has the make but **no** assets (or user requests enrichment): run **BoatMetaAIService** (or equivalent) per model, then **insert into inventory `assets` / `asset_variants`**, then import into tenant (same keys).
- Optionally add a separate **“list models for this make”** AI step if you need discovery before per-model meta calls.

---

## 7. Backend flow (high level)

1. **Resolve manufacturer** by `slug` (brand key) against inventory `boat_make`.
2. **Tenant onboarding:** upsert tenant `boat_make` rows from multi-select (`brand_key` = slug).
3. **List importable catalog:** query inventory `assets` (+ variants) for `make_id`; subtract set already imported (tenant query by keys).
4. **Import:** transactional batch upsert into tenant `assets` / `asset_variants`.
5. **AI path:** validate → write inventory → import tenant (keys identical).

---

## 8. Warnings (still critical)

- Do **not** let AI overwrite existing inventory rows without an explicit review or version policy.
- Do **not** invent variant **keys**; normalize to slug-style strings (as in [BoatMetaAIService](/app/Services/BoatMetaAIService.php)) and enforce uniqueness.
- **Queue** long-running AI + bulk imports to avoid HTTP timeouts.

---

## 9. Deprecations relative to v1 of this doc

- **`meta.json` / `models.json` as system of record** — demote to optional dev cache or remove after inventory DB is populated.
- **Boat make as arbitrary text** — remove for onboarding; use keyed manufacturer list + `brand_key` on `boat_make`.
- **Inventory items vs assets** — this iteration standardizes on **Assets** + **Asset variants** for catalog models (per your direction); **InventoryItem** boat flows can be updated later to reference the same keys if you want one spine for both.

---

## 10. Implementation checklist (engineering)

1. Add **`inventory`** DB connection and inventory migrations (`boat_make`, ensure `assets` / `asset_variants` match DDL + unique constraints on keys).
2. Seed inventory **`boat_make`** from `app/Domain/BoatMake/Schema/manufacturers.json` (display_name + slug).
3. Tenant migrations: **`boat_make.brand_key`**, tenant **`assets.catalog_asset_key`** (or documented slug rule), **`asset_variants.key`**.
4. Update **BoatMetaAIService** persistence target from disk to **inventory DB** (or a service that writes inventory first).
5. API + UI: multi-select brands; import all / subset; grey-out imported keys.
6. Align [BoatMake](/app/Domain/BoatMake/Models/BoatMake.php) / asset forms: brand picker is **select from keyed list**, not free text.

---

## 11. Reference: inventory `asset_variants` (excerpt)

`key` varchar(255) — **stable variant identifier** for dedupe and listing sync.

---

## 12. Reference: inventory `assets` (excerpt)

`make_id` → parent make in inventory; `slug` on model line; `has_variants` drives whether variant rows are authoritative for specs.
