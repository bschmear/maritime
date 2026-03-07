# Dealership Sales → Service → Delivery Flow

Internal system design notes for dealership workflow.

Goal: Create a universal structure that works for:

- Boat dealerships
- Car dealerships
- RV / powersports dealers
- Equipment dealers

This flow must integrate with existing system objects:

- Leads
- Estimates
- Invoices
- Service Tickets
- Work Orders
- Deliveries

The system must support both **sales operations** and **service department execution**.

---

# Core System Objects

| Object | Purpose |
|------|------|
| Lead | Initial contact from potential customer |
| Opportunity | Qualified lead tied to a product |
| Estimate | Pricing proposal sent to customer |
| Contract | Signed agreement to purchase |
| Invoice | Payment requests (deposit or final) |
| Service Ticket | Internal prep / rigging coordination |
| Work Order | Technician tasks |
| Delivery | Handoff to customer |
| Documents | Registration and title paperwork |

---

# Primary Workflow


Lead
↓
Opportunity
↓
Estimate
↓
Contract
↓
Deposit Invoice
↓
Inventory Allocation / Manufacturer Order
↓
Service Ticket
↓
Work Orders
↓
Final Invoice
↓
Delivery
↓
Registration / Documentation


---

# 1. Lead Intake

Lead enters the system.

Possible sources:

- Walk in
- Website form
- Phone call
- Manufacturer referral
- Boat show
- Broker

### Data Collected

- Customer name
- Phone
- Email
- Lead source
- Product interest
- Intended use
- Budget range
- Timeline to purchase
- Delivery location

### System Action

Create:


Lead


Lead is then qualified and converted to an opportunity.

---

# 2. Opportunity Qualification

Salesperson determines:

- Product model
- Inventory availability
- Model year
- Colors
- Available upgrades
- Engine requirements
- Trailer requirements

### System Action

Create:


Opportunity


Opportunity links:

- Customer
- Product
- Salesperson

---

# 3. Estimate / Quote

Salesperson prepares pricing.

Estimate may include:

- Base product price
- Engine
- Options
- Accessories
- Trailer
- Delivery
- Taxes
- Discounts

### System Action

Create:


Estimate


Status flow:


Draft
Sent
Viewed
Negotiation
Approved
Rejected


---

# 4. Follow Up

Typical follow up sequence:

- 1 to 2 days after quote
- 5 to 7 days after quote

Possible outcomes:


Approved
Negotiation
Rejected
No response


If approved, move to contract stage.

---

# 5. Contract / Agreement

Customer signs purchase agreement.

System should support:

- Docusign integration
- Contract PDF storage
- Signature tracking

### System Action

Create:


Contract


Contract must include:

- Product details
- Options list
- Payment terms
- Delivery timeline
- Delivery location

---

# 6. Deposit

Typical structure:

- 50 percent deposit
- 50 percent final payment

### System Action

Create:


Invoice


Payment methods:

- Wire
- ACH
- Check
- Cash

Once deposit is received, the deal moves into operational execution.

---

# 7. Inventory Allocation or Manufacturer Order

Two possible paths.

### Existing Inventory


Allocate Inventory Unit


### Factory Order


Create Manufacturer Order


Sales team must confirm:

- Model year
- Color
- Options
- Lead time

---

# 8. Service Ticket Creation

Once inventory is allocated, internal prep begins.

This is the **handoff from sales to service department**.

### System Action

Create:


Service Ticket
ST-1000


Service Ticket represents the **entire build or prep process**.

Example tasks:

- Motor rigging
- Electronics install
- Trailer prep
- Accessory install
- Water test
- Cleaning
- Cover install

---

# 9. Work Orders

Work Orders are technician assignments created under the Service Ticket.

Example:


WO-1001 Motor Installation
WO-1002 Electronics Install
WO-1003 Accessories Install
WO-1004 Water Test


Each Work Order tracks:

- Technician
- Labor hours
- Parts used
- Completion status
- Notes

Work Order status flow:


Pending
Assigned
In Progress
Completed


---

# 10. Final Invoice

Once all Work Orders are completed, final payment is required.

### System Action

Create or update:


Final Invoice


Invoice includes:

- Remaining balance
- Taxes
- Delivery fees
- Additional options

Deal cannot proceed to delivery until payment clears.

---

# 11. Delivery

Once final payment is received:

### System Action

Create:


Delivery
DLV-1000


Delivery includes:

- Delivery date
- Delivery location
- Delivery technician
- Ramp location
- Customer confirmation

Delivery checklist may include:

- Unit inspection
- Engine start
- Electronics test
- Customer walkthrough
- Safety overview

---

# 12. After Sale Documentation

After delivery, registration and documentation must be completed.

Documents may include:

- MSO (Manufacturer Statement of Origin)
- Bill of sale
- Registration forms
- Title paperwork
- Power of attorney

### Possible Registrations

- Personal registration
- Business / corporate registration

Required data may include:

- Driver's license
- EIN
- Articles of incorporation
- Florida address
- Mailing address for title

---

# Key Operational Transition

The most important internal transition occurs here:


Sales Department
↓
Service Department


Trigger event:


Deposit Received
↓
Service Ticket Created


This moves the deal from **sales pipeline** to **operational execution**.

---

# Internal Status Model

Recommended deal status flow.


Lead
Qualified
Quoted
Contract Sent
Contract Signed
Deposit Received
Inventory Allocated
In Service Prep
Ready for Delivery
Delivered
Closed


---

# Future Expansion

System should later support:

- Warranty work orders
- Service department jobs
- PDI inspections
- Customer service tickets
- Maintenance scheduling

This keeps the system usable after the sale lifecycle.
