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
                'page_sizes' => [],
            ];
        }

        if (isset($details['snapshot']) || isset($details['fields'])) {
            return array_merge([
                'snapshot' => [],
                'assigned_user_id' => null,
                'fields' => [],
                'page_sizes' => [],
            ], $details);
        }

        return [
            'snapshot' => $details,
            'assigned_user_id' => null,
            'fields' => [],
            'page_sizes' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  list<array<string, mixed>>  $fields
     * @param  array<int|string, array{width: float|int, height: float|int}>  $pageSizes
     * @return array<string, mixed>
     */
    public static function build(array $snapshot, ?int $assignedUserId, array $fields, array $pageSizes = []): array
    {
        return [
            'snapshot' => $snapshot,
            'assigned_user_id' => $assignedUserId,
            'fields' => array_values($fields),
            'page_sizes' => $pageSizes,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $details
     * @return array<int|string, array{width: float, height: float}>
     */
    public static function pageSizes(?array $details): array
    {
        $normalized = self::normalize($details);
        $sizes = $normalized['page_sizes'] ?? [];

        if (! is_array($sizes)) {
            return [];
        }

        $parsed = [];
        foreach ($sizes as $page => $dimensions) {
            if (! is_array($dimensions)) {
                continue;
            }

            $width = (float) ($dimensions['width'] ?? 0);
            $height = (float) ($dimensions['height'] ?? 0);
            if ($width <= 0 || $height <= 0) {
                continue;
            }

            $parsed[(int) $page] = [
                'width' => $width,
                'height' => $height,
            ];
        }

        return $parsed;
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
