<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\QuickBooks\QuickBooksChartOfAccountsMapper;
use PHPUnit\Framework\TestCase;

class PullChartOfAccountsFromQuickBooksTest extends TestCase
{
    public function test_extract_qbo_id_reads_string_id_field(): void
    {
        $this->assertSame('42', QuickBooksChartOfAccountsMapper::extractQboId(['Id' => '42', 'Name' => 'Parts']));
        $this->assertSame('7', QuickBooksChartOfAccountsMapper::extractQboId(['Id' => 7, 'Name' => 'Labor']));
        $this->assertSame('', QuickBooksChartOfAccountsMapper::extractQboId(['Name' => 'Missing id']));
    }
}
