<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Bill\Models\Bill;
use App\Domain\BillPayment\Models\BillPayment;
use App\Domain\BillPayment\Models\BillPaymentLine;
use App\Domain\ChartOfAccount\Models\ChartOfAccount;
use App\Enums\BillPayment\PayType;
use App\Services\Payments\QuickBooksAccountingService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BillPaymentPayTypeTest extends TestCase
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
    public function pay_type_enum_matches_quickbooks_bill_payment_type_values(): void
    {
        $this->assertSame('Check', PayType::Check->value);
        $this->assertSame('CreditCard', PayType::CreditCard->value);
        $this->assertContains('Check', PayType::values());
        $this->assertContains('CreditCard', PayType::values());
    }

    #[Test]
    public function ach_uses_bank_account_and_syncs_as_check_to_quickbooks(): void
    {
        $this->assertTrue(PayType::Ach->usesBankAccount());
        $this->assertFalse(PayType::Ach->usesCreditCardAccount());
        $this->assertSame('Check', PayType::Ach->quickbooksValue());
    }

    #[Test]
    public function build_bill_payment_payload_maps_ach_to_check_for_quickbooks(): void
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
            'pay_type' => PayType::Ach->value,
            'total_amt' => 150,
            'bank_account_ref_id' => '35',
        ]);
        $line = new BillPaymentLine([
            'amount' => 150,
            'quickbooks_bill_id' => '42',
        ]);
        $line->setRelation('bill', new Bill(['quickbooks_bill_id' => '42']));
        $payment->setRelation('lines', collect([$line]));

        $payload = app(QuickBooksAccountingService::class)->buildBillPaymentPayload($payment, '56');

        $this->assertSame('Check', $payload['PayType']);
        $this->assertSame('35', $payload['CheckPayment']['BankAccountRef']['value']);
        $this->assertArrayNotHasKey('CreditCardPayment', $payload);
    }

    #[Test]
    public function try_from_value_normalizes_common_aliases(): void
    {
        $this->assertSame(PayType::CreditCard, PayType::tryFromValue('creditcard'));
        $this->assertSame(PayType::Ach, PayType::tryFromValue('ach'));
        $this->assertSame(PayType::Check, PayType::tryFromValue('CHECK'));
    }
}
