<?php

namespace App\Domain\Payment\Models;

use App\Models\AccountSettings;
use App\Models\PaymentAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentConfiguration extends Model
{
    protected $table = 'payments_configurations';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'stripe_charges_enabled' => 'boolean',
        'stripe_payouts_enabled' => 'boolean',
        'meta' => 'array',
        'qbo_token_expires_at' => 'datetime',
        // Sensitive provider secrets — Laravel transparently encrypts on write/decrypts on read.
        'stripe_secret_key_enc' => 'encrypted',
        'qbo_access_token_enc' => 'encrypted',
        'qbo_refresh_token_enc' => 'encrypted',
    ];

    public function accountSettings(): BelongsTo
    {
        return $this->belongsTo(AccountSettings::class, 'account_settings_id');
    }

    public function processorPaymentMethods(): HasMany
    {
        return $this->hasMany(ProcessorPaymentMethod::class, 'configuration_id');
    }

    /**
     * Resolver for the active customer-payment configuration for the current account.
     * Today this is Stripe-only; when QuickBooks (or multi-processor) ships, resolve using
     * account_settings.payment_provider or is_default.
     */
    public static function forCurrentAccount(?AccountSettings $settings = null): self
    {
        return static::forStripe($settings);
    }

    /**
     * Whether this account has started (or completed) Stripe Connect for customer payments.
     * Used with {@see quickbooksConnected()} to enforce a single active processor.
     */
    public static function stripeConnectClaimed(?AccountSettings $settings = null): bool
    {
        $settings = $settings ?? AccountSettings::getCurrent();

        return (bool) static::forStripe($settings)->stripe_account_id;
    }

    /**
     * Default Stripe Connect configuration for the current tenant account settings row.
     * Creates the row (and pivot rows) when missing; optionally hydrates from legacy payment_accounts.
     */
    public static function forStripe(?AccountSettings $settings = null): self
    {
        $settings = $settings ?? AccountSettings::getCurrent();

        $config = static::query()
            ->where('account_settings_id', $settings->id)
            ->where('processor', 'stripe')
            ->first();

        if ($config !== null) {
            return $config;
        }

        $legacy = PaymentAccount::query()
            ->where('account_settings_id', $settings->id)
            ->where('provider', 'stripe')
            ->first();

        $config = static::create([
            'account_settings_id' => $settings->id,
            'processor' => 'stripe',
            'label' => 'Stripe',
            'is_active' => true,
            'is_default' => true,
            'stripe_account_id' => $legacy?->external_account_id,
            'stripe_charges_enabled' => (bool) ($legacy?->charges_enabled),
            'stripe_payouts_enabled' => (bool) ($legacy?->payouts_enabled),
            'meta' => $legacy
                ? array_merge($legacy->data ?? [], ['migrated_from_payment_accounts' => true])
                : [],
        ]);

        $config->ensureProcessorPaymentMethods();

        return $config;
    }

    /**
     * Default QuickBooks Online configuration for the current tenant account settings row.
     * Creates the row (and pivot rows) when missing. Does not contact Intuit; OAuth state lives
     * on the row after a successful connect via {@see \App\Services\Payments\QuickBooksOAuthService}.
     */
    public static function forQuickbooks(?AccountSettings $settings = null): self
    {
        $settings = $settings ?? AccountSettings::getCurrent();

        $config = static::query()
            ->where('account_settings_id', $settings->id)
            ->where('processor', 'quickbooks')
            ->first();

        if ($config !== null) {
            return $config;
        }

        $config = static::create([
            'account_settings_id' => $settings->id,
            'processor' => 'quickbooks',
            'label' => 'QuickBooks Online',
            'is_active' => true,
            'is_default' => false,
            'meta' => [],
        ]);

        $config->ensureProcessorPaymentMethods();

        return $config;
    }

    /**
     * QuickBooks Online OAuth has been completed (we hold a refresh token + realm id).
     */
    public function quickbooksConnected(): bool
    {
        return ! empty($this->qbo_realm_id) && ! empty($this->qbo_refresh_token_enc);
    }

    public function ensureProcessorPaymentMethods(): void
    {
        $codes = PaymentMethodConfig::query()->orderBy('position')->pluck('code');
        foreach ($codes as $code) {
            ProcessorPaymentMethod::query()->firstOrCreate(
                [
                    'configuration_id' => $this->id,
                    'payment_method_code' => $code,
                ],
                [
                    'is_enabled' => in_array($code, ['credit_card', 'ach', 'wire'], true),
                ],
            );
        }
    }

    /**
     * Payment methods enabled for this tenant’s Stripe configuration (for invoice UI / validation).
     *
     * @return array<int, array{code: string, label: string}>
     */
    public static function enabledStripeMethodOptionsForCurrentAccount(?AccountSettings $settings = null): array
    {
        $config = static::forStripe($settings);
        $config->ensureProcessorPaymentMethods();

        return $config->processorPaymentMethods()
            ->where('is_enabled', true)
            ->with('methodConfig')
            ->orderBy('payment_method_code')
            ->get()
            ->map(fn (ProcessorPaymentMethod $p) => [
                'code' => $p->payment_method_code,
                'label' => $p->methodConfig?->label ?? $p->payment_method_code,
            ])
            ->values()
            ->all();
    }

    public function stripeReadyForCharges(): bool
    {
        if (! $this->stripe_account_id || ! $this->is_active || ! $this->stripe_charges_enabled) {
            return false;
        }

        $card = self::normalizeStripeCapabilityStatus($this->meta['stripe_capability_card_payments'] ?? null);

        // Charges and Checkout on a connected account require `card_payments` to be active.
        return $card === 'active';
    }

    private static function normalizeStripeCapabilityStatus(mixed $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        if (is_string($raw)) {
            return $raw;
        }
        if (is_array($raw) && isset($raw['status']) && is_string($raw['status'])) {
            return $raw['status'];
        }
        if (is_object($raw)) {
            $decoded = json_decode(json_encode($raw), true);

            return is_array($decoded) ? self::normalizeStripeCapabilityStatus($decoded) : null;
        }

        return null;
    }

    public function stripeCardPaymentsCapability(): ?string
    {
        return self::normalizeStripeCapabilityStatus($this->meta['stripe_capability_card_payments'] ?? null);
    }

    public function stripeTransfersCapability(): ?string
    {
        return self::normalizeStripeCapabilityStatus($this->meta['stripe_capability_transfers'] ?? null);
    }

    /**
     * User-facing explanation when Connect exists but {@see stripeReadyForCharges()} is false.
     */
    public function stripeSetupHint(): ?string
    {
        if (! $this->stripe_account_id) {
            return null;
        }
        if ($this->stripeReadyForCharges()) {
            return null;
        }

        $card = $this->stripeCardPaymentsCapability();

        return match ($card) {
            'pending' => 'Stripe is still activating card payments (often within minutes after you finish onboarding). Check back shortly or click Continue setup if Stripe asks for more details.',
            'inactive' => 'Card payments are not active yet. Open “Continue setup” and complete Stripe’s requirements (business details, identity, bank account).',
            default => $this->stripe_charges_enabled
                ? 'Open “Continue setup” or your Stripe Dashboard to finish anything still required before customers can pay by card.'
                : 'Finish Stripe onboarding so card payments and payouts can be enabled.',
        };
    }
}
