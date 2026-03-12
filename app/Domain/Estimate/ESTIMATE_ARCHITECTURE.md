Dealership Estimate System – Configuration Overview
Purpose

Estimates represent a formal pricing proposal for a customer, capturing all costs related to a sale before any payment is made. They are derived from an Opportunity, but can exist in multiple versions, allowing sales to iterate on proposals.

Core Concepts
1. Estimate Record

Tied directly to a Customer.

Optionally tied to the Opportunity and/or a Lead for traceability.

Contains versioning:

version_number tracks iteration (1, 2, 3…)

copied_from_id links to a previous version if this is a revision.

Tracks financials:

subtotal – sum of line item totals before tax

tax – calculated tax

tax_rate – stores percentage applied

total – subtotal + tax

Tracks payment status:

Optional boolean: locked to prevent editing once finalized.

Other metadata:

status – draft, sent, viewed, approved, rejected

notes – internal or customer-facing

created_by, assigned_user (salesperson)

2. Line Items

Estimates have line items representing individual products or services. Line items are polymorphic, supporting two record types:

Assets – serialized units like boats or trailers

Inventory Items – parts, accessories, engines, electronics, rigging items

Line Item Fields

itemable_type – class name of the source record (Asset or InventoryItem)

itemable_id – ID of the source record

Snapshot fields (required to preserve the quote even if source changes):

name

description

unit_price

discount

line_total (unit_price * quantity - discount)

quantity – number of units (1 for assets like boats, may be >1 for parts)

position – ordering of line items

item_category – optional: boat, engine, trailer, electronics, accessory, service, fee

Example:
itemable_type   itemable_id name    category    quantity    unit_price  line_total
Asset   12  Walker Bay 10   boat    1   25,000  25,000
InventoryItem   42  Garmin GPS  electronics 1   1,500   1,500
3. Versioning

Each Estimate can have multiple versions.

Line items are tied to a specific version, so switching versions shows the correct totals.

Field copied_from_id tracks origin if a version is based on a previous estimate.

Enables easy comparison: version 1 vs version 2.

4. Workflow Context

Opportunity – defines products the salesperson thinks align with the customer’s interest (high-level, possible line items suggested, may still be vague).

Estimate – copies opportunity details into a concrete proposal:

Adds actual line items with quantities, unit prices, discounts, and totals.

Captures tax rates and financial calculations.

Tracks versions for iterative quoting.

Approval – once an estimate is approved and a deposit is received:

The deal moves into operational execution (inventory allocation, service prep, and work orders).

5. Snapshot Importance

Even if the underlying Asset or InventoryItem changes later (price, description), the estimate preserves the exact quote provided to the customer.

Prevents accidental recalculation or discrepancies.

6. Summary

Estimates = formal, financial representation of an Opportunity.

Line items = polymorphic records, can be assets or inventory items.

Versions allow iterative quoting without losing history.

Snapshot fields preserve historical accuracy.

Tax and totals are stored to simplify delivery and invoicing.


flowchart TD
    A[Opportunity] --> B[Estimate]
    B --> C[Estimate Version]
    C --> D[Line Items]
    D -->|Polymorphic| E[Asset]
    D -->|Polymorphic| F[Inventory Item]
    C --> G[Subtotal / Tax / Total]
    C --> H[Locked / Editable]
    B --> I[Customer]
    G --> J[Invoice]

    subgraph "Notes"
        direction LR
        B[Estimate] -->|Copied From| K[Previous Version]
        D -->|Snapshot Fields| L[Preserve Name, Price, Description]
    end


Explanation:

Opportunity

High-level view of what the customer wants.

Captures suggested products, model, options, salesperson.

Estimate

Tied to a Customer and optionally to Opportunity.

Can have multiple versions.

Status tracked: draft, sent, viewed, approved, rejected.

Estimate Version

Each version contains a snapshot of line items.

copied_from_id links to previous version for traceability.

Switching versions recalculates subtotal/tax/total for that version.

Line Items

Polymorphic: can reference Asset (boats, trailers) or Inventory Item (parts, accessories).

Snapshot fields preserve exact quoted details: name, description, unit price, quantity, discount.

Financials

Subtotal, tax, tax rate, total stored on the estimate version.

Optional locked boolean prevents editing after approval.

Invoice

Generated from the approved estimate version.

Pulls totals and line items from the selected version.
