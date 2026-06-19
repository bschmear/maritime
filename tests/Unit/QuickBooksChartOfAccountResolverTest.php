<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\ChartOfAccount\Models\ChartOfAccount;
use App\Support\QuickBooks\QuickBooksChartOfAccountResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class QuickBooksChartOfAccountResolverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('chart_of_accounts');
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('quickbooks_account_id', 64)->nullable();
            $table->string('fully_qualified_name')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('chart_of_accounts');

        parent::tearDown();
    }

    #[Test]
    public function it_resolves_local_chart_of_account_by_quickbooks_account_id(): void
    {
        $account = ChartOfAccount::query()->create([
            'name' => 'Accounts Payable (A/P)',
            'quickbooks_account_id' => '33',
            'fully_qualified_name' => 'Accounts Payable (A/P)',
        ]);

        $this->assertSame($account->id, QuickBooksChartOfAccountResolver::resolveLocalIdByQuickbooksAccountId('33'));

        $summary = QuickBooksChartOfAccountResolver::resolveSummaryByQuickbooksAccountId('33');
        $this->assertSame($account->id, $summary['id']);
        $this->assertSame('Accounts Payable (A/P)', $summary['name']);
    }
}
