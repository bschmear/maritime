<?php

declare(strict_types=1);

namespace App\Domain\Integration\Support;

use App\Domain\Integration\Models\Integration;
use App\Enums\Integration\IntegrationType;

final class EasyPostIntegrationSettings
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
        return self::from(
            Integration::query()
                ->where('integration_type', IntegrationType::EasyPost)
                ->first()
        );
    }

    public static function activeIntegration(): ?Integration
    {
        return Integration::query()
            ->where('integration_type', IntegrationType::EasyPost)
            ->where('active', true)
            ->first();
    }

    public function isConnected(): bool
    {
        return $this->integration !== null
            && (bool) $this->integration->active
            && filled($this->integration->access_token);
    }

    public function isTestMode(): bool
    {
        if ($this->integration === null) {
            return true;
        }

        return ($this->integration->settings ?? [])['test_mode'] ?? true;
    }

    public function hasApiKey(): bool
    {
        return $this->integration !== null && filled($this->integration->access_token);
    }

    public function apiKey(): ?string
    {
        return $this->integration?->access_token;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'test_mode' => $this->isTestMode(),
            'has_api_key' => $this->hasApiKey(),
            'active' => (bool) ($this->integration?->active ?? false),
            'external_id' => $this->integration?->external_id,
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function mergeIntoIntegrationSettings(array $settings): array
    {
        $existing = $this->integration?->settings ?? [];

        return array_merge($existing, $settings);
    }
}
