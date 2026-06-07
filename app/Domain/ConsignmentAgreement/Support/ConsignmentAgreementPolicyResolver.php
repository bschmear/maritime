<?php

declare(strict_types=1);

namespace App\Domain\ConsignmentAgreement\Support;

use App\Domain\ConsignmentAgreement\Models\ConsignmentAgreement;
use App\Domain\ConsignmentPolicy\Models\ConsignmentPolicy;
use App\Models\AccountSettings;

class ConsignmentAgreementPolicyResolver
{
    /**
     * Policies shown on an agreement: live active policies until signed, then the signed snapshot.
     *
     * @return list<array{id: int, body: string, sort_order: int}>
     */
    public static function forAgreement(ConsignmentAgreement $agreement): array
    {
        if ($agreement->signed_at !== null) {
            return self::fromSnapshot($agreement->policies_snapshot);
        }

        return self::fromActive();
    }

    /**
     * @return list<array{id: int, body: string, sort_order: int}>
     */
    public static function fromActive(): array
    {
        ConsignmentPolicy::ensureDefaultsExist();
        AccountSettings::ensureConsignmentDefaults();

        return ConsignmentPolicy::query()
            ->active()
            ->ordered()
            ->get(['id', 'body', 'sort_order'])
            ->map(fn (ConsignmentPolicy $policy) => [
                'id' => (int) $policy->id,
                'body' => (string) $policy->body,
                'sort_order' => (int) $policy->sort_order,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, body: string, sort_order: int}>
     */
    public static function snapshotFromActive(): array
    {
        return self::fromActive();
    }

    public static function policiesAreLocked(ConsignmentAgreement $agreement): bool
    {
        return $agreement->signed_at !== null;
    }

    /**
     * @return list<array{id: int, body: string, sort_order: int}>
     */
    private static function fromSnapshot(mixed $snapshot): array
    {
        if (! is_array($snapshot) || $snapshot === []) {
            return [];
        }

        $items = [];
        foreach ($snapshot as $row) {
            if (! is_array($row) || ! isset($row['body']) || $row['body'] === '') {
                continue;
            }
            $items[] = [
                'id' => (int) ($row['id'] ?? 0),
                'body' => (string) $row['body'],
                'sort_order' => (int) ($row['sort_order'] ?? 0),
            ];
        }

        usort($items, fn (array $a, array $b) => $a['sort_order'] <=> $b['sort_order'] ?: $a['id'] <=> $b['id']);

        return $items;
    }
}
