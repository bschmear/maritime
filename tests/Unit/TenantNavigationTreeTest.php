<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\NavigationMenu\Models\NavigationMenuItem;
use App\Services\TenantNavigation\TenantNavigationTree;
use Illuminate\Support\Collection;
use Tests\TestCase;

class TenantNavigationTreeTest extends TestCase
{
    public function test_build_creates_nested_structure_with_three_levels(): void
    {
        $parent = new NavigationMenuItem([
            'id' => 1,
            'label' => 'Sales',
            'route_name' => null,
            'sort_order' => 0,
            'parent_id' => null,
        ]);
        $parent->id = 1;

        $child = new NavigationMenuItem([
            'id' => 2,
            'label' => 'Bills',
            'route_name' => 'bills.index',
            'sort_order' => 0,
            'parent_id' => 1,
        ]);
        $child->id = 2;

        $grandchild = new NavigationMenuItem([
            'id' => 3,
            'label' => 'Bill Payments',
            'route_name' => 'bill-payments.index',
            'sort_order' => 0,
            'parent_id' => 2,
        ]);
        $grandchild->id = 3;

        $tree = TenantNavigationTree::build(Collection::make([$parent, $child, $grandchild]));

        $this->assertSame('Sales', $tree[0]['name']);
        $this->assertArrayNotHasKey('href', $tree[0]);
        $this->assertSame('Bills', $tree[0]['children'][0]['name']);
        $this->assertSame('bills.index', $tree[0]['children'][0]['href']);
        $this->assertSame('Bill Payments', $tree[0]['children'][0]['children'][0]['name']);
        $this->assertSame('bill-payments.index', $tree[0]['children'][0]['children'][0]['href']);
    }

    public function test_filter_by_permission_removes_unauthorized_links(): void
    {
        $tree = [
            [
                'name' => 'Sales',
                'children' => [
                    [
                        'name' => 'Invoices',
                        'href' => 'invoices.index',
                        'permission_key' => 'invoice.view',
                    ],
                    [
                        'name' => 'Dashboard',
                        'href' => 'dashboard',
                        'permission_key' => null,
                    ],
                ],
            ],
        ];

        $filtered = TenantNavigationTree::filterByPermission(
            $tree,
            fn (string $permission) => $permission !== 'invoice.view',
        );

        $this->assertCount(1, $filtered);
        $this->assertSame('Dashboard', $filtered[0]['children'][0]['name']);
    }
}
