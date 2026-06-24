<?php

declare(strict_types=1);

namespace App\Domain\Integration\Support;

use App\Domain\Integration\Models\Integration;
use App\Enums\Integration\IntegrationType;

final class WordPressIntegrationSettings
{
    public function __construct(
        private readonly ?Integration $integration,
    ) {}

    public static function from(?Integration $integration): self
    {
        return new self($integration);
    }

    public static function forCurrentTenant(): self
    {
        return self::from(self::integration());
    }

    public static function integration(): ?Integration
    {
        return Integration::query()
            ->where('integration_type', IntegrationType::WordPress)
            ->where('active', true)
            ->first();
    }

    public function isConnected(): bool
    {
        return $this->integration !== null
            && filled($this->wordpressUrl())
            && filled($this->integration->access_token);
    }

    public function wordpressUrl(): ?string
    {
        $url = $this->stringSetting('wordpress_url');

        return $url !== null ? rtrim($url, '/') : null;
    }

    public function isAutoPushEnabled(): bool
    {
        if ($this->integration === null) {
            return false;
        }

        $settings = $this->integration->settings ?? [];

        return ($settings['auto_push_enabled'] ?? true) === true;
    }

    public function hasHelmfulApiKey(): bool
    {
        return filled($this->stringSetting('helmful_api_key_hash'));
    }

    public function lastPushedAt(): ?string
    {
        return $this->stringSetting('last_pushed_at');
    }

    public function lastPullAt(): ?string
    {
        return $this->stringSetting('last_pull_at');
    }

    public static function hashApiKey(string $apiKey): string
    {
        return hash('sha256', $apiKey);
    }

    public static function verifyApiKey(string $apiKey, string $hash): bool
    {
        return hash_equals($hash, self::hashApiKey($apiKey));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'wordpress_url' => $this->wordpressUrl(),
            'auto_push_enabled' => $this->isAutoPushEnabled(),
            'has_helmful_api_key' => $this->hasHelmfulApiKey(),
            'last_pushed_at' => $this->lastPushedAt(),
            'last_pull_at' => $this->lastPullAt(),
        ];
    }

    private function stringSetting(string $key): ?string
    {
        if ($this->integration === null) {
            return null;
        }

        $value = ($this->integration->settings ?? [])[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
