<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\KioskUrl;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class KioskUrlTest extends TestCase
{
    #[Test]
    public function it_uses_admin_url_when_configured(): void
    {
        config(['app.admin_url' => 'https://kiosk.maritime.test']);

        $this->assertSame(
            'https://kiosk.maritime.test/accounts/12',
            KioskUrl::accountShow(12)
        );
    }

    #[Test]
    public function it_falls_back_to_kiosk_subdomain(): void
    {
        config([
            'app.admin_url' => null,
            'app.url' => 'https://maritime.test',
            'app.domain' => 'maritime.test',
        ]);

        $this->assertSame(
            'https://kiosk.maritime.test/accounts/12',
            KioskUrl::accountShow(12)
        );
    }
}
