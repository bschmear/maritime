SMS Notification System Architecture Reference

This document explains the purpose, architecture, and responsibilities of the SMS notification system so future AI agents and developers can safely extend or modify it without tightly coupling the application to a specific SMS provider.

The system is intentionally designed to be provider-agnostic.

Initial provider:

Twilio

Future providers may include:

Amazon SNS
Plivo
Telnyx
Purpose of This System

This system handles outbound transactional SMS notifications for tenants.

Examples:

Delivery status updates
Invoice notifications
Quote notifications
Appointment reminders
System alerts

This is NOT a conversational messaging platform.

The application does not currently support:

inbound conversations
messaging inboxes
chat threads
agent/customer messaging

The system is strictly:

event-driven outbound notifications.

High-Level Architecture
Laravel Events
    ↓
Notification Listeners
    ↓
SmsService
    ↓
SmsProviderFactory
    ↓
SmsProviderInterface
    ↓
TwilioProvider

Future:

SmsProviderInterface
    ├── TwilioProvider
    ├── AwsSnsProvider
    ├── PlivoProvider
Architectural Goals

The system is designed to:

✅ Keep business logic independent from providers
✅ Allow providers to be swapped later
✅ Keep all SMS sending centralized
✅ Support tenant-level notification settings
✅ Queue all outbound messages
✅ Log all outbound SMS activity
✅ Normalize provider responses

Core Principle

The application should NEVER directly depend on Twilio.

Twilio is only a transport layer.

All application code should communicate through:

SmsService

The service internally resolves the active provider.

This prevents vendor lock-in.

Provider-Agnostic Design

The system uses an interface-based provider architecture.

Provider Contract
SmsProviderInterface

All providers must implement:

send(string $to, string $message, ?string $from = null): SmsResult

This guarantees:

consistent responses
consistent error handling
interchangeable providers
Why This Matters

Without abstraction:

Controller → Twilio SDK

Switching providers later becomes painful.

With abstraction:

Controller → SmsService → Provider

Switching providers only requires:

new provider implementation
config change

No business logic changes.

SmsService Responsibilities

SmsService is the central orchestration layer.

It is responsible for:

resolving provider
validating tenant settings
dispatching queue jobs
logging SMS messages
formatting outbound data
normalizing provider behavior

This is the ONLY service application code should call.

Provider Responsibilities

Providers ONLY handle transport.

Example:

authenticate with provider
send message
parse provider response
normalize response into SmsResult

Providers should NOT contain:

business logic
tenant logic
notification rules
event handling
SmsResult Purpose

All providers return:

SmsResult

This normalizes:

success state
provider message ID
status
errors

This prevents provider-specific response handling throughout the app.

Queue-Based Architecture

All SMS messages must be queued.

Never send synchronously during requests.

Reason:

provider latency
retries
failure handling
rate limiting
request performance

Recommended stack:

Redis
Laravel Horizon
Event-Driven Notification Flow

Notifications should originate from domain events.

Examples:

InvoiceSent
InvoicePaid
DeliveryStatusUpdated
QuoteReady
AppointmentReminder

Listeners:

check tenant SMS settings (via `App\Services\SMS\SmsService::tenantWantsSms()` / `AccountSettings::wantsSms()`)
build message
dispatch SendSmsJob
Tenant Settings System

Each tenant can enable/disable:

SMS globally
individual notification types

Example:

Enable SMS Notifications

[ ] Estimate
[ ] Invoice
[ ] Delivery

(Additional categories such as service tickets or surveys can be added later.)

Globals on `account_settings`:

- `sms_enabled` (master)
- `sandbox_mode` (testing; nav badge and “route all SMS to current user” behavior are layered on top in separate work). Edited under **General Account Settings** on `/account` (main account form), not on the SMS categories page.

Per-type flags on `sms_notification_preferences` (see `App\Enums\SMS` and `App\Models\SmsNotificationPreference`).

Account UI: `GET` / `PATCH` `/account/notifications/sms` (route names `account.notifications.sms.index` and `account.notifications.sms.update`). Hub link from `/account` (Text notifications card).

`SmsService` exposes `smsGloballyEnabled()`, `smsSandboxMode()`, and `tenantWantsSms($type)` (`App\Enums\SMS|string`); queueing, logging, and provider sends are still to be wired.
SMS Logging

All outbound messages should be logged.

Table:

sms_messages

Purpose:

debugging
audit history
delivery tracking
provider migration
future analytics
Twilio-Specific Notes

Current provider:

Twilio

Twilio-specific logic must remain isolated inside:

TwilioProvider.php

No other part of the application should directly use:

Twilio SDK
Twilio response objects
Twilio-specific APIs
Future Provider Migration

To support a new provider:

Example
AwsSnsProvider.php

Implement:

SmsProviderInterface

Then update config:

SMS_PROVIDER=aws-sns

No other application logic should change.

That is the purpose of the abstraction layer.

Message Template System

Notification text should not be hardcoded in listeners.

Templates belong in:

app/Services/SMS/Templates/

Example:

InvoiceSentTemplate.php

This keeps:

formatting centralized
messages reusable
future localization easier
Current Scope

Current scope includes:

outbound SMS notifications
shared sending number
transactional messaging
tenant-level toggles

Current scope does NOT include:

inbound SMS
chat
MMS
conversations
support inboxes
marketing campaigns
Architectural Rules
DO

✅ Use SmsService everywhere
✅ Queue all sends
✅ Keep providers isolated
✅ Normalize provider responses
✅ Use events/listeners
✅ Log all outbound messages

DO NOT

❌ Call Twilio SDK directly outside providers
❌ Put business logic in providers
❌ Hardcode provider behavior
❌ Depend on provider-specific response objects
❌ Send synchronously during requests

Final Goal

The final system should provide:

✅ Clean SMS architecture
✅ Multi-provider flexibility
✅ Event-driven notifications
✅ Tenant-level controls
✅ Easy future migration
✅ Centralized logging
✅ Scalable queue-based delivery