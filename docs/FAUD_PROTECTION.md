# Chargeback Defense Architecture for Dealership SaaS

This document outlines:

1. Dispute Evidence Architecture in Laravel
2. Enforcing 3D Secure Programmatically with Stripe
3. Auto-Generating Dispute Evidence Packets

This is designed for high-ticket dealerships, marine shops, and service-based businesses.

---

# 1. Dispute Evidence Architecture (Laravel)

## Goal

When a dispute occurs, you should already have:

- Signed agreement
- Timestamp
- IP address
- User agent
- Proof of delivery
- Payment metadata
- Immutable PDF snapshot
- Hash of signed content

Everything must be stored at time of transaction.

---

## A. Core Tables

### service_tickets

- id
- uuid
- customer_id
- total
- status
- approved_at
- approval_hash
- signed_name
- signature_path
- approved_ip
- approved_user_agent
- timestamps

---

### payments

- id
- service_ticket_id
- stripe_payment_intent_id
- stripe_charge_id
- amount
- currency
- payment_method_type
- three_d_secure_used (boolean)
- risk_score (nullable)
- paid_at
- timestamps

---

### deliveries (if physical goods)

- id
- service_ticket_id
- signed_delivery_name
- delivery_signature_path
- delivery_ip
- delivered_at
- gps_coordinates (nullable)
- timestamps

---

### documents

Used to store:
- Signed PDFs
- Delivery photos
- Invoice snapshots
- Proof attachments

---

## B. Approval Hash

When customer signs:

hash('sha256', json_encode([
ticket_uuid,
total_amount,
line_items_snapshot,
acknowledgement_text,
signed_at,
ip_address
]));


Store this in:

service_tickets.approval_hash

This proves document integrity at time of signing.

---

## C. Locking Rules

After approval:

- Prevent price edits
- Prevent line item edits
- Force revision workflow if changes required

Disputes are often won by showing document integrity.

---

# 2. Enforcing 3D Secure Programmatically (Stripe)

3D Secure shifts liability in many fraud cases.

For high-ticket transactions, enforce it.

---

## A. Stripe PaymentIntent Creation (Server-Side)

In Laravel:

```php
\Stripe\PaymentIntent::create([
    'amount' => $amount,
    'currency' => 'usd',
    'customer' => $stripeCustomerId,
    'payment_method_types' => ['card'],
    'payment_method_options' => [
        'card' => [
            'request_three_d_secure' => 'any',
        ],
    ],
    'metadata' => [
        'service_ticket_uuid' => $ticket->uuid,
    ],
]);
Options:

'any' → Trigger 3D Secure if available

'automatic' → Let Stripe decide

'challenge_only' → Force challenge when possible

Recommended for high ticket items:

request_three_d_secure = 'any'
B. Conditional 3D Secure Enforcement
Example:

If amount > $1,000 → Require 3D Secure

if ($amount > 100000) {
    $threeDSecure = 'any';
} else {
    $threeDSecure = 'automatic';
}
C. Store 3D Secure Status
After payment succeeds:

Retrieve PaymentIntent:

$intent = \Stripe\PaymentIntent::retrieve($intentId);
Check:

$intent->charges->data[0]->payment_method_details->card->three_d_secure
Store:

three_d_secure_used

authentication_result

liability_shift (if available)

This helps tremendously during disputes.

3. Auto-Generating Dispute Evidence Packets
When Stripe notifies of a dispute:

Webhook event:

charge.dispute.created
You should:

Retrieve related payment

Retrieve related service ticket

Compile evidence

Submit to Stripe automatically (optional)

A. Webhook Listener
if ($event->type === 'charge.dispute.created') {
    $chargeId = $event->data->object->charge;
    $payment = Payment::where('stripe_charge_id', $chargeId)->first();

    DisputeEvidenceService::generate($payment);
}
B. Evidence Collection Service
Collect:

Customer name

Email

IP address at approval

User agent

Signed PDF

Approval hash

Delivery confirmation

Invoice

Proof of 3D Secure usage

Refund policy text

Customer communication logs

C. Evidence Packet Structure
Generate:

PDF summary report

Attach signed agreement

Attach delivery confirmation

Attach payment receipt

Attach logs

Example report sections:

Transaction Summary

Customer Information

Authorization Evidence

3D Secure Authentication Details

Service Fulfillment Evidence

Delivery Confirmation

Signed Agreement

Communication History

D. Stripe Evidence Submission
Stripe API allows submitting evidence:

\Stripe\Dispute::update(
    $disputeId,
    [
        'evidence' => [
            'customer_name' => $customerName,
            'customer_email_address' => $customerEmail,
            'billing_address' => $billingAddress,
            'access_activity_log' => $loginLog,
            'service_documentation' => $signedPdfUrl,
            'receipt' => $receiptUrl,
        ],
    ]
);
You can automate this once evidence is assembled.

4. Recommended Chargeback Defense Stack
For high-ticket dealerships:

Mandatory 3D Secure over threshold

Signed digital agreement

Immutable PDF snapshot

Approval hash stored

Delivery confirmation signature

Proof of customer login

IP + device logging

Refund policy acknowledgment

Automatic dispute evidence packet generator

5. Risk Mitigation Strategy Tiering
Lowest Risk:

Wire transfers

ACH

In-person chip card

Medium Risk:

Online card with 3D Secure

Highest Risk:

Manual card entry without authentication

For transactions over $5,000:
Strongly recommend ACH or wire.

6. Final Summary
To minimize chargebacks:

Authenticate payment strongly (3D Secure)

Capture explicit authorization

Preserve document integrity

Lock pricing after signing

Capture delivery confirmation

Automate dispute evidence assembly

This transforms your system from:
Reactive fraud response

Into:
Proactive fraud defense infrastructure
