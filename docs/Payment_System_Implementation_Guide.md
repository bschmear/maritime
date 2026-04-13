# Payment System Implementation Guide

## Overview

We are implementing a **multi-tenant payment system** that supports multiple providers, with:

- Stripe (default, primary)
- QuickBooks (secondary, optional)
- Future providers (extensible)

The system is designed to:
- Allow each tenant to connect their own payment account
- Route payments directly to the tenant (not the platform)
- Support switching providers without schema changes
- Keep payment logic abstracted and scalable

---

## Two different Stripe uses (do not conflate)

| Context | Where it lives | Purpose |
|--------|----------------|---------|
| **Platform / SaaS billing** | Central app, Laravel Cashier, `STRIPE_KEY` / `STRIPE_SECRET` | Bill tenants for Maritime **subscriptions** |
| **Tenant customer payments** | Each **tenant database**, Stripe **Connect** Express | Tenants collect money from **their customers** (e.g. invoices) |

Tenant payment tables (`payments_configurations`, `payments`, etc.) and [`PaymentConfiguration`](app/Domain/Payment/Models/PaymentConfiguration.php) are **only** for the second row. They must **not** store or replace platform subscription credentials.

Connect flows use the **same platform secret** in env to call Stripe’s API with the connected account id (`stripe_account` header / option); charges settle on the connected account.

---

## Current State

### 1. `account_settings`

- `payment_provider` (default: `stripe`) — which provider the tenant prefers for **customer** checkout (when implemented end-to-end).

### 2. `payments_configurations` (tenant DB) — **canonical for Connect**

Stores per–account-settings processor configuration:

- `account_settings_id` → links to the tenant’s `account_settings` row
- `processor` → `stripe` | `quickbooks`
- Stripe Connect: `stripe_account_id`, `stripe_charges_enabled`, `stripe_payouts_enabled`, optional `meta` (e.g. `details_submitted`, `email`, `connected_at`)
- `payment_methods_config` + `processor_payment_methods` — catalog of method codes and per-configuration toggles

UI: **Account → Payments** (`route('account.payments')`), [`PaymentConfigurationController`](app/Http/Controllers/Tenant/PaymentConfigurationController.php), Connect via [`StripeController`](app/Http/Controllers/Tenant/StripeController.php) + [`StripeService`](app/Services/Payments/StripeService.php). Resolve the active config with [`PaymentConfiguration::forCurrentAccount()`](app/Domain/Payment/Models/PaymentConfiguration.php) or `forStripe()` (Stripe-specific).

### 3. `payment_accounts` (legacy)

Older table used by early scaffolding. New Connect state should live in `payments_configurations`. [`PaymentConfiguration::forStripe()`](app/Domain/Payment/Models/PaymentConfiguration.php) can **hydrate from** a legacy `payment_accounts` row once when creating the new record. The tenant webhook still updates `PaymentAccount` for backwards compatibility until that table is removed.

### 4. Webhooks (`POST /stripe/webhook` on tenant host)

`account.updated` updates **`PaymentConfiguration`** (and legacy **`PaymentAccount`**) when `stripe_account_id` matches. **Stripe signature verification** should be added before production; Connect may use a dedicated endpoint or signing secret—document your chosen pattern in ops runbooks.

---

## System Architecture

### Core Principle

We are building a **Payment Provider Abstraction Layer**

Instead of:
- Hardcoding Stripe or QuickBooks

We use:
- A unified interface that delegates to provider-specific logic

---

## Next Steps

---

# 1. Create PaymentAccount Model

