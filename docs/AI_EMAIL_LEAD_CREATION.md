AI Inbox - Lead Intake via Email
Overview

Implement an AI-powered email ingestion system that allows tenants to create unique inbound email addresses. When an email is forwarded to one of these addresses, Helmful will:

Receive the email via SendGrid Inbound Parse.
Determine which tenant the email belongs to.
Store the raw email for auditing.
Queue processing.
Use AI to extract lead information.
Create a Lead record in the tenant's schema.

The system should be designed to support future automation types beyond lead creation.

Architecture
SendGrid

Inbound Parse is configured for:

inbound.helmful.com

SendGrid sends inbound emails to:

POST /api/inbound-email

Examples:

lead-a8f3d2@inbound.helmful.com
lead-k9m1x7@inbound.helmful.com

These addresses do not exist as mailboxes.

Database

All routing tables should exist in the central/public database.

email_routes

Stores inbound email routing configuration.

Columns:

id
tenant_id
address
action
is_active
created_at
updated_at

Example:

[
    'tenant_id' => 15,
    'address' => 'lead-a8f3d2@inbound.helmful.com',
    'action' => 'create_lead',
]
ai_email_ingestions

Stores all inbound emails for auditing and troubleshooting.

Columns:

id
tenant_id
email_route_id
status

from_email
to_email
subject

raw_payload

parsed_data

error

processed_at

created_at
updated_at

Status values:

pending
processing
completed
failed

Notes:

raw_payload should store the complete SendGrid payload.
parsed_data should store AI output JSON.
error should contain exception details if processing fails.
Models

Create:

App\Models\EmailRoute
App\Models\AiEmailIngestion

Both models belong to the central database connection.

Incoming Email Endpoint

Create:

POST /api/inbound-email

Controller:

App\Http\Controllers\Api\InboundEmailController

Responsibilities:

Read recipient email address.
Lookup matching EmailRoute.
Create AiEmailIngestion record.
Dispatch queue job.
Return success immediately.

Do NOT call AI directly in the controller.

Route Resolution

Incoming payload contains:

$request->input('to')

Example:

lead-a8f3d2@inbound.helmful.com

Lookup:

EmailRoute::where('address', $to)

If no route exists:

Return HTTP 200.
Log warning.
Do not create ingestion record.

Never expose route lookup failures publicly.

Queue Job

Create:

App\Jobs\ProcessInboundEmail

Constructor:

public function __construct(
    public int $ingestionId
)

Responsibilities:

Load ingestion.
Mark status as processing.
Resolve tenant.
Initialize tenancy.
Execute automation.
Mark completed or failed.
Automation Actions

Initially support:

create_lead

Design for future actions:

create_invoice
create_service_ticket
create_customer
create_work_order

Implementation should use a strategy/service pattern.

Example:

switch ($route->action) {
    case 'create_lead':
        ...
        break;
}

Prefer a dedicated action class architecture.

Lead Extraction Service

Create:

App\Services\Ai\LeadExtractionService

Input:

subject
emailBody

Output:

[
    'name' => '',
    'email' => '',
    'phone' => '',
    'company' => '',
    'notes' => '',
]

Responsibilities:

Build AI prompt.
Call OpenAI.
Validate response.
Return normalized data.

Return structured arrays only.

Lead Creation

Create lead in tenant schema.

Map:

name
email
phone

to Lead model fields.

Store full extracted notes.

If required information is missing:

Still create lead.
Populate available fields.
Preserve original email contents in notes.
AI Prompt Requirements

Prompt should:

Extract lead information.
Handle forwarded emails.
Handle signatures.
Handle dealer lead provider emails.
Return JSON only.

Expected schema:

{
  "name": "",
  "email": "",
  "phone": "",
  "company": "",
  "notes": ""
}
Tenant UI

Create:

Settings → AI Inbox

Table:

Email Address
Action
Status

Users can:

Create route
Disable route
Delete route
Route Generation

Generate unique addresses:

lead-{random}@inbound.helmful.com

Examples:

lead-a8f3d2@inbound.helmful.com
lead-z9m4c8@inbound.helmful.com

Requirements:

Random token
Unique
Not guessable
Security

Requirements:

Validate requests originate from SendGrid.
Rate limit endpoint.
Log failures.
Queue all processing.
Never process AI synchronously.
Logging

Log:

Route lookup failures
Queue failures
AI failures
Tenant initialization failures

Store all exceptions in:

ai_email_ingestions.error
Future Enhancements

Design system to support:

PDF invoice ingestion
Image attachments
Service request creation
Warranty claim creation
Inventory import emails
Customer creation
AI classification of email intent

Current MVP only implements:

Inbound Email → AI Extraction → Create Lead
