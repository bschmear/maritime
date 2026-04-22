# Warranty Handling for Invoice Line Items

## Objective
Update the invoice and work order system to properly handle **warranty-related line items** without distorting financial reporting.

The goal is to:
- Maintain accurate revenue and cost tracking
- Support both **manufacturer-paid** and **dealer-paid** warranty work
- Keep customer-facing invoices clean and understandable
- Avoid using discounts as a workaround for warranty coverage

---

## 🚫 Problem with Current/Naive Approach

Do NOT model warranty like this:

- Line Item: $500  
- Discount: -$500  
- Total: $0  

This causes:
- Inflated revenue
- Inflated discounts
- Misleading financial reports (P&L becomes noisy and inaccurate)

---

## ✅ Correct Conceptual Model

Warranty is NOT a discount.

Warranty means:
- The **customer is not the payer**
- Someone else is paying (manufacturer), OR
- The dealership is absorbing the cost

---

## 🧱 Required Data Model Changes

### Invoice Line Item Fields

Each line item should support:

```json
{
  "price": 500,
  "cost": 300,
  "quantity": 1,

  "is_warranty": true,
  "warranty_type": "manufacturer", // "manufacturer" | "dealer"

  "billable_to": "manufacturer" // "customer" | "manufacturer" | "internal"
}
Field Definitions
is_warranty (boolean)
Indicates if the line item is covered under warranty.
warranty_type (enum)
manufacturer: reimbursed by manufacturer
dealer: absorbed by dealership (internal cost)
billable_to (enum)
Controls who is financially responsible:
customer
manufacturer
internal
🧾 Invoice Behavior
Customer-Facing Invoice
For Warranty Items:
Do NOT treat as discount internally
Display options:

Option A (Preferred - Clean UI):

Water Pump Replacement .......... Covered Under Warranty
Total Due ....................... $0

Option B (More transparent):

Water Pump Replacement .......... $500
Warranty Coverage ............... -$500
Total Due ....................... $0

👉 This is display-only logic, not accounting logic.

💰 Accounting Rules
1. Manufacturer Warranty
Customer pays: $0
Manufacturer pays: YES
Accounting:
Revenue: $500 (from manufacturer)
Cost: $300
Profit: $200
Notes:
May require Accounts Receivable (manufacturer)
Revenue should NOT be tied to customer invoice
2. Dealer Warranty
Customer pays: $0
No reimbursement
Accounting:
Revenue: $0
Cost: $300
Profit: -$300
🔄 Work Order → Invoice Flow

When generating an invoice from a work order:

Preserve:
cost
price
is_warranty
warranty_type
billable_to
Do NOT auto-apply discounts
Do NOT modify price to zero
📊 Reporting Requirements (Critical)
Profit & Loss (P&L)
Include:
Revenue from:
Customer-paid items
Manufacturer-paid warranty
Exclude:
Dealer warranty from revenue
Always include:
Cost of all work performed
🧠 Internal vs External Views
Internal (Admin / CRM)

Show:

Price
Cost
Margin
Warranty type
Billable party
External (Customer Invoice)

Show:

Clean simplified version
Optional “Warranty Covered” indicator
Hide cost and internal logic
⚙️ Implementation Requirements
Backend
Update invoice line item schema
Update work order line items
Ensure warranty fields persist across transformations
Frontend
Add UI controls:
Toggle: "Is Warranty"
Select: "Warranty Type"
Display logic:
Hide or transform warranty items for customer view
Reporting Layer
Modify revenue calculations:
Ignore dealer warranty revenue
Include manufacturer warranty revenue (when applicable)
🚨 Important Constraints
NEVER rely on discounts to model warranty
NEVER lose cost data for warranty items
ALWAYS track who the true payer is
✅ Summary

Warranty handling must:

Separate who receives the service from who pays
Preserve accurate financial data
Provide clean customer-facing invoices
Support future integrations (manufacturer claims, reimbursements)

This change is foundational for:

Accurate P&L reporting
Scalable accounting
Real dealership workflows