<?php

declare(strict_types=1);

namespace App\Services\TenantNavigation;

use App\Enums\RecordType;

final class TenantNavigationCatalog
{
    /**
     * Explicit route name => permission key overrides.
     *
     * @var array<string, string|null>
     */
    private const ROUTE_PERMISSION_OVERRIDES = [
        'dashboard' => null,
        'sales.index' => null,
        'serviceyard.index' => null,
        'serviceyard.scheduling' => null,
        'integrations' => null,
        'account.index' => null,
        'reports.pnl' => null,
        'reports.cash-flow' => null,
        'reports.sales-tax-liability' => null,
        'reports.sales-tax-payable' => null,
        'reports.financing' => null,
        'reports.sales-by-customer' => null,
        'reports.sales-by-item-summary' => null,
        'reports.sales-by-item-detail' => null,
        'fleet.index' => null,
        'fleet.maintenance.index' => null,
        'qualifications.index' => null,
        'mso.index' => 'msorecord.view',
        'chart-of-accounts.index' => null,
        'assets.units.global-index' => 'assetunit.view',
        'asset-options.index' => 'asset.view',
        'asset-specs.index' => 'asset.view',
        'surveysIndex' => 'survey.view',
        'surveysCreate' => 'survey.create',
        'surveyResponses' => 'survey.view',
        'boat-shows.index' => null,
        'boat-show-events.index' => null,
        'boat-show-email-templates.index' => null,
        'deliveries.requests.index' => 'delivery.view',
        'deliveries.delivery-schedule' => 'delivery.view',
        'delivery-locations.index' => 'delivery.view',
        'delivery-checklist-templates.index' => 'delivery.view',
    ];

    /**
     * @return list<array{route: string|null, label: string, permission_key: string|null, requires_integration: string|null, group_path: list<string>}>
     */
    public static function flattened(): array
    {
        return self::flattenNodes(TenantDefaultNavigation::nodes());
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @param  list<string>  $groupPath
     * @return list<array{route: string|null, label: string, permission_key: string|null, requires_integration: string|null, group_path: list<string>}>
     */
    private static function flattenNodes(array $nodes, array $groupPath = []): array
    {
        $entries = [];

        foreach ($nodes as $node) {
            $label = (string) ($node['label'] ?? '');
            $route = isset($node['route']) ? (string) $node['route'] : null;
            $children = $node['children'] ?? [];
            $requiresIntegration = isset($node['requires_integration'])
                ? (string) $node['requires_integration']
                : null;

            if ($route !== null) {
                $entries[] = [
                    'route' => $route,
                    'label' => $label,
                    'permission_key' => self::permissionKeyForRoute($route),
                    'requires_integration' => $requiresIntegration,
                    'group_path' => $groupPath,
                ];
            }

            if ($children !== []) {
                $childPath = array_merge($groupPath, [$label]);
                $entries = array_merge($entries, self::flattenNodes($children, $childPath));
            }
        }

        return $entries;
    }

    public static function requiresIntegrationForRoute(?string $routeName): ?string
    {
        if ($routeName === null || $routeName === '') {
            return null;
        }

        foreach (self::flattened() as $entry) {
            if ($entry['route'] === $routeName) {
                return $entry['requires_integration'];
            }
        }

        return null;
    }

    public static function permissionKeyForRoute(?string $routeName): ?string
    {
        if ($routeName === null || $routeName === '') {
            return null;
        }

        if (array_key_exists($routeName, self::ROUTE_PERMISSION_OVERRIDES)) {
            return self::ROUTE_PERMISSION_OVERRIDES[$routeName];
        }

        $firstSegment = explode('.', $routeName)[0] ?? '';
        if ($firstSegment === '') {
            return null;
        }

        foreach (RecordType::cases() as $recordType) {
            $plural = $recordType->plural();
            if ($plural === $firstSegment || self::normalizeSegment($plural) === self::normalizeSegment($firstSegment)) {
                return $recordType->key().'.view';
            }
        }

        return null;
    }

    private static function normalizeSegment(string $value): string
    {
        return str_replace(['-', '_'], '', strtolower($value));
    }
}
