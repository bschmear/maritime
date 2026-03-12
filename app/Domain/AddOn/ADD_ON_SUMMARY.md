Add-on Types

Add-ons support two usage patterns:

1. Common Add-ons (Reusable)

Reusable options stored in the addons table.

Examples:

Name	Default Price
Garmin Electronics Package	4500
Premium Sound System	1800
Custom Hull Color	1200

These can be selected from a list during estimate creation.

Common add-ons may optionally be restricted to a specific product type.

Example:

type = InventoryItem
type = Asset
type = null (universal)
2. Custom Add-ons (One-Off)

Salespeople can also create custom typed-in add-ons directly on the estimate.

Example:

Name	Price
Custom Offshore Package	3500
Special Trailer Modification	900

These do not require a record in the addons table.

Core Tables
Add-ons

Stores reusable add-ons.

Table: addons

Fields:

id
uuid
name
default_price
description
type (InventoryItem, Asset, or null)
timestamps
soft_deletes

Purpose:

Maintain a catalog of common upgrades

Allow quick selection during quoting

Estimate Line Item Add-ons

Add-ons are attached to estimate line items, not directly to estimates.

Example structure:

Estimate
→ Line Item (Boat)
→ Add-ons (Electronics, Color, Accessories)

Table: estimate_line_item_addon

Fields:

id
estimate_line_item_id
addon_id (nullable)
name (for custom add-ons)
price
quantity
metadata (json)
timestamps
Behavior
When selecting a common add-on
addon_id = referenced
name = optional
price = copied from default_price (editable)
When creating a custom add-on
addon_id = null
name = user entered
price = user entered
Metadata

The metadata JSON column allows storing additional configuration details.

Examples:

{
  "color": "Matte Black"
}
{
  "brand": "Garmin",
  "screen_size": "12 inch"
}

This allows flexible configuration without schema changes.

Pricing Behavior

Add-ons contribute to the line item total.

Line item total:

(base_price × quantity)
+ sum(addon_price × addon_quantity)

Estimate totals are calculated from all line items and their add-ons.

Relationship Structure
Estimate
 └ Line Items
     └ Add-ons

Add-ons cannot exist without a line item.

Key Design Principles

The add-on system is designed to support:

reusable upgrades

one-off customizations

flexible product configuration

dealership product packages

future product expansion

This structure works for:

boats

vehicles

RVs

equipment

powersports