<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\ServiceItem\BillingType;
use App\Jobs\PullServiceItemsFromQuickBooks;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class PullServiceItemsFromQuickBooksTest extends TestCase
{
    #[Test]
    public function map_row_sets_quickbooks_item_id_and_flat_billing_by_default(): void
    {
        $job = new PullServiceItemsFromQuickBooks(1);
        $method = new ReflectionMethod(PullServiceItemsFromQuickBooks::class, 'mapRowToPayload');
        $method->setAccessible(true);

        $payload = $method->invoke($job, [
            'Description' => 'Weekly service',
            'Sku' => 'GARDEN-01',
            'UnitPrice' => 35,
            'PurchaseCost' => 10,
            'Taxable' => true,
            'IncomeAccountRef' => ['name' => 'Landscaping Services', 'value' => '45'],
            'PurchaseDesc' => 'Vendor labor',
        ], '42', 'Gardening');

        $this->assertSame('Gardening', $payload['display_name']);
        $this->assertSame('GARDEN-01', $payload['code']);
        $this->assertSame('Weekly service', $payload['description']);
        $this->assertSame('42', $payload['quickbooks_item_id']);
        $this->assertSame(BillingType::Flat->value, $payload['billing_type']);
        $this->assertSame(35.0, $payload['default_rate']);
        $this->assertSame(10.0, $payload['default_cost']);
        $this->assertTrue($payload['taxable']);
        $this->assertSame('Landscaping Services', $payload['attributes']['quickbooks']['income_account']);
        $this->assertSame('45', $payload['attributes']['quickbooks']['income_account_id']);
        $this->assertStringContainsString('Vendor labor', (string) $payload['notes']);
    }

    #[Test]
    public function map_row_uses_selected_default_billing_type(): void
    {
        $job = new PullServiceItemsFromQuickBooks(1, BillingType::Hourly->value);
        $method = new ReflectionMethod(PullServiceItemsFromQuickBooks::class, 'mapRowToPayload');
        $method->setAccessible(true);

        $payload = $method->invoke($job, [
            'UnitPrice' => 75,
        ], '7', 'Labor Hours');

        $this->assertSame(BillingType::Hourly->value, $payload['billing_type']);
    }
}
