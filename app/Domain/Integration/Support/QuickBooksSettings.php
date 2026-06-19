<?php

declare(strict_types=1);

namespace App\Domain\Integration\Support;

use App\Domain\Integration\Models\Integration;
use App\Enums\Integration\IntegrationType;

final class QuickBooksSettings
{
    public function __construct(
        public bool $syncContacts = false,
        public bool $syncInvoices = false,
        public bool $syncPayments = false,
        public bool $syncBills = false,
        public bool $syncBillPayments = false,
        public ?string $defaultItemId = null,
    ) {}

    public static function from(?Integration $integration): self
    {
        if ($integration === null) {
            return new self;
        }

        $settings = is_array($integration->settings) ? $integration->settings : [];

        return new self(
            syncContacts: (bool) ($settings['sync_contacts'] ?? false),
            syncInvoices: (bool) ($settings['sync_invoices'] ?? false),
            syncPayments: (bool) ($settings['sync_payments'] ?? false),
            syncBills: (bool) ($settings['sync_bills'] ?? false),
            syncBillPayments: (bool) ($settings['sync_bill_payments'] ?? false),
            defaultItemId: isset($settings['default_item_id']) ? (string) $settings['default_item_id'] : null,
        );
    }

    public static function forCurrentTenant(): self
    {
        $integration = Integration::query()
            ->where('integration_type', IntegrationType::QuickBooks)
            ->first();

        return self::from($integration);
    }

    public function isSyncContactsEnabled(): bool
    {
        return $this->syncContacts;
    }

    public function isSyncInvoicesEnabled(): bool
    {
        return $this->syncInvoices;
    }

    public function isSyncPaymentsEnabled(): bool
    {
        return $this->syncPayments;
    }

    public function isSyncBillsEnabled(): bool
    {
        return $this->syncBills;
    }

    public function isSyncBillPaymentsEnabled(): bool
    {
        return $this->syncBillPayments;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $out = [
            'sync_contacts' => $this->syncContacts,
            'sync_invoices' => $this->syncInvoices,
            'sync_payments' => $this->syncPayments,
            'sync_bills' => $this->syncBills,
            'sync_bill_payments' => $this->syncBillPayments,
        ];

        if ($this->defaultItemId !== null && $this->defaultItemId !== '') {
            $out['default_item_id'] = $this->defaultItemId;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function mergeIntoIntegrationSettings(Integration $integration, array $validated): void
    {
        $existing = is_array($integration->settings) ? $integration->settings : [];
        $current = self::from($integration);

        $integration->update([
            'settings' => array_merge($existing, [
                'sync_contacts' => (bool) ($validated['sync_contacts'] ?? $current->syncContacts),
                'sync_invoices' => (bool) ($validated['sync_invoices'] ?? $current->syncInvoices),
                'sync_payments' => (bool) ($validated['sync_payments'] ?? $current->syncPayments),
                'sync_bills' => (bool) ($validated['sync_bills'] ?? $current->syncBills),
                'sync_bill_payments' => (bool) ($validated['sync_bill_payments'] ?? $current->syncBillPayments),
                'default_item_id' => $validated['default_item_id'] ?? $existing['default_item_id'] ?? $current->defaultItemId,
            ]),
        ]);
    }
}
