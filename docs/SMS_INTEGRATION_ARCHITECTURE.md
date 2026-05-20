Flexible SMS Architecture Plan (Twilio First, Provider-Agnostic)

Goal:

Build a notification system using Twilio initially, while keeping the architecture provider-agnostic so you can later swap to:

AWS SNS
Plivo
Telnyx
Vonage

…without rewriting business logic.

This system is for:

transactional alerts
status updates
links/notifications
multi-tenant SaaS usage

NOT conversational messaging.

Core Architecture
Laravel Events
    ↓
Notification Listeners
    ↓
SmsNotificationService
    ↓
SmsProviderInterface
    ↓
TwilioProvider

Future:

SmsProviderInterface
    ├── TwilioProvider
    ├── AwsSnsProvider
    ├── PlivoProvider
Phase 1: Install Twilio
Install SDK
composer require twilio/sdk
Phase 2: Environment Variables
SMS_PROVIDER=twilio

TWILIO_ACCOUNT_SID=
TWILIO_AUTH_TOKEN=
TWILIO_PHONE_NUMBER=

Future providers can add:

AWS_ACCESS_KEY_ID=
PLIVO_AUTH_ID=
TELNYX_API_KEY=

without changing app logic.

Phase 3: Config File

Create:

config/sms.php
return [

    'default' => env('SMS_PROVIDER', 'twilio'),

    'providers' => [

        'twilio' => [
            'account_sid' => env('TWILIO_ACCOUNT_SID'),
            'auth_token' => env('TWILIO_AUTH_TOKEN'),
            'phone_number' => env('TWILIO_PHONE_NUMBER'),
        ],

    ],

];
Phase 4: Database Schema
Tenant SMS preferences (implemented)

This app uses **Stancl tenancy**: tenant migrations run on each tenant database. SMS toggles live on the single `account_settings` row (`AccountSettings::getCurrent()`), not a separate `tenant_sms_settings` table and **not** a `foreignId('tenant_id')->constrained('tenants')` (the `tenants` table is not present on tenant DB connections).

Add columns on `account_settings` (see `database/migrations/tenant/*_add_sms_notification_settings_to_account_settings_table.php`):

- `sms_enabled` — master switch for SMS
- `sandbox_mode` — global testing mode (UI banner + routing customer emails and SMS to the signed-in user are implemented separately). Toggle lives under **General Account Settings** on `/account` (`account.update`), not on `/account/notifications/sms`.

Per-category toggles live in **`sms_notification_preferences`** (one row per `account_settings`, `account_settings_id` FK). Boolean columns follow `notify_{enumValue}` (e.g. `notify_invoice`, `notify_estimate`, `notify_delivery`). Categories are defined by the backed enum `App\Enums\SMS` (currently estimate, invoice, delivery; more can be added later).

Listeners and `SmsService` should use `AccountSettings::getCurrent()->wantsSms(App\Enums\SMS::Invoice)` (or another `SMS` case / matching string value).
SMS Message Logs (planned)
php artisan make:migration create_sms_messages_table
Schema::create('sms_messages', function (Blueprint $table) {
    $table->id();

    $table->foreignId('tenant_id')->nullable()->constrained();

    $table->string('provider')->nullable();

    $table->string('to');
    $table->string('from');

    $table->text('message');

    $table->string('provider_message_id')->nullable();

    $table->string('status')->default('pending');

    $table->timestamp('sent_at')->nullable();
    $table->timestamp('delivered_at')->nullable();
    $table->timestamp('failed_at')->nullable();

    $table->text('error')->nullable();

    $table->timestamps();
});
Phase 5: Contracts

Create:

app/Services/SMS/Contracts/
SmsProviderInterface
namespace App\Services\SMS\Contracts;

use App\Services\SMS\Data\SmsResult;

interface SmsProviderInterface
{
    public function send(
        string $to,
        string $message,
        ?string $from = null,
    ): SmsResult;
}
Phase 6: Result DTO

