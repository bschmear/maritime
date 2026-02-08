# Action Files Guide

## Overview

Action files are responsible for creating, updating, and deleting records in the application. They handle validation, data processing, and error handling in a consistent way across all domains.

## File Structure

Each domain should have three action files:

```
app/Domain/{DomainName}/Actions/
├── Create{DomainName}.php
├── Update{DomainName}.php
└── Delete{DomainName}.php
```

## Create Action Template

### Basic Structure

```php
<?php
namespace App\Domain\{DomainName}\Actions;

use App\Domain\{DomainName}\Models\{DomainName} as RecordModel;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Throwable;

class Create{DomainName}
{
    public function __invoke(array $data): array
    {
        // 1. Validation
        $validator = Validator::make($data, [
            // Add validation rules here
        ]);

        if ($validator->fails()) {
            return [
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()->toArray(),
                'record' => null,
            ];
        }

        try {
            // 2. Process validated data
            $recordData = $validator->validated();

            // 3. Apply defaults for nullable fields
            // Example: $recordData['field_name'] = $recordData['field_name'] ?? 'default_value';

            // 4. Add non-validated fields that should be saved
            $additionalFields = ['field1', 'field2'];
            foreach ($additionalFields as $field) {
                if (array_key_exists($field, $data)) {
                    $recordData[$field] = $data[$field];
                }
            }

            // 5. Create the record
            $record = RecordModel::create($recordData);

            return [
                'success' => true,
                'record' => $record,
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in Create{DomainName}', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in Create{DomainName}', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        }
    }
}
```

### Validation Rules

#### Required Fields
For fields marked as `required` in `fields.json`, use:
```php
'field_name' => 'required|string|max:255',
```

#### Optional Fields
For nullable fields, use:
```php
'field_name' => 'nullable|string|max:255',
```

#### Common Field Types

**Text/String:**
```php
'display_name' => 'required|string|max:255',
'code' => 'nullable|string|max:50',
```

**Textarea:**
```php
'description' => 'nullable|string',
'notes' => 'nullable|string',
```

**Numeric/Currency:**
```php
'default_rate' => 'nullable|numeric|min:0',
'default_cost' => 'nullable|numeric|min:0',
'quantity' => 'required|integer|min:1',
```

**Boolean:**
```php
'taxable' => 'nullable|boolean',
'active' => 'nullable|boolean',
```

**Enums:**
```php
'billing_type' => 'required|string',
'status' => 'required|integer',
```

**Record Relations (Foreign Keys):**
```php
'customer_id' => 'nullable|integer|exists:customers,id',
'subsidiary_id' => 'nullable|integer|exists:subsidiaries,id',
```

**Dates:**
```php
'due_at' => 'nullable|date',
'scheduled_start_at' => 'nullable|date',
```

### Default Values

Apply default values **after** validation but **before** creating the record:

```php
$recordData = $validator->validated();

// Apply defaults for nullable numeric fields
$recordData['default_rate'] = $recordData['default_rate'] ?? 0;
$recordData['default_cost'] = $recordData['default_cost'] ?? 0;

// Apply defaults for nullable boolean fields
$recordData['active'] = $recordData['active'] ?? true;
$recordData['billable'] = $recordData['billable'] ?? true;
```

### Non-Validated Fields

Some fields should be saved but not validated (e.g., JSON attributes, tenant context):

```php
// Add any additional non-validated fields that should be saved
$additionalFields = ['subsidiary_id', 'attributes', 'attachments'];
foreach ($additionalFields as $field) {
    if (array_key_exists($field, $data)) {
        $recordData[$field] = $data[$field];
    }
}
```

## Update Action Template

### Basic Structure

```php
<?php
namespace App\Domain\{DomainName}\Actions;

use App\Domain\{DomainName}\Models\{DomainName} as RecordModel;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Throwable;

class Update{DomainName}
{
    public function __invoke(int $id, array $data): array
    {
        // 1. Validation (use 'sometimes|required' for required fields)
        $validator = Validator::make($data, [
            // Add validation rules here
        ]);

        if ($validator->fails()) {
            return [
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()->toArray(),
                'record' => null,
            ];
        }

        try {
            // 2. Process validated data
            $recordData = $validator->validated();

            // 3. Apply defaults for nullable fields (only if present in update)
            if (array_key_exists('default_rate', $recordData) && $recordData['default_rate'] === null) {
                $recordData['default_rate'] = 0;
            }

            // 4. Add non-validated fields that should be saved
            $additionalFields = ['field1', 'field2'];
            foreach ($additionalFields as $field) {
                if (array_key_exists($field, $data)) {
                    $recordData[$field] = $data[$field];
                }
            }

            // 5. Update the record
            $record = RecordModel::findOrFail($id);
            $record->update($recordData);

            return [
                'success' => true,
                'record' => $record,
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in Update{DomainName}', [
                'error' => $e->getMessage(),
                'id' => $id,
                'data' => $data
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in Update{DomainName}', [
                'error' => $e->getMessage(),
                'id' => $id,
                'data' => $data
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        }
    }
}
```