```php
app/Models/PaymentAccount.php
Responsibilities:

Represent a connected payment account

Provide helper scopes

Example:
class PaymentAccount extends Model
{
    protected $casts = [
        'data' => 'array',
        'charges_enabled' => 'boolean',
        'payouts_enabled' => 'boolean',
        'connected_at' => 'datetime',
    ];

    public function scopeProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
2. Create Payment Service Layer
app/Services/Payments/PaymentService.php
Purpose

Acts as the entry point for all payment operations

Example API
class PaymentService
{
    public function createCheckout($tenant, $amount, $type = 'full')
    {
        $provider = $tenant->accountSettings->payment_provider;

        return match ($provider) {
            'stripe' => app(StripeService::class)->createCheckout($tenant, $amount, $type),
            'quickbooks' => app(QuickBooksService::class)->createInvoice($tenant, $amount),
        };
    }
}
3. Implement Stripe (Primary Provider)

We are using Stripe Connect (Express).

3.1 Create Stripe Service
app/Services/Payments/StripeService.php
Responsibilities:

Create connected accounts

Generate onboarding links

Create checkout sessions

Handle Stripe-specific logic

3.2 Stripe Onboarding Flow
Step 1: Create Connected Account
\Stripe\Account::create([
    'type' => 'express',
]);
Step 2: Store in payment_accounts
PaymentAccount::create([
    'account_settings_id' => $settings->id,
    'provider' => 'stripe',
    'external_account_id' => $account->id,
    'data' => [
        'email' => $account->email,
    ],
]);
Step 3: Create Onboarding Link
\Stripe\AccountLink::create([
    'account' => $account->id,
    'refresh_url' => route('stripe.refresh'),
    'return_url' => route('stripe.return'),
    'type' => 'account_onboarding',
]);
3.3 After Onboarding

Fetch account details and update:

$account = \Stripe\Account::retrieve($accountId);

$paymentAccount->update([
    'charges_enabled' => $account->charges_enabled,
    'payouts_enabled' => $account->payouts_enabled,
    'data' => array_merge($paymentAccount->data ?? [], [
        'details_submitted' => $account->details_submitted,
    ]),
    'connected_at' => now(),
]);
4. Stripe Checkout Flow
Triggered After:

Estimate approved

Contract signed (optional depending on flow)

Create Checkout Session
\Stripe\Checkout\Session::create([
    'payment_method_types' => ['card'],
    'line_items' => [[
        'price_data' => [
            'currency' => 'usd',
            'product_data' => [
                'name' => 'Deposit',
            ],
            'unit_amount' => $amount * 100,
        ],
        'quantity' => 1,
    ]],
    'mode' => 'payment',
    'success_url' => '...',
    'cancel_url' => '...',
], [
    'stripe_account' => $connectedAccountId,
]);
5. Webhooks (Critical)

Create endpoint:

/routes/web.php or api.php
POST /stripe/webhook
Handle Events

checkout.session.completed

account.updated

Example
if ($event->type === 'account.updated') {
    $account = $event->data->object;

    PaymentAccount::where('external_account_id', $account->id)
        ->update([
            'charges_enabled' => $account->charges_enabled,
            'payouts_enabled' => $account->payouts_enabled,
        ]);
}
6. Payment Flow Integration
New Flow:

Estimate Approved

Contract Signed (optional)

Payment Required

Payment Created via PaymentService

Customer completes payment

Webhook confirms payment

System updates status

7. Future: QuickBooks Integration

Will use same structure:

provider = quickbooks

Store:

realm_id

tokens in data

Flow:

Create invoice in QuickBooks

Send payment link

Sync status

8. Important Rules
DO NOT:

Hardcode Stripe logic outside StripeService

Store provider data directly in account_settings

Assume onboarding = ready to charge

ALWAYS:

Check charges_enabled before allowing payment

Use payment_provider to determine flow

Use payment_accounts as source of truth

9. Summary

We are building:

A provider-agnostic payment system

With Stripe as default

Using Stripe Connect (Express) for payouts

Designed to support multiple providers without refactoring

Next Immediate Tasks

Create PaymentAccount model

Create PaymentService

Implement StripeService

Build Stripe onboarding flow

Add Stripe webhook handler

Add payment UI (Pay button)
