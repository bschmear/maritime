<?php

namespace Tests\Unit;

use App\Domain\Integration\Models\Integration;
use App\Domain\Integration\Support\QuickBooksSettings;
use App\Enums\Integration\IntegrationType;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class QuickBooksSettingsTest extends TestCase
{
    #[Test]
    public function defaults_are_false_when_integration_missing(): void
    {
        $settings = QuickBooksSettings::from(null);

        $this->assertFalse($settings->syncContacts);
        $this->assertFalse($settings->syncInvoices);
        $this->assertFalse($settings->syncPayments);
        $this->assertNull($settings->defaultItemId);
    }

    #[Test]
    public function reads_booleans_from_integration_settings(): void
    {
        $integration = new Integration([
            'integration_type' => IntegrationType::QuickBooks,
            'settings' => [
                'sync_contacts' => true,
                'sync_invoices' => true,
                'sync_payments' => false,
                'default_item_id' => '42',
            ],
        ]);

        $settings = QuickBooksSettings::from($integration);

        $this->assertTrue($settings->isSyncContactsEnabled());
        $this->assertTrue($settings->isSyncInvoicesEnabled());
        $this->assertFalse($settings->isSyncPaymentsEnabled());
        $this->assertSame('42', $settings->defaultItemId);
    }

    #[Test]
    public function to_array_includes_sync_flags(): void
    {
        $settings = new QuickBooksSettings(
            syncContacts: true,
            syncInvoices: false,
            syncPayments: true,
            defaultItemId: '99',
        );

        $this->assertSame([
            'sync_contacts' => true,
            'sync_invoices' => false,
            'sync_payments' => true,
            'default_item_id' => '99',
        ], $settings->toArray());
    }
}
