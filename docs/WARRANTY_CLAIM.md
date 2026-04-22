# Warranty Claim System — Full Implementation Guide

## 🎯 Objective

Build a **Warranty Claim system** that allows the dealership to:

- Track manufacturer-paid warranty work
- Generate claims directly from work orders
- Manage submission, approval, and payment lifecycle
- Accurately reflect warranty revenue in financial reporting

This system must be **separate from customer invoices** and act as the true revenue pipeline for manufacturer warranty work.

---

## 🧠 Core Concept

A Warranty Claim represents:

> A request for payment from a manufacturer for work performed under warranty.

### Key Principle:
- Customer ≠ Payer
- Manufacturer = Payer

---

## 🔗 System Relationships


Work Order
↓
Invoice (Customer - usually $0)
↓
Warranty Claim (Manufacturer - actual revenue)


---

## 🧱 Database Schema

### warranty_claims

```php
Schema::create('warranty_claims', function (Blueprint $table) {
    $table->id();

    $table->foreignId('manufacturer_id')->constrained()->cascadeOnDelete();
    $table->foreignId('work_order_id')->nullable()->constrained()->nullOnDelete();

    $table->string('claim_number')->nullable();

    $table->enum('status', [
        'draft',
        'submitted',
        'approved',
        'rejected',
        'paid'
    ])->default('draft');

    $table->decimal('total_amount', 10, 2)->default(0);

    $table->timestamp('submitted_at')->nullable();
    $table->timestamp('approved_at')->nullable();
    $table->timestamp('paid_at')->nullable();

    $table->text('notes')->nullable();

    $table->timestamps();
});
warranty_claim_line_items
Schema::create('warranty_claim_line_items', function (Blueprint $table) {
    $table->id();

    $table->foreignId('warranty_claim_id')->constrained()->cascadeOnDelete();
    $table->foreignId('work_order_line_item_id')->nullable()->constrained()->nullOnDelete();

    $table->string('description');

    $table->integer('quantity')->default(1);

    $table->decimal('price', 10, 2); // what manufacturer pays
    $table->decimal('cost', 10, 2)->nullable(); // internal cost

    $table->timestamps();
});
🧩 Required Fields from Work Orders

Each Work Order Line Item must include:

{
  "price": 500,
  "cost": 300,
  "is_warranty": true,
  "warranty_type": "manufacturer",
  "billable_to": "manufacturer"
}
⚙️ Workflow
1. Work Order Creation
Technician/service team adds line items
Marks items as:
is_warranty = true
warranty_type = manufacturer
2. Customer Invoice
Generated from Work Order
Warranty items:
Show as "Covered under warranty"
Total = $0

👉 No revenue recorded here

3. Create Warranty Claim
Trigger:
Button: "Create Warranty Claim"
Behavior:
Pull all eligible line items:
is_warranty = true
warranty_type = manufacturer
Pre-fill:
Description
Quantity
Price (editable)
Cost (for internal tracking)
4. Claim Editing (Draft State)

User can:

Modify pricing (manufacturer rates differ)
Add/remove items
Add notes
Attach files (photos, receipts, docs)
5. Submit Claim
Status → submitted
Set submitted_at

Optional:

Create Accounts Receivable entry
6. Approval / Rejection
Approved:
Status → approved
Set approved_at
Rejected:
Status → rejected
Add rejection reason
Allow edits and resubmission
7. Payment
Status → paid
Set paid_at
Accounting:
Revenue recognized here (or at approval, depending on accounting method)
Payment recorded
💰 Financial Behavior
Manufacturer Warranty
Stage	Revenue	Notes
Work Order	❌	No revenue yet
Invoice	❌	Customer not paying
Claim Draft	❌	Not submitted
Submitted	⚠️	Optional AR
Approved	✅	Revenue recognized (optional)
Paid	✅	Cash received
Dealer Warranty (for reference)
Should NOT create warranty claims
Cost only
Negative margin impact
🧾 UI / UX Requirements
Work Order Screen
Checkbox: "Warranty Item"
Dropdown: "Warranty Type"
Manufacturer
Dealer
Invoice Screen
Show:
“Covered under warranty”
Hide:
Internal cost
Warranty billing logic
Warranty Claim Builder
Table of line items
Editable:
Price
Quantity
Display:
Cost (internal only)
Actions:
Save Draft
Submit Claim
Warranty Claim List

Columns:

Claim #
Manufacturer
Status
Total
Submitted Date
Paid Date

Filters:

Status
Manufacturer
🔁 Sync Behavior
Work order updates do NOT auto-update submitted claims
Draft claims can be refreshed manually
📊 Reporting Requirements
P&L
Include:
Revenue from approved/paid warranty claims
Exclude:
Customer invoices for warranty work
Include:
Cost of all work (always)
🚨 Edge Cases
Partial Approval
Manufacturer approves only some items or partial amounts
Must support line-level adjustments
Rejected Claims
Must allow:
Editing
Resubmitting
Multiple Claims per Work Order
Allowed (real-world scenario)
Split Manufacturers
If parts from different manufacturers:
Separate claims per manufacturer
🚀 Future Enhancements
Manufacturer-specific pricing rules
Auto-fill labor rates by brand
Claim templates
Bulk claim submission
Integration with manufacturer APIs
✅ Summary

Warranty Claims:

Are separate from invoices
Represent actual revenue source for warranty work
Require lifecycle tracking (draft → paid)
Must preserve:
Price (what you bill)
Cost (what you spent)
Enable accurate:
Financial reporting
Cash flow tracking
Operational workflows