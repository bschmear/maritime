# Laravel Cashier Setup Guide

This application uses Laravel Cashier for subscription management with Stripe.

## Setup Steps

### 1. Install Dependencies (Already Done)
```bash
composer require laravel/cashier
```

### 2. Run Migrations
```bash
php artisan migrate
```

This will create:
- `plans` table - stores your subscription plans
- `plan_items` table - stores add-ons for plans
- `accounts` table - stores customer accounts
- Cashier tables - `subscriptions`, `subscription_items`, etc.

### 3. Configure Stripe

#### A. Get Stripe API Keys
1. Go to https://dashboard.stripe.com/
2. Get your **Publishable Key** and **Secret Key**
3. Add them to your `.env` file:

```env
STRIPE_KEY=pk_test_your_publishable_key
STRIPE_SECRET=sk_test_your_secret_key
```

#### B. Create Products and Prices in Stripe
1. Go to Stripe Dashboard → Products
2. Create 3 products: Basic, Pro, Agency
3. For each product, create 2 prices:
   - Monthly recurring price
   - Yearly recurring price
4. Copy the Price IDs (they look like `price_xxxxx`)
5. Add them to your `.env`:

```env
# Basic Plan
STRIPE_BASIC_MONTHLY_PRICE_ID=price_xxxxx
STRIPE_BASIC_YEARLY_PRICE_ID=price_xxxxx

# Pro Plan
STRIPE_PRO_MONTHLY_PRICE_ID=price_xxxxx
STRIPE_PRO_YEARLY_PRICE_ID=price_xxxxx

# Agency Plan
STRIPE_AGENCY_MONTHLY_PRICE_ID=price_xxxxx
STRIPE_AGENCY_YEARLY_PRICE_ID=price_xxxxx
```

### 4. Configure Webhooks (Production)

Helmful uses a **platform Connect webhook** on the central app domain (one URL for all tenants):

1. Stripe Dashboard → **Developers → Webhooks → Add endpoint**
2. URL: `https://your-app-domain/stripe/connect-webhook` (e.g. `https://app.helmful.com/stripe/connect-webhook`)
3. Enable **Listen to events on Connected accounts** (Connect)
4. Events (minimum): `account.updated`, `checkout.session.completed`, `checkout.session.async_payment_succeeded`, `checkout.session.async_payment_failed`, `checkout.session.expired`
5. Open the endpoint → **Signing secret** → Reveal → copy the `whsec_…` value
6. Set on production (Forge / Vapor / etc.) — **not** the endpoint URL:

```env
STRIPE_WEBHOOK=https://app.helmful.com/stripe/connect-webhook
STRIPE_CONNECT_WEBHOOK_SECRET=whsec_xxxxx
# Legacy alias (optional):
# STRIPE_WEBHOOK_SECRET=whsec_xxxxx
```

After changing secrets, run `php artisan config:clear` or redeploy so `config:cache` picks up the new value.

**Common mistake:** putting the webhook URL in `STRIPE_WEBHOOK_SECRET` or using the `stripe listen` CLI secret in production. The secret must come from the **same** Dashboard endpoint URL you registered.

Optional per-tenant endpoint: `POST https://{tenant}.yourdomain.com/stripe/webhook` (same signing secret and handler; prefer the central Connect URL).

### 5. Test Webhooks Locally

For local development, use Stripe CLI:

```bash
# Install Stripe CLI
brew install stripe/stripe-cli/stripe

# Login
stripe login

# Forward webhooks to local server
stripe listen --forward-to localhost:8000/stripe/webhook
```

## Usage Flow

### Customer Journey

1. **Browse Plans** - Customer visits homepage and sees pricing
2. **Select Plan** - Click on a plan → redirected to `/pricing` with plan pre-selected
3. **Review Cart** - `/checkout/cart` - review plan, add extras
4. **Checkout** - `/checkout` - enter payment details
5. **Success** - Account created, subscription started, redirected to dashboard

### Account Creation

When a successful checkout occurs:
1. A Stripe customer is created for the user
2. A subscription is created with the selected plan (14-day trial)
3. **Only after successful payment**, an `Account` is created with the user as owner
4. The user is attached to the account with 'owner' role
5. User is redirected to dashboard

### Key Models

- **User** - Uses `Billable` trait, stores Stripe customer info and subscriptions
- **Account** - Workspace/organization, created after successful payment
- **Plan** - Stores plan details and Stripe price IDs
- **PlanItem** - Add-ons for plans (extra seats, features, etc.)

### Routes

- `GET /pricing` - Plan selection page (guest)
- `GET /checkout/cart` - Cart/review page (auth)
- `GET /checkout` - Checkout form (auth)
- `POST /checkout` - Process payment (auth)

## Testing

Use Stripe test cards:
- Success: `4242 4242 4242 4242`
- Decline: `4000 0000 0000 0002`
- 3D Secure: `4000 0025 0000 3155`

Any future expiry date and any 3-digit CVC.

## Important Notes

1. **Billable Model**: The `User` model uses the `Billable` trait (subscriptions belong to users)
2. **Account Creation**: Accounts are only created AFTER successful payment
3. **Free Trial**: All subscriptions include a 14-day free trial
4. **Seat Limits**: Plans have seat limits stored in the `seat_limit` column
5. **Add-ons**: Use `PlanItem` model for additional purchasable items
6. **Multiple Accounts**: Users can own multiple accounts or be members of multiple accounts
7. **Checkout timeouts**: New workspaces run tenant DB creation and migrations during `POST /checkout`. Set `CHECKOUT_PROCESS_MAX_EXECUTION_SECONDS=300` (default) and ensure your web server allows long requests (e.g. nginx `fastcgi_read_timeout 300;`). If users see 504 errors after Stripe succeeds, raise both values.

## Next Steps

- [ ] Set up Stripe account and get API keys
- [ ] Create products and prices in Stripe dashboard
- [ ] Update .env with Stripe credentials
- [ ] Run migrations
- [ ] Seed plans
- [ ] Test checkout flow
- [ ] Set up webhooks for production

