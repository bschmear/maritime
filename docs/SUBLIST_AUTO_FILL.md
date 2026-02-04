# Sublist Auto-Fill Configuration

## Overview

The Sublist component supports automatic field population from parent records when creating sublist items. This is configured in the sublist's `form.json` schema file.

## Configuration

In your sublist domain's `form.json`, add a `sublistConditions` section with an `auto_fill` object:

```json
{
  "form": [...],
  "sublists": [...],
  "sublistConditions": {
    "auto_fill": {
      "target_field": "ParentDomain.source_field",
      "another_field": "ParentDomain.another_source"
    }
  }
}
```

## Format

- **Key**: The field name in the sublist record to auto-fill
- **Value**: Dot notation string: `"DomainName.field_name"`
  - `DomainName`: Must match the parent record's domain
  - `field_name`: The field name in the parent record to pull the value from

## Example: InventoryUnit from InventoryItem

**File**: `/app/Domain/InventoryUnit/Schema/form.json`

```json
{
  "sublistConditions": {
    "auto_fill": {
      "cost": "InventoryItem.default_cost",
      "asking_price": "InventoryItem.default_price"
    }
  }
}
```

When creating an InventoryUnit from an InventoryItem's sublist:
1. The `cost` field will be pre-filled with the InventoryItem's `default_cost` value
2. The `asking_price` field will be pre-filled with the InventoryItem's `default_price` value

## Automatic Behavior

### Parent Reference Fields
The component automatically handles parent reference fields (fields where `typeDomain` matches the parent domain):
- Auto-fills the field with the parent record's ID
- Locks/disables the field
- Displays the parent record's name

Example:
```json
{
  "fields": {
    "inventory_item_id": {
      "type": "record",
      "typeDomain": "InventoryItem"
    }
  }
}
```
This field is **automatically** locked and filled when creating from an InventoryItem.

### Custom Auto-Fill Fields
Fields configured in `sublistConditions.auto_fill` are:
- Pre-filled with the parent's value
- **NOT locked** - user can still modify them
- Only filled if the domain matches and the value exists

## How It Works

1. User clicks "Add New" in a sublist (e.g., Inventory Units)
2. System loads the sublist's schema
3. System identifies parent reference fields by matching `typeDomain`
4. System locks and fills parent reference fields
5. System reads `sublistConditions.auto_fill` configuration
6. System checks if the source domain matches the parent
7. System pulls values from the parent record
8. Form opens with all fields pre-populated

## Domain Matching

Auto-fill only occurs when the domain in the configuration matches the parent:

✅ **Works**: Creating InventoryUnit from InventoryItem
- Config: `"cost": "InventoryItem.default_cost"`
- Parent: InventoryItem
- Match: ✓

❌ **Skipped**: Creating InventoryUnit from Customer
- Config: `"cost": "InventoryItem.default_cost"`
- Parent: Customer
- Match: ✗ (skipped, no auto-fill)

## Use Cases

### 1. Default Pricing
Pre-fill cost and price from a catalog item to individual units:
```json
{
  "auto_fill": {
    "cost": "InventoryItem.default_cost",
    "asking_price": "InventoryItem.default_price"
  }
}
```

### 2. Inherited Properties
Copy specifications from parent to child:
```json
{
  "auto_fill": {
    "length": "BoatModel.standard_length",
    "width": "BoatModel.standard_width",
    "weight": "BoatModel.standard_weight"
  }
}
```

### 3. Default Assignments
Pre-assign based on parent context:
```json
{
  "auto_fill": {
    "assigned_to": "Project.manager_id",
    "priority": "Project.default_priority"
  }
}
```

## Debugging

Enable browser console to see auto-fill logs:
```
getSublistInitialData - Auto-fill config: {...}
Auto-filled cost = 1500 from InventoryItem.default_cost
Auto-filled asking_price = 2000 from InventoryItem.default_price
```

## Notes

- Values must exist in the parent record (not null/undefined)
- Field types should match (currency to currency, text to text, etc.)
- Auto-filled fields can still be edited by the user
- Parent reference fields are always locked and cannot be edited
- Configuration is per-sublist domain (not per-parent domain)
