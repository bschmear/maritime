# InventoryUnit Actions Configuration

## Overview

The InventoryUnit actions (Create, Update, Delete) have been fully configured with proper validation rules, error handling, and field mapping.

## Actions Summary

### 1. CreateInventoryUnit
**File**: `app/Domain/InventoryUnit/Actions/CreateInventoryUnit.php`

**Features**:
- Full validation for all InventoryUnit fields
- Maps `inventory_item_id` to `parent_id` (database column)
- Foreign key validation for relationships
- Proper error handling with logging
- ValidationException pass-through for user feedback

**Validation Rules**:
```php
'inventory_item_id' => ['required', 'integer', 'exists:inventory_items,id']
'serial_number'     => ['nullable', 'string', 'max:255']
'hin'           => ['nullable', 'string', 'max:255']
'sku'               => ['nullable', 'string', 'max:255']
'batch_number'      => ['nullable', 'string', 'max:255']
'quantity'          => ['nullable', 'integer', 'min:1']
'condition'         => ['nullable', 'integer']
'status'            => ['nullable', 'integer']
'engine_hours'      => ['nullable', 'integer', 'min:0']
'cost'              => ['nullable', 'numeric', 'min:0']
'asking_price'      => ['nullable', 'numeric', 'min:0']
'price_history'     => ['nullable', 'array']
'vendor_id'         => ['nullable', 'integer', 'exists:users,id']
'owner_name'        => ['nullable', 'string', 'max:255']
'location_id'       => ['nullable', 'integer', 'exists:locations,id']
'inactive'          => ['nullable', 'boolean']
'notes'             => ['nullable', 'string']
```

### 2. UpdateInventoryUnit
**File**: `app/Domain/InventoryUnit/Actions/UpdateInventoryUnit.php`

**Features**:
- Same validation rules as Create
- Finds and updates existing record
- Maps `inventory_item_id` to `parent_id`
- Prevents updating timestamp and ID fields
- Proper error handling with logging

### 3. DeleteInventoryUnit
**File**: `app/Domain/InventoryUnit/Actions/DeleteInventoryUnit.php`

**Features**:
- Finds record by ID
- Soft/hard delete based on model configuration
- Error handling with logging
- Returns success/failure status

**Status**: ✅ Already correctly configured (no changes needed)

## Model Updates

### InventoryUnit Model
**File**: `app/Domain/InventoryUnit/Models/InventoryUnit.php`

**Added**:

1. **inventory_item_id to fillable**:
   ```php
   protected $fillable = [
       'parent_id',
       'inventory_item_id', // Alias for parent_id
       // ... other fields
   ];
   ```

2. **display_name Accessor**:
   ```php
   public function getDisplayNameAttribute()
   {
       // Priority: Serial Number > Hull ID > SKU > "Unit #{id}"
       if (!empty($this->serial_number)) {
           return "SN: {$this->serial_number}";
       }
       // ... etc
   }
   ```

3. **Appends Array**:
   ```php
   protected $appends = ['display_name'];
   ```
   Ensures `display_name` is always included in JSON/array output.

## Field Mapping

### API to Database Mapping
The actions handle field name differences between the API and database:

| API Field Name      | Database Column | Notes                           |
|---------------------|-----------------|--------------------------------|
| inventory_item_id   | parent_id       | Mapped automatically in actions |

This allows the frontend to use descriptive field names while maintaining database consistency.

## Display Name Generation

The model automatically generates a display name for each unit:

**Priority Order**:
1. `SN: {serial_number}` - If serial number exists
2. `HIN: {hin}` - If Hull ID exists
3. `SKU: {sku}` - If SKU exists
4. `Unit #{id}` - Fallback

**Examples**:
- Unit with serial number: "SN: ABC123"
- Boat with HIN: "HIN: ABC12345D606"
- Part with SKU: "SKU: PART-001"
- Unit with no identifiers: "Unit #42"

## Error Handling

All actions follow a consistent error handling pattern:

### Validation Errors
```php
try {
    $validated = Validator::make($data, [...])->validate();
} catch (ValidationException $e) {
    throw $e; // Passes to user
}
```
- ValidationException is re-thrown
- Frontend receives field-specific errors
- User sees helpful validation messages

### Database Errors
```php
catch (QueryException $e) {
    Log::error('Database query error', [...]);
    return [
        'success' => false,
        'message' => $e->getMessage()
    ];
}
```
- Logged for debugging
- Returns error message to user
- Transaction rolled back automatically

### Unexpected Errors
```php
catch (Throwable $e) {
    Log::error('Unexpected error', [...]);
    return [
        'success' => false,
        'message' => $e->getMessage()
    ];
}
```
- Catches all other exceptions
- Logged for debugging
- Prevents application crash

## Testing Examples

### Create Example
```php
$createAction = new CreateInventoryUnit();

$result = $createAction([
    'inventory_item_id' => 1,
    'serial_number' => 'ABC123',
    'cost' => 1500.00,
    'asking_price' => 2000.00,
    'condition' => 1, // New
    'status' => 1, // Available
]);

// Result:
// [
//     'success' => true,
//     'record' => InventoryUnit { ... }
// ]
```

### Update Example
```php
$updateAction = new UpdateInventoryUnit();

$result = $updateAction(1, [
    'inventory_item_id' => 1,
    'asking_price' => 2200.00, // Price increase
    'status' => 2, // Reserved
]);

// Result:
// [
//     'success' => true,
//     'record' => InventoryUnit { ... }
// ]
```

### Delete Example
```php
$deleteAction = new DeleteInventoryUnit();

$result = $deleteAction(1);

// Result:
// [
//     'success' => true,
//     'message' => 'Record deleted successfully.'
// ]
```

## Integration with Sublist Component

The actions work seamlessly with the Sublist component:

1. **Auto-Fill from Parent**:
   - `inventory_item_id` automatically filled from parent InventoryItem
   - `cost` and `asking_price` auto-filled from parent's `default_cost` and `default_price`

2. **Field Validation**:
   - Frontend receives validation errors
   - User sees field-specific error messages
   - Form highlights invalid fields

3. **Success Handling**:
   - Record created/updated successfully
   - Sublist automatically refreshes
   - New unit appears in the list

## Logs

All errors are logged to Laravel logs with context:

```
[2024-01-15 10:30:00] local.ERROR: Database query error in CreateInventoryUnit
{
    "error": "SQLSTATE[23000]: Integrity constraint violation...",
    "data": {
        "inventory_item_id": 999,
        "serial_number": "TEST123"
    }
}
```

Check logs at: `storage/logs/laravel.log`

## Foreign Key Constraints

The actions validate foreign key relationships:

- **inventory_item_id**: Must exist in `inventory_items` table
- **vendor_id**: Must exist in `users` table (nullable)
- **location_id**: Must exist in `locations` table (nullable)

Invalid IDs will fail validation before database insertion.

## Best Practices Applied

✅ **Validation**: All inputs validated before processing
✅ **Error Handling**: Comprehensive try-catch blocks
✅ **Logging**: All errors logged with context
✅ **Field Mapping**: API names mapped to database columns
✅ **Foreign Keys**: Validated before insertion
✅ **Display Names**: Auto-generated for UI display
✅ **Security**: Mass assignment protection
✅ **Consistency**: Follows InventoryItem patterns

## Next Steps

The actions are production-ready. You can now:

1. ✅ Create InventoryUnits from InventoryItem sublists
2. ✅ Update InventoryUnit details
3. ✅ Delete InventoryUnits
4. ✅ View InventoryUnits in tables and lists
5. ✅ See auto-generated display names

No further configuration needed!
