<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\QuickBooks\QuickBooksChartOfAccountsMapper;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class QuickBooksChartOfAccountsMapperTest extends TestCase
{
    #[Test]
    public function map_account_row_matches_sandbox_chart_of_accounts_export(): void
    {
        $row = [
            'Id' => '64',
            'Name' => 'Job Expenses',
            'FullyQualifiedName' => 'Job Expenses',
            'AccountType' => 'Expense',
            'AccountSubType' => 'OtherMiscellaneousServiceCost',
            'Active' => true,
            'SubAccount' => false,
        ];

        $payload = QuickBooksChartOfAccountsMapper::mapAccountRow($row);

        $this->assertSame('Job Expenses', $payload['name']);
        $this->assertSame('64', $payload['quickbooks_account_id']);
        $this->assertSame('Job Expenses', $payload['fully_qualified_name']);
        $this->assertSame('Expense', $payload['account_type']);
        $this->assertSame('OtherMiscellaneousServiceCost', $payload['detail_type']);
        $this->assertTrue($payload['active']);
    }

    #[Test]
    public function map_nested_sub_account_uses_colon_full_name_from_sandbox_export(): void
    {
        $row = [
            'Id' => '71',
            'Name' => 'Job Materials',
            'FullyQualifiedName' => 'Job Expenses:Job Materials',
            'AccountType' => 'Expense',
            'AccountSubType' => 'SuppliesMaterials',
            'Active' => true,
            'SubAccount' => true,
            'ParentRef' => ['value' => '64', 'name' => 'Job Expenses'],
        ];

        $payload = QuickBooksChartOfAccountsMapper::mapAccountRow($row);

        $this->assertSame('Job Materials', $payload['name']);
        $this->assertSame('Job Expenses:Job Materials', $payload['fully_qualified_name']);
        $this->assertSame('Expense', $payload['account_type']);
        $this->assertSame('SuppliesMaterials', $payload['detail_type']);
        $this->assertSame('64', QuickBooksChartOfAccountsMapper::parentQboId($row));
    }

    #[Test]
    public function map_deeply_nested_account_from_sandbox_export(): void
    {
        $row = [
            'Id' => '88',
            'Name' => 'Sprinklers and Drip Systems',
            'FullyQualifiedName' => 'Job Expenses:Job Materials:Sprinklers and Drip Systems',
            'AccountType' => 'Expense',
            'AccountSubType' => 'SuppliesMaterials',
            'Active' => true,
            'SubAccount' => true,
            'ParentRef' => ['value' => '71', 'name' => 'Job Expenses:Job Materials'],
        ];

        $payload = QuickBooksChartOfAccountsMapper::mapAccountRow($row);

        $this->assertSame('Sprinklers and Drip Systems', $payload['name']);
        $this->assertSame(
            'Job Expenses:Job Materials:Sprinklers and Drip Systems',
            $payload['fully_qualified_name'],
        );
    }
}
