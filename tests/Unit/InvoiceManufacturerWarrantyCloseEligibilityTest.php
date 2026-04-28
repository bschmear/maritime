<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Invoice\Models\Invoice;
use App\Domain\InvoiceItem\Models\InvoiceItem;
use App\Domain\WarrantyClaim\Models\WarrantyClaim;
use App\Domain\WarrantyClaim\Support\AssertInvoiceManufacturerWarrantyClaimsAllowClose;
use App\Domain\WarrantyClaim\Support\InvoiceManufacturerWarrantyCloseEligibility;
use App\Enums\WarrantyClaim\Status;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InvoiceManufacturerWarrantyCloseEligibilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('warranty_claim_line_items');
        Schema::dropIfExists('warrantyclaims');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('work_order_id')->nullable();
            $table->string('status', 20)->default('sent');
            $table->decimal('total', 12, 2)->default(100);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->decimal('amount_due', 12, 2)->default(100);
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->string('name')->default('Item');
            $table->text('description')->nullable();
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('cost', 10, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->boolean('is_warranty')->default(false);
            $table->string('warranty_type')->nullable();
            $table->string('billable_to', 32)->default('customer');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->boolean('taxable')->default(false);
            $table->decimal('tax_rate', 6, 3)->nullable();
            $table->decimal('tax_amount', 12, 2)->nullable();
            $table->decimal('total', 12, 2)->default(0);
            $table->integer('position')->default(0);
            $table->timestamps();
        });

        Schema::create('warrantyclaims', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->unsignedBigInteger('work_order_id')->nullable();
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->string('claim_number')->nullable();
            $table->string('status', 32)->default('draft');
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('warranty_claim_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warranty_claim_id')->constrained('warrantyclaims')->cascadeOnDelete();
            $table->unsignedBigInteger('work_order_service_item_id')->nullable();
            $table->string('description');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('price', 10, 2);
            $table->decimal('cost', 10, 2)->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('warranty_claim_line_items');
        Schema::dropIfExists('warrantyclaims');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');

        parent::tearDown();
    }

    public function test_no_manufacturer_warranty_items_allows_close(): void
    {
        $invoice = $this->makeInvoice();

        $eligibility = new InvoiceManufacturerWarrantyCloseEligibility;
        $this->assertTrue($eligibility->isAllowed($invoice));

        $assert = new AssertInvoiceManufacturerWarrantyClaimsAllowClose($eligibility);
        $assert($invoice, 'status');
    }

    public function test_manufacturer_warranty_without_claim_blocks_assert(): void
    {
        $invoice = $this->makeInvoice();
        $this->addManufacturerWarrantyLine($invoice->id);

        $eligibility = new InvoiceManufacturerWarrantyCloseEligibility;
        $this->assertFalse($eligibility->isAllowed($invoice));

        $assert = new AssertInvoiceManufacturerWarrantyClaimsAllowClose($eligibility);
        $this->expectException(ValidationException::class);
        $assert($invoice, 'status');
    }

    public function test_open_claim_blocks_when_manufacturer_warranty_present(): void
    {
        $invoice = $this->makeInvoice();
        $this->addManufacturerWarrantyLine($invoice->id);

        WarrantyClaim::query()->create([
            'invoice_id' => $invoice->id,
            'status' => Status::Draft->value,
            'total_amount' => 0,
        ]);

        $eligibility = new InvoiceManufacturerWarrantyCloseEligibility;
        $this->assertFalse($eligibility->isAllowed($invoice));
    }

    public function test_paid_claim_allows_apply_payment_and_close(): void
    {
        $invoice = $this->makeInvoice();
        $this->addManufacturerWarrantyLine($invoice->id);

        WarrantyClaim::query()->create([
            'invoice_id' => $invoice->id,
            'status' => Status::Paid->value,
            'total_amount' => 10,
            'paid_at' => now(),
        ]);

        $eligibility = new InvoiceManufacturerWarrantyCloseEligibility;
        $this->assertTrue($eligibility->isAllowed($invoice));

        $invoice->refresh();
        $invoice->applyPayment(100);

        $this->assertSame('paid', $invoice->fresh()->status);
    }

    public function test_voided_claim_allows_close(): void
    {
        $invoice = $this->makeInvoice();
        $this->addManufacturerWarrantyLine($invoice->id);

        WarrantyClaim::query()->create([
            'invoice_id' => $invoice->id,
            'status' => Status::Voided->value,
            'total_amount' => 0,
            'voided_at' => now(),
        ]);

        $eligibility = new InvoiceManufacturerWarrantyCloseEligibility;
        $this->assertTrue($eligibility->isAllowed($invoice));
    }

    public function test_work_order_fallback_links_claim_without_invoice_id(): void
    {
        $invoice = $this->makeInvoice();
        DB::table('invoices')->where('id', $invoice->id)->update(['work_order_id' => 42]);

        $invoice = Invoice::query()->findOrFail($invoice->id);
        $this->addManufacturerWarrantyLine($invoice->id);

        WarrantyClaim::query()->create([
            'invoice_id' => null,
            'work_order_id' => 42,
            'status' => Status::Paid->value,
            'total_amount' => 5,
            'paid_at' => now(),
        ]);

        $eligibility = new InvoiceManufacturerWarrantyCloseEligibility;
        $this->assertTrue($eligibility->isAllowed($invoice));
    }

    private function makeInvoice(): Invoice
    {
        $id = DB::table('invoices')->insertGetId([
            'work_order_id' => null,
            'status' => 'sent',
            'total' => 100,
            'amount_paid' => 0,
            'amount_due' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Invoice::query()->findOrFail($id);
    }

    private function addManufacturerWarrantyLine(int $invoiceId): void
    {
        InvoiceItem::query()->create([
            'invoice_id' => $invoiceId,
            'name' => 'Labor',
            'description' => 'Test',
            'quantity' => 1,
            'unit_price' => 100,
            'cost' => 0,
            'discount' => 0,
            'is_warranty' => true,
            'warranty_type' => 'manufacturer',
            'billable_to' => 'manufacturer',
            'subtotal' => 100,
            'taxable' => false,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'total' => 100,
            'position' => 0,
        ]);
    }
}
