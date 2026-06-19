<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Vendor\Models\Vendor;
use App\Support\QuickBooks\QuickBooksBillMapper;
use App\Support\QuickBooks\QuickBooksBillPaymentMapper;
use App\Support\QuickBooks\QuickBooksVendorResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class QuickBooksVendorResolverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('vendors');
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('display_name');
            $table->string('company_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('secondary_email')->nullable();
            $table->unsignedBigInteger('primary_contact_id')->nullable();
            $table->string('quickbooks_id', 64)->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('vendors');

        parent::tearDown();
    }

    #[Test]
    public function it_resolves_vendor_by_quickbooks_id(): void
    {
        $vendor = Vendor::query()->create([
            'display_name' => 'Acme Supplies',
            'quickbooks_id' => '36',
        ]);

        $resolved = QuickBooksVendorResolver::resolveLocalVendorId([
            'value' => '36',
            'name' => 'Acme Supplies',
        ]);

        $this->assertSame($vendor->id, $resolved);
    }

    #[Test]
    public function it_falls_back_to_display_name_when_quickbooks_id_not_linked(): void
    {
        $vendor = Vendor::query()->create([
            'display_name' => 'Robertson & Associates',
            'quickbooks_id' => null,
        ]);

        $resolved = QuickBooksVendorResolver::resolveLocalVendorId([
            'value' => '99',
            'name' => 'Robertson & Associates',
        ]);

        $this->assertSame($vendor->id, $resolved);
    }

    #[Test]
    public function it_resolves_vendor_by_email_from_qbo_vendor_row(): void
    {
        $vendor = Vendor::query()->create([
            'display_name' => 'Unlinked Vendor',
            'contact_email' => 'billing@acme.example',
            'quickbooks_id' => null,
        ]);

        $resolved = QuickBooksVendorResolver::resolveLocalVendorId(
            ['value' => '77', 'name' => 'Different QBO Name'],
            [
                'Id' => '77',
                'DisplayName' => 'Different QBO Name',
                'PrimaryEmailAddr' => ['Address' => 'billing@acme.example'],
            ],
        );

        $this->assertSame($vendor->id, $resolved);
    }

    #[Test]
    public function it_resolves_vendor_by_secondary_email(): void
    {
        $vendor = Vendor::query()->create([
            'display_name' => 'Email Vendor',
            'secondary_email' => 'ap@vendor.test',
        ]);

        $resolved = QuickBooksVendorResolver::resolveLocalVendorIdByEmail('ap@vendor.test');

        $this->assertSame($vendor->id, $resolved);
    }

    #[Test]
    public function bill_mapper_sets_vendor_id_and_quickbooks_vendor_id(): void
    {
        $vendor = Vendor::query()->create([
            'display_name' => 'Diego',
            'quickbooks_id' => '36',
        ]);

        $payload = QuickBooksBillMapper::mapBillRow([
            'Id' => '501',
            'VendorRef' => ['value' => '36', 'name' => 'Diego'],
            'TotalAmt' => 100,
            'Balance' => 100,
        ]);

        $this->assertSame($vendor->id, $payload['vendor_id']);
        $this->assertSame('36', $payload['quickbooks_vendor_id']);
    }

    #[Test]
    public function bill_payment_mapper_sets_vendor_id_and_quickbooks_vendor_id(): void
    {
        $vendor = Vendor::query()->create([
            'display_name' => 'Diego',
            'quickbooks_id' => '36',
        ]);

        $payload = QuickBooksBillPaymentMapper::mapBillPaymentRow([
            'Id' => '901',
            'VendorRef' => ['value' => '36', 'name' => 'Diego'],
            'TotalAmt' => 50,
            'Line' => [],
        ]);

        $this->assertSame($vendor->id, $payload['vendor_id']);
        $this->assertSame('36', $payload['quickbooks_vendor_id']);
    }

    #[Test]
    public function it_backfills_quickbooks_id_on_matched_vendor(): void
    {
        $vendor = Vendor::query()->create([
            'display_name' => 'Email Match',
            'contact_email' => 'pay@match.test',
        ]);

        QuickBooksVendorResolver::backfillQuickbooksIdOnVendor($vendor->id, '88');

        $this->assertSame('88', $vendor->fresh()->quickbooks_id);
    }
}
