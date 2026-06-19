<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\ChartOfAccount\Models\ChartOfAccount;
use App\Support\ChartOfAccount\ChartOfAccountTreeBuilder;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ChartOfAccountTreeBuilderTest extends TestCase
{
    public function test_builds_nested_tree_by_parent(): void
    {
        $accounts = new Collection([
            $this->account(1, null, 'Assets', 'Assets'),
            $this->account(2, 1, 'Bank', 'Assets:Bank'),
            $this->account(3, 1, 'AR', 'Assets:AR'),
            $this->account(4, null, 'Expenses', 'Expenses'),
        ]);

        $tree = ChartOfAccountTreeBuilder::build($accounts);

        $this->assertCount(2, $tree);
        $this->assertSame('Assets', $tree[0]['name']);
        $this->assertTrue($tree[0]['has_children']);
        $this->assertCount(2, $tree[0]['children']);
        $this->assertSame('AR', $tree[0]['children'][0]['name']);
        $this->assertSame('Bank', $tree[0]['children'][1]['name']);
    }

    public function test_search_includes_matching_nodes_and_ancestors(): void
    {
        $accounts = new Collection([
            $this->account(1, null, 'Assets', 'Assets'),
            $this->account(2, 1, 'Bank', 'Assets:Bank'),
            $this->account(3, null, 'Expenses', 'Expenses'),
        ]);

        $tree = ChartOfAccountTreeBuilder::build($accounts, 'bank');

        $this->assertCount(1, $tree);
        $this->assertSame('Assets', $tree[0]['name']);
        $this->assertCount(1, $tree[0]['children']);
        $this->assertSame('Bank', $tree[0]['children'][0]['name']);
    }

    public function test_active_filter_limits_nodes(): void
    {
        $accounts = new Collection([
            $this->account(1, null, 'Active root', 'Active root', true),
            $this->account(2, null, 'Inactive root', 'Inactive root', false),
        ]);

        $tree = ChartOfAccountTreeBuilder::build($accounts, null, null, true);

        $this->assertCount(1, $tree);
        $this->assertSame('Active root', $tree[0]['name']);
    }

    private function account(int $id, ?int $parentId, string $name, string $fullName, bool $active = true): ChartOfAccount
    {
        $account = new ChartOfAccount([
            'parent_id' => $parentId,
            'name' => $name,
            'fully_qualified_name' => $fullName,
            'active' => $active,
        ]);
        $account->id = $id;

        return $account;
    }
}
