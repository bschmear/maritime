<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\ChartOfAccount\Models\ChartOfAccount;
use App\Support\ChartOfAccount\DefaultChartOfAccounts;
use App\Support\QuickBooks\ChartOfAccountImportMatcher;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ChartOfAccountImportMatcherTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('chart_of_accounts')) {
            Schema::create('chart_of_accounts', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('quickbooks_account_id', 64)->nullable()->unique();
                $table->string('account_type')->nullable();
                $table->string('detail_type')->nullable();
                $table->string('fully_qualified_name')->nullable();
                $table->boolean('active')->default(true);
                $table->foreignId('parent_id')->nullable();
                $table->timestamps();
            });
        }

        ChartOfAccount::query()->delete();
    }

    public function test_matches_seeded_account_by_fully_qualified_name_and_links_qbo_id(): void
    {
        $seeded = ChartOfAccount::query()->create([
            'name' => 'Checking',
            'fully_qualified_name' => 'Checking',
            'account_type' => 'Bank',
            'detail_type' => 'Checking',
            'active' => true,
        ]);

        $match = ChartOfAccountImportMatcher::findExisting([
            'name' => 'Checking',
            'fully_qualified_name' => 'Checking',
            'account_type' => 'Bank',
            'detail_type' => 'Checking',
            'quickbooks_account_id' => '35',
        ]);

        $this->assertNotNull($match);
        $this->assertSame($seeded->id, $match->id);
    }

    public function test_matches_nested_account_by_fully_qualified_name(): void
    {
        $parent = ChartOfAccount::query()->create([
            'name' => 'Utilities',
            'fully_qualified_name' => 'Utilities',
            'account_type' => 'Expense',
            'detail_type' => 'Utilities',
            'active' => true,
        ]);

        $child = ChartOfAccount::query()->create([
            'name' => 'Telephone',
            'fully_qualified_name' => 'Utilities:Telephone',
            'account_type' => 'Expense',
            'detail_type' => 'Utilities',
            'active' => true,
            'parent_id' => $parent->id,
        ]);

        $match = ChartOfAccountImportMatcher::findExisting([
            'name' => 'Telephone',
            'fully_qualified_name' => 'Utilities:Telephone',
            'account_type' => 'Expense',
            'detail_type' => 'Utilities',
            'quickbooks_account_id' => '77',
        ]);

        $this->assertNotNull($match);
        $this->assertSame($child->id, $match->id);
    }

    public function test_default_chart_of_accounts_excludes_landscaping_specific_accounts(): void
    {
        $names = array_column(DefaultChartOfAccounts::definitions(), 'fully_qualified_name');

        $this->assertContains('Sales of Product Income', $names);
        $this->assertContains('Inventory Asset', $names);
        $this->assertNotContains('Landscaping Services', $names);
        $this->assertNotContains('Job Expenses:Job Materials:Decks and Patios', $names);
    }
}
