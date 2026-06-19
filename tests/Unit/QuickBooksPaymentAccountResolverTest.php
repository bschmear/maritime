<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\ChartOfAccount\Models\ChartOfAccount;
use App\Support\QuickBooks\QuickBooksPaymentAccountResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class QuickBooksPaymentAccountResolverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('chart_of_accounts')) {
            Schema::create('chart_of_accounts', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('quickbooks_account_id', 64)->nullable();
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
    public function it_rejects_expense_account_for_bank_payment_and_falls_back_to_bank(): void
    {
        ChartOfAccount::query()->create([
            'name' => 'Job Materials',
            'quickbooks_account_id' => '99',
            'account_type' => 'Expense',
            'detail_type' => 'SuppliesMaterials',
            'active' => true,
        ]);

        ChartOfAccount::query()->create([
            'name' => 'Checking',
            'quickbooks_account_id' => '35',
            'account_type' => 'Bank',
            'detail_type' => 'Checking',
            'active' => true,
        ]);

        $resolved = QuickBooksPaymentAccountResolver::resolveBankAccount('99');

        $this->assertNotNull($resolved);
        $this->assertSame('35', $resolved->quickbooks_account_id);
    }

    #[Test]
    public function it_only_includes_accounts_payable_when_type_matches(): void
    {
        ChartOfAccount::query()->create([
            'name' => 'AP',
            'quickbooks_account_id' => '33',
            'account_type' => 'Accounts Payable',
            'active' => true,
        ]);

        ChartOfAccount::query()->create([
            'name' => 'Expense',
            'quickbooks_account_id' => '44',
            'account_type' => 'Expense',
            'active' => true,
        ]);

        $this->assertSame('33', QuickBooksPaymentAccountResolver::validatedAccountsPayableQuickbooksId('33'));
        $this->assertNull(QuickBooksPaymentAccountResolver::validatedAccountsPayableQuickbooksId('44'));
    }
}
