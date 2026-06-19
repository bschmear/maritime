<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Bill\Models\Bill;
use App\Domain\BillItem\Models\BillItem;
use App\Domain\BillPayment\Models\BillPayment;
use App\Domain\BillPayment\Models\BillPaymentLine;
use App\Domain\ChartOfAccount\Models\ChartOfAccount;
use App\Services\Payments\QuickBooksAccountingService;
use App\Support\QuickBooks\QuickBooksBillMapper;
use App\Support\QuickBooks\QuickBooksBillPaymentMapper;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class QuickBooksBillPayloadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('chart_of_accounts')) {
            Schema::create('chart_of_accounts', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('quickbooks_account_id', 64)->nullable()->unique();
                $table->string('fully_qualified_name')->nullable();
                $table->string('account_type')->nullable();
                $table->string('detail_type')->nullable();
                $table->boolean('active')->default(true);
                $table->foreignId('parent_id')->nullable();
                $table->timestamps();
            });
        }
    }

    #[Test]
    public function map_line_rows_map_account_ref_to_chart_of_account_fields(): void
    {
        $method = new ReflectionMethod(QuickBooksBillMapper::class, 'mapLineRows');
        $method->setAccessible(true);

        $lines = $method->invoke(null, [
            [
                'Id' => '1',
                'Amount' => 125.5,
                'Description' => 'Parts',
                'DetailType' => 'AccountBasedExpenseLineDetail',
                'AccountBasedExpenseLineDetail' => [
                    'AccountRef' => ['value' => '7', 'name' => 'Job Expenses:Job Materials'],
                ],
            ],
        ]);

        $this->assertCount(1, $lines);
        $this->assertSame('7', $lines[0]['expense_account_ref_id']);
        $this->assertSame('Job Expenses:Job Materials', $lines[0]['expense_account_ref_name']);
        $this->assertNull($lines[0]['chart_of_account_id']);
    }

    #[Test]
    public function map_line_rows_links_chart_of_account_when_account_exists(): void
    {
        ChartOfAccount::query()->create([
            'name' => 'Job Materials',
            'quickbooks_account_id' => '71',
            'fully_qualified_name' => 'Job Expenses:Job Materials',
            'account_type' => 'Expense',
            'detail_type' => 'SuppliesMaterials',
            'active' => true,
        ]);

        $method = new ReflectionMethod(QuickBooksBillMapper::class, 'mapLineRows');
        $method->setAccessible(true);

        $lines = $method->invoke(null, [
            [
                'Id' => '1',
                'Amount' => 50,
                'DetailType' => 'AccountBasedExpenseLineDetail',
                'AccountBasedExpenseLineDetail' => [
                    'AccountRef' => ['value' => '71', 'name' => 'Job Expenses:Job Materials'],
                ],
            ],
        ]);

        $this->assertSame('71', $lines[0]['expense_account_ref_id']);
        $this->assertSame(1, $lines[0]['chart_of_account_id']);
    }

    #[Test]
    public function build_bill_line_uses_chart_of_account_for_account_ref(): void
    {
        $item = new BillItem([
            'amount' => 80,
            'description' => 'Labor',
            'detail_type' => 'AccountBasedExpenseLineDetail',
        ]);
        $item->setRelation('chartOfAccount', new ChartOfAccount([
            'quickbooks_account_id' => '7',
            'name' => 'Job Expenses',
        ]));

        $method = new ReflectionMethod(QuickBooksAccountingService::class, 'buildBillLine');
        $method->setAccessible(true);
        $service = app(QuickBooksAccountingService::class);

        $line = $method->invoke($service, $item);

        $this->assertSame('7', $line['AccountBasedExpenseLineDetail']['AccountRef']['value']);
        $this->assertArrayNotHasKey('ClassRef', $line['AccountBasedExpenseLineDetail']);
    }

    #[Test]
    public function build_bill_payment_payload_links_bill_transactions(): void
    {
        ChartOfAccount::query()->create([
            'name' => 'Checking',
            'quickbooks_account_id' => '35',
            'fully_qualified_name' => 'Checking',
            'account_type' => 'Bank',
            'detail_type' => 'Checking',
            'active' => true,
        ]);

        $payment = new BillPayment([
            'pay_type' => 'Check',
            'total_amt' => 200,
            'bank_account_ref_id' => '35',
        ]);
        $line = new BillPaymentLine([
            'amount' => 200,
            'quickbooks_bill_id' => '88',
        ]);
        $line->setRelation('bill', new Bill(['quickbooks_bill_id' => '88']));
        $payment->setRelation('lines', collect([$line]));

        $payload = app(QuickBooksAccountingService::class)->buildBillPaymentPayload($payment, '56');

        $this->assertSame('Check', $payload['PayType']);
        $this->assertSame('88', $payload['Line'][0]['LinkedTxn'][0]['TxnId']);
    }

    #[Test]
    public function map_payment_lines_capture_amount_and_position(): void
    {
        $lines = QuickBooksBillPaymentMapper::mapPaymentLines([
            [
                'Amount' => 50,
                'LinkedTxn' => [],
            ],
        ]);

        $this->assertSame(50.0, $lines[0]['amount']);
        $this->assertSame(0, $lines[0]['position']);
    }
}
