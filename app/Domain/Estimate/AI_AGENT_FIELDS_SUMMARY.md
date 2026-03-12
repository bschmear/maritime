AI-Friendly Relational Overview
1. Opportunities
Field   Type    Notes
id  bigint  PK
uuid    uuid    Unique identifier
sequence    bigint  Sequential number for display/reference
customer_id bigint  FK → Customers
user_id bigint  FK → User (Salesperson)
name    string  Optional opportunity title
stage   tinyint Enum for opportunity stage
status  tinyint Enum for opportunity status
needs_engine    boolean Optional flag
needs_trailer   boolean Optional flag
internal_notes  text    Internal comments
customer_notes  text    Notes for customer context
opened_at   timestamp   When opportunity created
won_at  timestamp   When opportunity won
lost_at timestamp   When opportunity lost
createdby_id    bigint  FK → User who created
timestamps  timestamp   Laravel created_at / updated_at
softDeletes timestamp   Soft deletion support

Relationships:

customer_id → Customer

user_id → Salesperson

Can link to Estimates (one-to-many)

2. Estimates
Field   Type    Notes
id  bigint  PK
uuid    uuid    Unique identifier
opportunity_id  bigint  FK → Opportunity (optional)
customer_id bigint  FK → Customer
version tinyint Version number (1, 2, 3...)
copied_from_id  bigint  FK → Previous estimate version
status  tinyint Draft, Sent, Viewed, Approved, Rejected
locked  boolean True if estimate should not be edited
subtotal    decimal Calculated sum of line items
tax decimal Tax applied to subtotal
tax_rate    decimal Tax rate stored
total   decimal subtotal + tax
internal_notes  text    Internal comments
customer_notes  text    Optional notes for customer
createdby_id    bigint  FK → User who created estimate
timestamps  timestamp   created_at / updated_at
softDeletes timestamp   Soft deletion support

Relationships:

Belongs to Opportunity and Customer

Has many Estimate Line Items (polymorphic)

Versions handled via version + copied_from_id

3. Estimate Line Items
Field   Type    Notes
id  bigint  PK
estimate_id bigint  FK → Estimate
lineable_type   string  Polymorphic: Asset or InventoryItem
lineable_id bigint  FK → specific record
name    string  Snapshot of item name at time of estimate
description text    Snapshot description
unit_price  decimal Price per unit
quantity    decimal Typically 1 for boats, >1 for parts/accessories
discount    decimal Optional discount per line item
total   decimal Calculated per line item (unit_price × quantity - discount)
timestamps  timestamp   created_at / updated_at

Relationships:

Polymorphic:

Asset → Boats, trailers, vehicles

InventoryItem → Parts, accessories

Notes:

Snapshot fields ensure line items do not change when the inventory record changes.

Each version of an estimate has its own set of line items.

4. Versioning

Each estimate version has:

Its own set of line items

Independent subtotal, tax, total

Optional locked flag to prevent editing after approval

Switching versions:

Totals are recalculated from that version's line items

5. Invoice

Tied to a specific estimate version

Pulls totals and line items from the selected version

Can be Deposit Invoice or Final Invoice

Tracks payment methods and status

6. Summary Notes for AI Agent

Opportunities = high-level customer interest + salesperson plan

Estimates = detailed financial proposal, versioned

Line Items = polymorphic; snapshot fields for consistency

Versions = allow quoting changes without overwriting history

Invoice = finalized payment request from selected estimate version
