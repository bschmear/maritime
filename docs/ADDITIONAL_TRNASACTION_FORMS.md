# AI Implementation Guide: Additional Transaction Forms System

## Overview

Build a document automation system that supports:

- PDF templates with draggable mapped fields
- DOCX templates with placeholder replacement
- Auto-filling from transaction/contact/property data
- Manual editing before export
- Saving generated transaction-specific document instances

Tech stack:

- Laravel
- Vue 3
- PostgreSQL
- PDF.js
- FPDI
- PHPWord

---

# System Architecture

## Core Concepts

There are TWO different document engines:

| Type | Strategy |
|---|---|
| PDF | Visual coordinate overlay system |
| DOCX | Placeholder replacement system |

DO NOT attempt to unify rendering logic between the two.

---

# Database Schema

## additional_form_templates

Stores uploaded templates.

```php
Schema::create('additional_form_templates', function (Blueprint $table) {
    $table->id();

    $table->uuid('uuid')->unique();

    $table->foreignId('company_id')
        ->constrained()
        ->cascadeOnDelete();

    $table->string('name');

    $table->string('document_type');

    // pdf/docx
    $table->string('file_path');

    $table->string('thumbnail_path')
        ->nullable();

    $table->unsignedInteger('page_count')
        ->nullable();

    $table->boolean('is_active')
        ->default(true);

    $table->timestamps();
});
```

---

## additional_form_template_fields

Stores field mappings.

```php
Schema::create('additional_form_template_fields', function (Blueprint $table) {
    $table->id();

    $table->uuid('uuid')->unique();

    $table->foreignId('additional_form_template_id')
        ->constrained()
        ->cascadeOnDelete();

    $table->string('key');

    $table->string('label');

    $table->string('type')
        ->default('text');

    $table->string('source_path')
        ->nullable();

    // PDF positioning
    $table->unsignedInteger('page')
        ->nullable();

    // normalized coordinates (0.0 - 1.0)
    $table->decimal('x', 8, 6)
        ->nullable();

    $table->decimal('y', 8, 6)
        ->nullable();

    $table->decimal('width', 8, 6)
        ->nullable();

    $table->decimal('height', 8, 6)
        ->nullable();

    // DOCX placeholder
    $table->string('placeholder')
        ->nullable();

    $table->boolean('required')
        ->default(false);

    $table->boolean('editable')
        ->default(true);

    $table->json('settings')
        ->nullable();

    $table->timestamps();
});
```

---

## transaction_additional_forms

Stores generated form instances tied to transactions.

```php
Schema::create('transaction_additional_forms', function (Blueprint $table) {
    $table->id();

    $table->uuid('uuid')->unique();

    $table->foreignId('transaction_id')
        ->constrained()
        ->cascadeOnDelete();

    $table->foreignId('additional_form_template_id')
        ->nullable()
        ->constrained()
        ->nullOnDelete();

    $table->string('status')
        ->default('draft');

    $table->string('generated_file_path')
        ->nullable();

    $table->string('finalized_file_path')
        ->nullable();

    $table->timestamp('generated_at')
        ->nullable();

    $table->timestamp('completed_at')
        ->nullable();

    $table->timestamps();
});
```

---

## transaction_additional_form_fields

Stores immutable snapshots of generated field values.

```php
Schema::create('transaction_additional_form_fields', function (Blueprint $table) {
    $table->id();

    $table->uuid('uuid')->unique();

    $table->foreignId('transaction_additional_form_id')
        ->constrained()
        ->cascadeOnDelete();

    $table->foreignId('additional_form_template_field_id')
        ->nullable()
        ->constrained()
        ->nullOnDelete();

    $table->string('key');

    $table->string('type');

    $table->string('source_path')
        ->nullable();

    $table->longText('value')
        ->nullable();

    $table->boolean('is_overridden')
        ->default(false);

    // snapshot coordinates
    $table->unsignedInteger('page')
        ->nullable();

    $table->decimal('x', 8, 6)
        ->nullable();

    $table->decimal('y', 8, 6)
        ->nullable();

    $table->decimal('width', 8, 6)
        ->nullable();

    $table->decimal('height', 8, 6)
        ->nullable();

    $table->timestamps();
});
```

---

# Required Packages

## Backend Packages

### PHPWord

```bash
composer require phpoffice/phpword
```

Purpose:
- DOCX template processing
- Placeholder replacement

Official:
https://phpoffice.github.io/PHPWord/

---

### FPDI

```bash
composer require setasign/fpdi
```

Purpose:
- Import existing PDFs
- Write overlay text onto PDFs

Official:
https://www.setasign.com/products/fpdi/about/

---

### Spatie PDF to Image

```bash
composer require spatie/pdf-to-image
```

Purpose:
- Generate PDF thumbnails/previews

Official:
https://github.com/spatie/pdf-to-image

---

## Frontend Packages

### PDF.js

```bash
npm install pdfjs-dist
```

Purpose:
- Render PDF pages inside Vue

Official:
https://mozilla.github.io/pdf.js/

---

### Interact.js

```bash
npm install interactjs
```

Purpose:
- Dragging
- Resizing
- Field placement

Official:
https://interactjs.io/

---

# Directory Structure

## Laravel Storage

```txt
storage/app/forms/templates/
storage/app/forms/generated/
storage/app/forms/finalized/
storage/app/forms/previews/
```

---

# Enums

