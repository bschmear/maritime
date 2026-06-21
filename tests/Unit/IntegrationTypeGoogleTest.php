<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\Integration\IntegrationType;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IntegrationTypeGoogleTest extends TestCase
{
    #[Test]
    public function google_integration_type_is_registered(): void
    {
        $this->assertSame(3, IntegrationType::Google->value);
        $this->assertSame('google', IntegrationType::Google->slug());
        $this->assertTrue(IntegrationType::Google->requiresOAuth());

        $slugs = array_column(IntegrationType::options(), 'slug');
        $this->assertContains('google', $slugs);
    }
}
