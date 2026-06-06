<?php

declare(strict_types=1);

namespace App\Domain\MsoRecord\Support;

final class MsoRecordDetails
{
    /**
     * @param  array<string, mixed>|null  $details
     * @return array<string, mixed>
     */
    public static function normalize(?array $details): array
    {
        if (! is_array($details)) {
            return [
                'snapshot' => [],
                'assigned_user_id' => null,
                'fields' => [],
            ];
        }

        if (isset($details['snapshot']) || isset($details['fields'])) {
            return array_merge([
                'snapshot' => [],
                'assigned_user_id' => null,
                'fields' => [],
            ], $details);
        }

        return [
            'snapshot' => $details,
            'assigned_user_id' => null,
            'fields' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  list<array<string, mixed>>  $fields
     * @return array<string, mixed>
     */
    public static function build(array $snapshot, ?int $assignedUserId, array $fields): array
    {
        return [
            'snapshot' => $snapshot,
            'assigned_user_id' => $assignedUserId,
            'fields' => array_values($fields),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $details
     * @return list<array<string, mixed>>
     */
    public static function fields(?array $details): array
    {
        $normalized = self::normalize($details);
        $fields = $normalized['fields'] ?? [];

        return is_array($fields) ? array_values($fields) : [];
    }
}
