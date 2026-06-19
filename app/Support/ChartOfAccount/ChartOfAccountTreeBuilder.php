<?php

declare(strict_types=1);

namespace App\Support\ChartOfAccount;

use App\Domain\ChartOfAccount\Models\ChartOfAccount;
use Illuminate\Support\Collection;

final class ChartOfAccountTreeBuilder
{
    /**
     * @param  Collection<int, ChartOfAccount>  $accounts
     * @return list<array<string, mixed>>
     */
    public static function build(
        Collection $accounts,
        ?string $search = null,
        ?string $accountType = null,
        ?bool $active = null,
    ): array {
        if ($accounts->isEmpty()) {
            return [];
        }

        /** @var Collection<int, ChartOfAccount> $indexed */
        $indexed = $accounts->keyBy('id');

        $candidateIds = self::filterCandidateIds($indexed, $search, $accountType, $active);

        if ($candidateIds === []) {
            return [];
        }

        return self::buildTree($indexed, $candidateIds);
    }

    /**
     * @param  Collection<int, ChartOfAccount>  $indexed
     * @return list<int>
     */
    private static function filterCandidateIds(
        Collection $indexed,
        ?string $search,
        ?string $accountType,
        ?bool $active,
    ): array {
        $ids = [];

        foreach ($indexed as $account) {
            if ($accountType !== null && (string) $account->account_type !== $accountType) {
                continue;
            }

            if ($active !== null && (bool) $account->active !== $active) {
                continue;
            }

            if ($search !== null && $search !== '' && ! self::matchesSearch($account, $search)) {
                continue;
            }

            $ids[(int) $account->id] = true;
        }

        if ($search !== null && $search !== '') {
            foreach (array_keys($ids) as $id) {
                foreach (self::ancestorIds($indexed, $id) as $ancestorId) {
                    $ids[$ancestorId] = true;
                }
            }
        }

        return array_keys($ids);
    }

    private static function matchesSearch(ChartOfAccount $account, string $search): bool
    {
        $needle = mb_strtolower(trim($search));

        if ($needle === '') {
            return true;
        }

        $haystacks = [
            (string) $account->id,
            (string) ($account->name ?? ''),
            (string) ($account->fully_qualified_name ?? ''),
            (string) ($account->quickbooks_account_id ?? ''),
            (string) ($account->account_type ?? ''),
            (string) ($account->detail_type ?? ''),
        ];

        foreach ($haystacks as $haystack) {
            if ($haystack !== '' && str_contains(mb_strtolower($haystack), $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Collection<int, ChartOfAccount>  $indexed
     * @return list<int>
     */
    private static function ancestorIds(Collection $indexed, int $id): array
    {
        $ancestors = [];
        $current = $indexed->get($id);

        while ($current !== null && $current->parent_id) {
            $parentId = (int) $current->parent_id;
            $ancestors[] = $parentId;
            $current = $indexed->get($parentId);
        }

        return $ancestors;
    }

    /**
     * @param  Collection<int, ChartOfAccount>  $indexed
     * @param  list<int>  $visibleIds
     * @return list<array<string, mixed>>
     */
    private static function buildTree(Collection $indexed, array $visibleIds): array
    {
        $visible = array_fill_keys($visibleIds, true);
        /** @var array<int, list<ChartOfAccount>> $byParent */
        $byParent = [];

        foreach ($visibleIds as $id) {
            $account = $indexed->get($id);
            if ($account === null) {
                continue;
            }

            $parentId = $account->parent_id ? (int) $account->parent_id : null;
            $bucketKey = ($parentId !== null && isset($visible[$parentId])) ? $parentId : 0;
            $byParent[$bucketKey][] = $account;
        }

        foreach ($byParent as &$group) {
            usort(
                $group,
                static fn (ChartOfAccount $a, ChartOfAccount $b): int => strcasecmp(
                    self::sortLabel($a),
                    self::sortLabel($b),
                ),
            );
        }
        unset($group);

        return self::mapNodes($byParent, 0);
    }

    /**
     * @param  array<int, list<ChartOfAccount>>  $byParent
     * @return list<array<string, mixed>>
     */
    private static function mapNodes(array $byParent, int $parentKey): array
    {
        $nodes = [];

        foreach ($byParent[$parentKey] ?? [] as $account) {
            $children = self::mapNodes($byParent, (int) $account->id);

            $nodes[] = [
                'id' => (int) $account->id,
                'name' => $account->name,
                'display_name' => $account->display_name,
                'fully_qualified_name' => $account->fully_qualified_name,
                'account_type' => $account->account_type,
                'detail_type' => $account->detail_type,
                'quickbooks_account_id' => $account->quickbooks_account_id,
                'active' => (bool) $account->active,
                'parent_id' => $account->parent_id ? (int) $account->parent_id : null,
                'has_children' => $children !== [],
                'children' => $children,
            ];
        }

        return $nodes;
    }

    private static function sortLabel(ChartOfAccount $account): string
    {
        if (filled($account->fully_qualified_name)) {
            return (string) $account->fully_qualified_name;
        }

        if (filled($account->name)) {
            return (string) $account->name;
        }

        return (string) $account->id;
    }
}
