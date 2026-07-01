<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Integration\Models\Integration;
use App\Domain\Integration\Support\EasyPostIntegrationSettings;
use App\Enums\Integration\IntegrationType;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EasyPostIntegrationSettingsTest extends TestCase
{
    #[Test]
    public function it_reports_connected_when_active_with_api_key(): void
    {
        $integration = new Integration([
            'integration_type' => IntegrationType::EasyPost,
            'active' => true,
            'settings' => [
                'test_mode' => true,
            ],
        ]);
        $integration->forceFill(['access_token' => 'EZAKTEST123456789']);

        $settings = EasyPostIntegrationSettings::from($integration);

        $this->assertTrue($settings->isConnected());
        $this->assertTrue($settings->hasApiKey());
        $this->assertTrue($settings->isTestMode());
        $this->assertArrayNotHasKey('access_token', $settings->toArray());
    }

    #[Test]
    public function it_is_not_connected_when_disabled(): void
    {
        $integration = new Integration([
            'integration_type' => IntegrationType::EasyPost,
            'active' => false,
        ]);
        $integration->forceFill(['access_token' => 'EZAKTEST123456789']);

        $settings = EasyPostIntegrationSettings::from($integration);

        $this->assertFalse($settings->isConnected());
    }
}
