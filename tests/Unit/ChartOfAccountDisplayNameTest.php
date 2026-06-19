<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\ChartOfAccount\Models\ChartOfAccount;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChartOfAccountDisplayNameTest extends TestCase
{
    #[Test]
    public function display_name_uses_name_when_present(): void
    {
        $account = new ChartOfAccount([
            'id' => 5,
            'quickbooks_account_id' => '64',
            'name' => 'Job Expenses',
            'fully_qualified_name' => 'Expenses:Job Expenses',
        ]);

        $this->assertSame('Job Expenses', $account->display_name);
    }

    #[Test]
    public function display_name_falls_back_to_fully_qualified_name(): void
    {
        $account = new ChartOfAccount([
            'fully_qualified_name' => 'Expenses:Utilities',
        ]);

        $this->assertSame('Expenses:Utilities', $account->display_name);
    }

    #[Test]
    public function display_name_falls_back_to_quickbooks_reference_when_unnamed(): void
    {
        $account = new ChartOfAccount([
            'quickbooks_account_id' => '64',
        ]);

        $this->assertSame('COA-64', $account->display_name);
    }

    #[Test]
    public function display_name_falls_back_to_local_id_when_not_imported(): void
    {
        $account = new ChartOfAccount([]);
        $account->id = 12;

        $this->assertSame('COA-12', $account->display_name);
    }
}
