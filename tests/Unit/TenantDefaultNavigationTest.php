<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\TenantNavigation\TenantDefaultNavigation;
use Tests\TestCase;

class TenantDefaultNavigationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.stores.redis' => [
                'driver' => 'array',
                'serialize' => false,
            ],
        ]);
    }

    public function test_loads_default_menu_from_json_file(): void
    {
        $nodes = TenantDefaultNavigation::nodes();

        $this->assertNotEmpty($nodes);
        $this->assertSame('Overview', $nodes[0]['label'] ?? null);
        $this->assertSame('dashboard', $nodes[0]['route'] ?? null);
    }

    public function test_tree_with_metadata_includes_permission_keys(): void
    {
        $tree = TenantDefaultNavigation::treeWithMetadata();

        $this->assertNotEmpty($tree);
        $this->assertSame('Overview', $tree[0]['name']);
        $this->assertSame('dashboard', $tree[0]['href']);
        $this->assertArrayHasKey('permission_key', $tree[0]);
    }

    public function test_editor_nodes_match_json_structure(): void
    {
        $editorNodes = TenantDefaultNavigation::editorNodes();

        $this->assertGreaterThan(5, count($editorNodes));
        $this->assertSame('Overview', $editorNodes[0]['label']);
        $this->assertSame('dashboard', $editorNodes[0]['route_name']);
        $this->assertTrue($editorNodes[0]['permission_granted_for_role']);
    }
}
