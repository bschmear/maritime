# Service Ticket Client Approval Flow

## Overview

This document outlines the recommended workflow for sending service tickets to clients for review and digital signature.

The system uses a web-based approval process. A PDF is generated only after the client signs, serving as a locked legal record.

---

# 1. Workflow States

### Suggested Status Flow

- Draft
- Open
- Estimating
- Sent
- In Progress
- Completed
- Closed
- Cancelled

> Note: "Approved" should NOT be a status.
> Approval should be tracked using a boolean and timestamp.

---

# 2. Approval Architecture

## Step 1: Send Ticket

Client receives email with secure link:

/service-tickets/{uuid}/review

The link should:
- Use UUID, not incremental ID
- Optionally expire after X days
- Optionally require email verification token

---

## Step 2: Client Review Page

Client sees:

- Company branding
- Ticket number
- Customer name
- Asset details
- Repair description
- Line items
- Subtotal
- Tax
- Total
- Acknowledgement text
- Approve / Decline buttons
- Signature pad
- Consent checkbox

---

## Step 3: Capture Approval

When client approves, store:

approved_at (timestamp)
approved_ip (string)
approved_user_agent (string)
signed_name (string)
signature_path (string or file reference)
approval_hash (string)

Set:
approved = true

If declined:
- store declined_at
- store decline_reason (optional)
- do NOT allow work order creation

---

# 3. Approval Hash (Integrity Protection)

When signing, generate a hash of critical data:

- ticket ID
- totals
- acknowledgement text
- line items snapshot
- timestamp
- IP address

Example logic:

hash('sha256', json_encode([...]))

Store this as approval_hash.

This prevents disputes about modified totals after signing.

---

# 4. PDF Generation (After Approval)

Once signed:

1. Generate a PDF snapshot
2. Embed:
   - Signature image
   - Signed name
   - Signed timestamp
   - IP address
3. Store PDF as a document
4. Email PDF copy to client

The PDF becomes the permanent legal artifact.

Never allow PDF regeneration that alters signed data.

---

# 5. Locking Rules

After approval:

- Prevent estimate edits
- Prevent line item edits
- Prevent total edits

If changes are needed:
- Create a revision
- Require re-authorization

---

# 6. Re-Authorization Trigger

If revised total exceeds threshold (default 20%):

1. Create service_ticket_revision record
2. Lock work order
3. Send new approval link
4. Capture new signature
5. Store revision hash

---

# 7. Required Database Fields

Recommended fields on service_tickets:

- approved_at (timestamp nullable)
- approved_ip (string nullable)
- approved_user_agent (string nullable)
- signed_name (string nullable)
- signature_path (string nullable)
- approval_hash (string nullable)
- declined_at (timestamp nullable)

Recommended fields on service_ticket_revisions:

- service_ticket_id
- previous_estimated_total
- revised_estimated_total
- percent_increase
- items_snapshot (json)
- signature_method
- approved (boolean)
- signed_at
- signed_ip
- signed_user_agent
- signature_hash
- approved_by_user_id (nullable)
- timestamps

---

# 8. Security Best Practices

- Use UUID for public routes
- Do not expose internal IDs
- Expire approval links if desired
- Throttle approval route
- Validate signature not empty
- Log approval activity

---

# 9. Clean Separation of Responsibilities

Service Ticket = Intake + Estimate + Authorization
Work Order = Execution

Approval controls work order creation.

Work order completion can automatically close service ticket.

---

# 10. Final Recommended Flow

Draft
↓
Open
↓
Estimating
↓
Sent to Client
↓
Client Signs
↓
approved_at set
↓
Work Order Created
↓
Work Completed
↓
Closed
2
---

# Summary

Best Practice:

Web-based approval first
Store legal metadata
Generate PDF after signing
Lock data after approval
Use revision system for increases

This provides:

- Strong legal defensibility
- Clean architecture
- Better UX
- Scalable workflow
