<?php

declare(strict_types=1);

namespace App\Services\TenantNavigation;

use App\Domain\NavigationMenu\Models\NavigationMenuItem;
use Illuminate\Support\Collection;

final class TenantNavigationTree
{
    /**
     * @param  Collection<int, NavigationMenuItem>  $items
     * @return list<array{name: string, href?: string, children?: list<array<string, mixed>>}>
     */
    public static function build(Collection $items): array
    {
        $byParent = $items->groupBy(fn (NavigationMenuItem $item) => $item->parent_id ?? 0);

        return self::buildBranch($byParent, 0);
    }

    /**
     * @param  Collection<int|string, Collection<int, NavigationMenuItem>>  $byParent
     * @return list<array{name: string, href?: string, children?: list<array<string, mixed>>}>
     */
    private static function buildBranch(Collection $byParent, int $parentKey): array
    {
        $nodes = [];

        foreach ($byParent->get($parentKey, collect()) as $item) {
            $childNodes = self::buildBranch($byParent, $item->id);
            $node = ['name' => $item->label];

            if ($item->route_name !== null && $item->route_name !== '') {
                $node['href'] = $item->route_name;
            }

            if ($childNodes !== []) {
                $node['children'] = $childNodes;
            }

            $nodes[] = $node;
        }

        return $nodes;
    }

    /**
     * @param  list<array{name: string, href?: string, children?: list<array<string, mixed>>}>  $tree
     * @return list<array{name: string, href?: string, children?: list<array<string, mixed>>}>
     */
    public static function filterByPermission(array $tree, callable $hasPermission): array
    {
        $filtered = [];

        foreach ($tree as $node) {
            $permissionKey = $node['permission_key'] ?? null;
            $children = $node['children'] ?? [];
            $filteredChildren = $children !== []
                ? self::filterByPermission($children, $hasPermission)
                : [];

            $isLink = isset($node['href']);
            $blocked = $permissionKey !== null && ! $hasPermission($permissionKey);

            if ($blocked && $filteredChildren === []) {
                continue;
            }

            if ($blocked && ! $isLink) {
                if ($filteredChildren === []) {
                    continue;
                }

                $node = [
                    'name' => $node['name'],
                    'children' => $filteredChildren,
                ];
            } elseif ($filteredChildren !== []) {
                $node['children'] = $filteredChildren;
            } elseif ($blocked) {
                continue;
            }

            unset($node['permission_key']);
            $filtered[] = $node;
        }

        return $filtered;
    }

    /**
     * @param  list<array{name: string, href?: string, permission_key?: string|null, requires_integration?: string|null, children?: list<array<string, mixed>>}>  $tree
     * @return list<array{name: string, href?: string, children?: list<array<string, mixed>>}>
     */
    public static function filterByIntegration(array $tree, callable $integrationIsActive): array
    {
        $filtered = [];

        foreach ($tree as $node) {
            $requiresIntegration = $node['requires_integration'] ?? null;
            $children = $node['children'] ?? [];
            $filteredChildren = $children !== []
                ? self::filterByIntegration($children, $integrationIsActive)
                : [];

            $isLink = isset($node['href']);
            $blocked = $requiresIntegration !== null && ! $integrationIsActive($requiresIntegration);

            if ($blocked && $filteredChildren === []) {
                continue;
            }

            if ($blocked && ! $isLink) {
                if ($filteredChildren === []) {
                    continue;
                }

                $node = [
                    'name' => $node['name'],
                    'children' => $filteredChildren,
                ];
            } elseif ($filteredChildren !== []) {
                $node['children'] = $filteredChildren;
            } elseif ($blocked) {
                continue;
            }

            unset($node['requires_integration'], $node['permission_key']);
            $filtered[] = $node;
        }

        return $filtered;
    }

    /**
     * Attach permission_key metadata before filtering (internal use).
     *
     * @param  Collection<int, NavigationMenuItem>  $items
     * @return list<array{name: string, href?: string, permission_key?: string|null, children?: list<array<string, mixed>>}>
     */
    public static function buildWithPermissions(Collection $items): array
    {
        $byParent = $items->groupBy(fn (NavigationMenuItem $item) => $item->parent_id ?? 0);

        return self::buildBranchWithPermissions($byParent, 0);
    }

    /**
     * @param  Collection<int|string, Collection<int, NavigationMenuItem>>  $byParent
     * @return list<array{name: string, href?: string, permission_key?: string|null, children?: list<array<string, mixed>>}>
     */
    private static function buildBranchWithPermissions(Collection $byParent, int $parentKey): array
    {
        $nodes = [];

        foreach ($byParent->get($parentKey, collect()) as $item) {
            $childNodes = self::buildBranchWithPermissions($byParent, $item->id);
            $node = [
                'name' => $item->label,
                'permission_key' => $item->permission_key,
                'requires_integration' => $item->requires_integration,
            ];

            if ($item->route_name !== null && $item->route_name !== '') {
                $node['href'] = $item->route_name;
            }

            if ($childNodes !== []) {
                $node['children'] = $childNodes;
            }

            $nodes[] = $node;
        }

        return $nodes;
    }
}