## Document Type Enum

```php
enum AdditionalFormDocumentType: string
{
    case Pdf = 'pdf';
    case Docx = 'docx';
}
```

---

## Field Type Enum

```php
enum AdditionalFormFieldType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Date = 'date';
    case Currency = 'currency';
    case Checkbox = 'checkbox';
    case Signature = 'signature';
}
```

---

# Backend Services

## AdditionalFormValueResolver

Purpose:
Resolve source paths against transaction data.

Example:

```php
resolve(
    Transaction $transaction,
    string $sourcePath
): mixed
```

Supported examples:

```txt
transaction.purchase_price
buyer.full_name
property.address
agent.email
```

Implementation should use:

```php
data_get()
```

But wrapped inside a dedicated service.

DO NOT scatter data_get calls throughout the application.

---

# PdfFormGenerator Service

Responsibilities:

- Load template PDF
- Apply field values
- Write text overlays
- Save generated PDF
- Save finalized PDF

Recommended library:
- FPDI

---

# DocxTemplateGenerator Service

Responsibilities:

- Load DOCX
- Replace placeholders
- Generate filled DOCX
- Optional PDF conversion later

Recommended library:
- PHPWord TemplateProcessor

---

# PDF Coordinate Rules

IMPORTANT:
Never store raw pixels.

Store normalized coordinates:

```txt
0.0 - 1.0
```

Example:

```txt
x = 0.42
y = 0.61
```

Render formula:

```js
actualX = normalizedX * renderedWidth
actualY = normalizedY * renderedHeight
```

This avoids:
- zoom problems
- retina scaling issues
- responsive issues

---

# Vue Component Structure

## Recommended Components

```txt
components/
├── forms/
│   ├── PdfTemplateEditor.vue
│   ├── PdfPageCanvas.vue
│   ├── DraggableField.vue
│   ├── TransactionFormViewer.vue
│   ├── DocxVariablePanel.vue
│   └── FieldSettingsPanel.vue
```

---

# PDF Editor Flow

## Admin Workflow

1. Upload PDF
2. Render pages with PDF.js
3. Overlay draggable fields
4. Select source mapping
5. Save field coordinates

---

# PDF Runtime Flow

## User Workflow

1. Open transaction
2. Click additional forms
3. Select form
4. Generate field values
5. Render editable overlay
6. Save/export finalized PDF

---

# DOCX Workflow

## Admin Workflow

User uploads DOCX with placeholders.

Example:

```txt
Buyer Name: {{buyer.full_name}}

Purchase Price: {{transaction.purchase_price}}
```

Store detected placeholders into:
- additional_form_template_fields

---

# DOCX Runtime Flow

1. Load transaction
2. Resolve placeholders
3. Replace values
4. Generate DOCX
5. Optionally convert to PDF

---

# DOCX Placeholder Parsing

Regex:

```php
/{{(.*?)}}/
```

Extract variables from uploaded DOCX XML.

Store them automatically.

---

# Recommended API Endpoints

## Templates

```txt
GET    /api/additional-form-templates
POST   /api/additional-form-templates
PUT    /api/additional-form-templates/{id}
DELETE /api/additional-form-templates/{id}
```

---

## Template Fields

```txt
POST   /api/additional-form-templates/{id}/fields
PUT    /api/additional-form-template-fields/{id}
DELETE /api/additional-form-template-fields/{id}
```

---

## Transaction Forms

```txt
GET    /api/transactions/{id}/additional-forms
POST   /api/transactions/{id}/additional-forms
GET    /api/transaction-additional-forms/{id}
PUT    /api/transaction-additional-forms/{id}
```

---

# PDF Rendering Notes

Use PDF.js.

Recommended strategy:

- Render each page to canvas
- Overlay absolute-positioned fields
- Use relative positioning container

Example:

```css
position: relative;
```

Overlay fields:

```css
position: absolute;
```

---

# Dragging System

Use Interact.js.

Features needed:
- drag
- resize
- snap to bounds

Save normalized coordinates after drag end.

---

# Recommended MVP

## Phase 1

### PDF
- Upload template
- Place text fields
- Autofill fields
- Export PDF

### DOCX
- Upload template
- Replace placeholders
- Export DOCX

---

## Phase 2

- Manual editable fields
- Save partial progress
- Generated previews
- DOCX helper UI

---

## Phase 3

- Signatures
- Conditional fields
- Approval workflows
- Multi-user collaboration

---

# Important Rules

## DO NOT

- Store pixel coordinates
- Modify original templates
- Depend on PDFs containing AcroForms
- Share rendering logic between PDF and DOCX systems

---

# Future Improvements

## Conditional Logic

Example:

```json
{
  "show_if": {
    "field": "loan_type",
    "equals": "cash"
  }
}
```

---

## Computed Fields

Examples:

```txt
buyer.full_name
transaction.formatted_price
```

Support these through resolver service methods.

---

# Suggested Development Order

## Step 1
Build DOCX replacement engine first.

Why:
- Easier
- Faster
- Less frontend complexity

---

## Step 2
Build PDF renderer + overlay editor.

---

## Step 3
Build finalized PDF export.

---

## Step 4
Add signatures and workflows.

---

# Success Criteria

The system should allow:

1. Admin uploads document
2. Admin maps fields
3. User opens transaction
4. Document auto-fills
5. User edits remaining fields
6. User exports finalized document

