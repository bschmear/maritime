<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Domain\Integration\Models\Integration;
use App\Enums\Payments\Terms;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class QuickBooksTermsService
{
    public function __construct(
        protected QuickBooksOAuthService $oauth,
    ) {}

    /**
     * @return array{value: string}|null SalesTermRef payload for a QBO Invoice, or null when not mapped.
     */
    public function salesTermRefFor(Integration $integration, Terms $term): ?array
    {
        if ($term === Terms::Custom) {
            return null;
        }

        $termId = $this->resolveTermId($integration, $term);
        if ($termId === null) {
            return null;
        }

        return ['value' => $termId];
    }

    public function resolveTermId(Integration $integration, Terms $term): ?string
    {
        if ($term === Terms::Custom) {
            return null;
        }

        $termsByName = $this->cachedTermsByName($integration);

        foreach ($term->quickbooksTermNames() as $name) {
            $key = $this->normalizeTermName($name);
            if (isset($termsByName[$key])) {
                return $termsByName[$key];
            }
        }

        Log::warning('QuickBooks payment term not found for Maritime term', [
            'integration_id' => $integration->id,
            'realm_id' => $integration->external_id,
            'maritime_term' => $term->value,
            'attempted_qbo_names' => $term->quickbooksTermNames(),
        ]);

        return null;
    }

    public function forgetCachedTerms(Integration $integration): void
    {
        $realmId = (string) $integration->external_id;
        if ($realmId === '') {
            return;
        }

        Cache::forget($this->cacheKey($integration->id, $realmId));
    }

    /**
     * @return array<string, string> normalized Name => Term Id
     */
    protected function cachedTermsByName(Integration $integration): array
    {
        $realmId = (string) $integration->external_id;
        if ($realmId === '') {
            return [];
        }

        return Cache::remember(
            $this->cacheKey($integration->id, $realmId),
            now()->addDay(),
            fn () => $this->fetchTermsByName($integration),
        );
    }

    /**
     * @return array<string, string>
     */
    protected function fetchTermsByName(Integration $integration): array
    {
        $payload = $this->oauth->queryAccountingForIntegration(
            $integration,
            'select Id, Name from Term where Active = true',
        );

        if (! empty($payload['Fault'])) {
            throw new RuntimeException(
                $this->faultMessage($payload['Fault']) ?: 'QuickBooks terms query failed.',
            );
        }

        $terms = $payload['QueryResponse']['Term'] ?? [];
        if ($terms === []) {
            return [];
        }
        if (! array_is_list($terms)) {
            $terms = [$terms];
        }

        $byName = [];
        foreach ($terms as $term) {
            if (! is_array($term) || empty($term['Id']) || empty($term['Name'])) {
                continue;
            }
            $byName[$this->normalizeTermName((string) $term['Name'])] = (string) $term['Id'];
        }

        return $byName;
    }

    protected function cacheKey(int $integrationId, string $realmId): string
    {
        return "qbo_terms_{$integrationId}_{$realmId}";
    }

    protected function normalizeTermName(string $name): string
    {
        return strtolower(preg_replace('/\s+/u', ' ', trim($name)) ?? trim($name));
    }

    /**
     * @param  array<string, mixed>  $fault
     */
    protected function faultMessage(array $fault): string
    {
        $errors = $fault['Error'] ?? [];
        if (! is_array($errors)) {
            return '';
        }
        if ($errors !== [] && ! array_is_list($errors)) {
            $errors = [$errors];
        }
        $parts = [];
        foreach ($errors as $err) {
            if (is_array($err) && ! empty($err['Message'])) {
                $parts[] = (string) $err['Message'];
            }
        }

        return implode('; ', $parts);
    }
}
