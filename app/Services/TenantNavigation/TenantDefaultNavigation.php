<?php

declare(strict_types=1);

namespace App\Services\TenantNavigation;

use Illuminate\Support\Facades\Cache;

/**
 * Application-wide default tenant navigation loaded from config/tenant_navigation.json.
 *
 * Cached by file modification time so deploys pick up JSON changes without tenant DB seeds.
 */
final class TenantDefaultNavigation
{
    private const CACHE_STORE = 'redis';

    /**
     * @return list<array<string, mixed>>
     */
    public static function nodes(): array
    {
        $path = config_path('tenant_navigation.json');

        if (! is_readable($path)) {
            return [];
        }

        $mtime = (int) filemtime($path);

        return Cache::store(self::CACHE_STORE)->remember(
            'tenant_nav_default_file:'.$mtime,
            now()->addDay(),
            function () use ($path) {
                $decoded = json_decode((string) file_get_contents($path), true);

                return is_array($decoded) ? $decoded : [];
            },
        );
    }

    /**
     * @return list<array{name: string, href?: string, permission_key?: string|null, requires_integration?: string|null, children?: list<array<string, mixed>>}>
     */
    public static function treeWithMetadata(): array
    {
        return self::mapNodes(self::nodes());
    }

    /**
     * @return list<array{label: string, route_name: string|null, permission_key: string|null, permission_granted_for_role: bool, children: list<array<string, mixed>>}>
     */
    public static function editorNodes(): array
    {
        return self::mapEditorNodes(self::nodes());
    }

    public static function forgetCache(): void
    {
        $path = config_path('tenant_navigation.json');
        if (! is_readable($path)) {
            return;
        }

        $mtime = (int) filemtime($path);
        Cache::store(self::CACHE_STORE)->forget('tenant_nav_default_file:'.$mtime);
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @return list<array<string, mixed>>
     */
    private static function mapNodes(array $nodes): array
    {
        $mapped = [];

        foreach ($nodes as $node) {
            $label = (string) ($node['label'] ?? '');
            $route = isset($node['route']) ? (string) $node['route'] : null;
            $requiresIntegration = isset($node['requires_integration'])
                ? (string) $node['requires_integration']
                : ($route !== null ? TenantNavigationCatalog::requiresIntegrationForRoute($route) : null);

            $item = [
                'name' => $label,
                'permission_key' => $route !== null ? TenantNavigationCatalog::permissionKeyForRoute($route) : null,
                'requires_integration' => $requiresIntegration,
            ];

            if ($route !== null && $route !== '') {
                $item['href'] = $route;
            }

            $children = $node['children'] ?? [];
            if (is_array($children) && $children !== []) {
                $item['children'] = self::mapNodes($children);
            }

            $mapped[] = $item;
        }

        return $mapped;
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @return list<array<string, mixed>>
     */
    private static function mapEditorNodes(array $nodes): array
    {
        $mapped = [];

        foreach ($nodes as $node) {
            $route = isset($node['route']) ? (string) $node['route'] : null;
            $permissionKey = $route !== null ? TenantNavigationCatalog::permissionKeyForRoute($route) : null;

            $mapped[] = [
                'label' => (string) ($node['label'] ?? ''),
                'route_name' => $route,
                'permission_key' => $permissionKey,
                'permission_granted_for_role' => true,
                'children' => self::mapEditorNodes(is_array($node['children'] ?? null) ? $node['children'] : []),
            ];
        }

        return $mapped;
    }
}