Create:

app/Services/SMS/Data/SmsResult.php
namespace App\Services\SMS\Data;

class SmsResult
{
    public function __construct(
        public bool $success,
        public ?string $providerMessageId = null,
        public ?string $status = null,
        public ?string $error = null,
    ) {}
}
Phase 7: Twilio Provider

Create:

app/Services/SMS/Providers/TwilioProvider.php

Responsibilities:

Initialize Twilio SDK
Send messages
Normalize response into SmsResult
Catch/log exceptions
Example Structure
class TwilioProvider implements SmsProviderInterface
{
    public function send(
        string $to,
        string $message,
        ?string $from = null,
    ): SmsResult {
        // Send via Twilio
    }
}
Phase 8: Provider Factory

Create:

app/Services/SMS/SmsProviderFactory.php

Purpose:

Determine active provider
Return correct implementation
Example
class SmsProviderFactory
{
    public static function make(): SmsProviderInterface
    {
        return match (config('sms.default')) {
            'twilio' => app(TwilioProvider::class),

            default => throw new Exception('Unsupported SMS provider'),
        };
    }
}
Phase 9: Main SMS Service

Create:

app/Services/SMS/SmsService.php

This is the ONLY class the app should use directly.

Responsibilities
Resolve provider
Queue sends
Log messages
Validate tenant settings
Normalize formatting
Dispatch jobs
Example Usage
SmsService::send(
    tenant: $tenant,
    to: $customer->phone,
    message: $message,
);
Phase 10: Queue System

Create:

php artisan make:job SendSmsJob

Never send SMS synchronously.

Recommended:

Redis
Horizon
Phase 11: Event-Driven Notifications

Recommended events:

DeliveryStatusUpdated
InvoiceSent
InvoicePaid
QuoteReady
AppointmentReminder

Listeners:

check tenant SMS settings
dispatch SendSmsJob
Phase 12: Message Templates

Create:

app/Services/SMS/Templates/

Examples:

InvoiceSentTemplate.php
DeliveryEnRouteTemplate.php
Example
class InvoiceSentTemplate
{
    public function render(Invoice $invoice): string
    {
        return "Your invoice #{$invoice->number} is ready: {$invoice->url}";
    }
}
Phase 13: Settings UI (implemented)

Inertia/Vue page under the tenant **Account** area (distinct from `/notifications`, which is in-app notification records).

Routes (see `routes/tenant.php`, `account` prefix):

- `GET /account/notifications/sms` — `account.notifications.sms.index`
- `PATCH /account/notifications/sms` — `account.notifications.sms.update`

Controller: `App\Http\Controllers\Tenant\AccountSmsNotificationsController`

Vue: `resources/js/Pages/Tenant/Account/SmsNotifications.vue`

Entry point: Account hub cards include **Text notifications**; optional inline link from `Account/Index.vue` under service ticket notification copy.

Payload: `sms_enabled` and `preferences` (object keyed by `SMS` enum string values, e.g. `preferences.invoice`). Sandbox is saved with the main account form, not this endpoint.
Phase 14: Logging & Monitoring

Log:

provider
message SID
failures
timestamps
delivery status

Future providers should populate the same schema.

Phase 15: Twilio Setup

Inside Twilio Console:

Configure:
Messaging-enabled number
A2P 10DLC registration
Transactional campaign
Status callback webhook
Recommended Folder Structure
app/
└── Services/
    └── SMS/
        ├── Contracts/
        │   └── SmsProviderInterface.php
        │
        ├── Data/
        │   └── SmsResult.php
        │
        ├── Providers/
        │   └── TwilioProvider.php
        │
        ├── Templates/
        │   ├── InvoiceSentTemplate.php
        │   └── DeliveryEnRouteTemplate.php
        │
        ├── SmsProviderFactory.php
        └── SmsService.php