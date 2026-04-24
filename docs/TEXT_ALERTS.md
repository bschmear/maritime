📄 Tenant SMS Notification System (Telnyx)
Overview

We are implementing outbound-only SMS notifications for a multi-tenant boat dealership CRM.

Each tenant:

Has their own phone number
Sends transactional alerts only (no 2-way messaging required initially)
Operates within a schema-based multi-tenant architecture
🎯 Use Cases

Send SMS notifications for:

Boat delivery notifications
Invoices
Work Order updates
Contract signature requests
Estimate approvals
Service ticket signatures
Survey follow-up requests
🧱 Architecture Overview
Multi-Tenant Structure
Central DB (landlord)
Stores global config like:
Telnyx API key
Messaging profiles (optional)
Tenant DB schemas
Each tenant stores:
Their assigned phone number
SMS logs
Notification preferences
☁️ SMS Provider

Using: Telnyx

Why:

Easy number provisioning
Strong API
Good pricing
Supports scaling later into voice or 2-way messaging
🧩 Database Schema
Central Database (optional)
sms_providers
id
name (telnyx)
api_key (encrypted)
created_at
updated_at
Tenant Schema
sms_numbers

Stores the phone number assigned to the tenant

id
phone_number
telnyx_number_id
messaging_profile_id (nullable)
is_active (boolean)
created_at
updated_at
sms_messages

Stores all outgoing messages

id
to_number
from_number
message
status (queued, sent, delivered, failed)
telnyx_message_id (nullable)
metadata (json) -- invoice_id, work_order_id, etc
sent_at (timestamp)
created_at
updated_at
sms_notification_settings

Per-tenant toggle settings

id
event_key (invoice_sent, work_order_update, etc)
enabled (boolean)
created_at
updated_at
🔑 Event Keys

Standardize these:

[
  'boat_delivery',
  'invoice_sent',
  'work_order_update',
  'contract_signature',
  'estimate_approval',
  'service_ticket_signature',
  'survey_followup'
]
⚙️ Service Layer
SendSmsAction.php
class SendSmsAction
{
    public function execute(string $to, string $message, array $meta = [])
    {
        $from = tenant()->sms_number;

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.telnyx.api_key'),
        ])->post('https://api.telnyx.com/v2/messages', [
            'from' => $from,
            'to' => $to,
            'text' => $message,
        ]);

        SmsMessage::create([
            'to_number' => $to,
            'from_number' => $from,
            'message' => $message,
            'status' => $response->successful() ? 'sent' : 'failed',
            'telnyx_message_id' => $response->json('data.id') ?? null,
            'metadata' => $meta,
            'sent_at' => now(),
        ]);
    }
}
🔔 Notification Layer

Use Laravel Events + Listeners

Example
Event
InvoiceSent
Listener
SendInvoiceSmsNotification
Logic
if (! SmsSettings::enabled('invoice_sent')) {
    return;
}

$message = "Your invoice is ready: {$invoice->url}";

app(SendSmsAction::class)->execute(
    $customer->phone,
    $message,
    ['invoice_id' => $invoice->id]
);
🧠 Message Strategy

Keep messages:

Short
Actionable
Link-driven
Examples

Invoice

Your invoice is ready: {link}

Delivery

Your boat delivery is on the way. ETA: 2:30 PM

Work Order

Update: Your service request is in progress

Signature

Please sign your document: {link}

🔗 URL Strategy

Always use signed or short-lived links

Example:

URL::temporarySignedRoute(...)

Optional later:

Add link shortener (/s/abc123)
📈 Future Expansion (Not Now)
Inbound SMS (2-way messaging)
MMS (images, PDFs)
Read/delivery receipts via webhooks
Automated reminders
Campaign messaging
⚠️ Important Constraints
Outbound only (for now)
No chat UI needed
No real-time threading required
Focus on reliability + logging
🧪 Testing Strategy
Use test numbers from Telnyx
Log all responses
Add retry logic later if needed
🧭 Admin UI (Optional Later)

Per tenant:

Toggle notifications on/off
View message logs
Assign/change phone number
💡 Key Design Principle

This is not a messaging system.
This is a notification system with traceability.