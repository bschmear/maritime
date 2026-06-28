<?php

declare(strict_types=1);

namespace App\Support\Asset;

use Illuminate\Support\Facades\Cache;

/**
 * Permanent cache for boat_spec_filler AI results (per tenant + model + spec schema).
 */
final class BoatSpecFillerCache
{
    private const STORE = 'redis';

    public static function key(string $tenantId, string $modelName, string $schemaHash): string
    {
        $normalizedModel = mb_strtolower(trim($modelName));

        return 'boat_spec_filler:'.sha1($tenantId.'|'.$normalizedModel.'|'.$schemaHash);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get(string $tenantId, string $modelName, string $schemaHash): ?array
    {
        /** @var array<string, mixed>|null */
        return Cache::store(self::STORE)->get(self::key($tenantId, $modelName, $schemaHash));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function put(string $tenantId, string $modelName, string $schemaHash, array $payload): void
    {
        Cache::store(self::STORE)->forever(
            self::key($tenantId, $modelName, $schemaHash),
            $payload,
        );
    }

    public static function forget(string $tenantId, string $modelName, string $schemaHash): void
    {
        Cache::store(self::STORE)->forget(self::key($tenantId, $modelName, $schemaHash));
    }
}
