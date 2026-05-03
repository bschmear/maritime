🎯 Goal

Implement a flexible asset options system that allows dealers to define configurable options (e.g. colors, stereo packages), assign them to boat models or variants, and select them during estimate creation with proper pricing.

🧱 Core Concepts
1. Option (Group)

Represents a configurable category.

Examples:

Hull Color
Stereo Package
Upholstery

Fields:

name
slug
input_type (select, color, multi_select, toggle)
is_required
allow_multiple
active
2. Option Values

Selectable values within an option.

Examples:

Black, White, Blue (for color)
Standard, Premium Audio

Fields:

option_id
label
value (optional raw value)
color_hex (if color picker)
cost (internal cost)
price (customer price)
sort_order
active
3. Option Assignments

Defines where an option is available.

Rules:

Can be assigned to a full asset (model) OR a specific variant
Variant assignment overrides asset-level behavior

Fields:

option_id
asset_id (nullable)
variant_id (nullable)
cost_override (optional)
price_override (optional)
active

Constraint:

Unique per (option_id, asset_id, variant_id)
4. Estimate Selected Options (Snapshot)

Stores what the customer selected at time of estimate.

Must NOT rely on live data.

Fields:

estimate_id
option_id
option_value_id
option_name (snapshot)
value_label (snapshot)
cost (snapshot)
price (snapshot)
⚙️ Behavior Rules
Assignment Logic
If option assigned to asset → applies to all variants
If also assigned to variant → variant-specific overrides apply
If only assigned to variant → only available for that variant
Pricing Logic
Use option_value.price
If assignment override exists → use override
Snapshot final value into estimate
Estimate UI Behavior
Load options based on selected asset + variant
Respect:
is_required
allow_multiple
Allow user to select values
Store selection in snapshot table
🚫 Constraints
Do NOT store selections directly on assets or variants
Do NOT dynamically resolve pricing later (always snapshot)
Do NOT duplicate option definitions per asset
Do NOT hardcode option types
🧠 Design Goals
Fully dynamic (dealer-defined options)
Reusable across brands and models
Supports future integrations (catalog sync)
Safe against pricing changes over time
✅ Deliverables
Migrations for:
asset_options
asset_option_values
asset_option_assignments
estimate_selected_options
Eloquent relationships
Basic service layer for:
resolving available options
applying pricing
Estimate integration logic