### Update-Specific Validation

For update actions, use `sometimes|required` for fields that are required **only if present**:

```php
'display_name' => 'sometimes|required|string|max:255',
'billing_type' => 'sometimes|required|string',
```

This allows partial updates where only changed fields are sent.

## Delete Action Template

```php
<?php
namespace App\Domain\{DomainName}\Actions;

use App\Domain\{DomainName}\Models\{DomainName} as RecordModel;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Throwable;

class Delete{DomainName}
{
    public function __invoke(int $id): array
    {
        try {
            $record = RecordModel::findOrFail($id);
            $record->delete();

            return [
                'success' => true,
                'message' => 'Record deleted successfully.',
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in Delete{DomainName}', [
                'error' => $e->getMessage(),
                'id' => $id
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in Delete{DomainName}', [
                'error' => $e->getMessage(),
                'id' => $id
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
```

## Best Practices

### 1. Always Validate Required Fields

Check `fields.json` for fields with `"required": true` and add corresponding validation rules:

```json
// fields.json
{
  "display_name": {
    "label": "Service Name",
    "type": "text",
    "required": true
  }
}
```

```php
// CreateAction.php
$validator = Validator::make($data, [
    'display_name' => 'required|string|max:255',
]);
```

### 2. Handle Nullable Database Columns

If a database column is NOT NULL but you want to accept null from the form, apply a default:

```php
// Migration has: $table->decimal('default_rate', 10, 2)->default(0);
// Action should:
$recordData['default_rate'] = $recordData['default_rate'] ?? 0;
```

### 3. Preserve Non-Validated Fields

Some fields should be saved without validation (e.g., `attributes`, `subsidiary_id`):

```php
$additionalFields = ['subsidiary_id', 'attributes', 'attachments'];
foreach ($additionalFields as $field) {
    if (array_key_exists($field, $data)) {
        $recordData[$field] = $data[$field];
    }
}
```

### 4. Return Consistent Response Format

Always return an array with:
- `success` (boolean)
- `record` (object or null)
- `message` (string, for errors)
- `errors` (array, for validation errors)

```php
// Success
return [
    'success' => true,
    'record' => $record,
];

// Validation failure
return [
    'success' => false,
    'message' => $validator->errors()->first(),
    'errors' => $validator->errors()->toArray(),
    'record' => null,
];

// Exception
return [
    'success' => false,
    'message' => $e->getMessage(),
    'record' => null,
];
```

### 5. Log Errors Properly

Always log errors with context:

```php
Log::error('Database query error in CreateServiceItem', [
    'error' => $e->getMessage(),
    'data' => $data
]);
```

## Common Patterns

### Auto-Incrementing Custom Numbers

For fields like `work_order_number` that should auto-increment:

```php
if (empty($recordData['work_order_number'])) {
    $lastRecord = RecordModel::orderBy('work_order_number', 'desc')->first();
    $recordData['work_order_number'] = $lastRecord ? $lastRecord->work_order_number + 1 : 1000;
}
```

### Conditional Field Processing

For fields that depend on other fields:

```php
// If status is "Draft", set draft flag
if (isset($recordData['status']) && $recordData['status'] == 1) {
    $recordData['draft'] = true;
}
```

### Handling JSON Fields

For JSON fields like `attributes`:

```php
// In validation
'attributes' => 'nullable|array',

// In additionalFields
$additionalFields = ['attributes'];
```

## Checklist for Creating Action Files

- [ ] Check `fields.json` for all field definitions
- [ ] Check `form.json` for required fields
- [ ] Check migration for NOT NULL constraints
- [ ] Add validation rules for all user-input fields
- [ ] Use `required` for required fields in Create action
- [ ] Use `sometimes|required` for required fields in Update action
- [ ] Apply default values for nullable database columns
- [ ] Include non-validated fields (attributes, subsidiary_id, etc.)
- [ ] Return consistent response format
- [ ] Add proper error logging
- [ ] Test with missing required fields
- [ ] Test with null values for nullable fields
- [ ] Test with invalid data types

## Example: Complete Service Item Actions

See the following files for a complete implementation:
- `app/Domain/ServiceItem/Actions/CreateServiceItem.php`
- `app/Domain/ServiceItem/Actions/UpdateServiceItem.php`
- `app/Domain/ServiceItem/Actions/DeleteServiceItem.php`

These files demonstrate all the patterns and best practices outlined in this guide.
