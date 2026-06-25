<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Middleware\EnsureCustomerContactEmailVerified;
use Tests\TestCase;

class EnsureCustomerContactEmailVerifiedTest extends TestCase
{
    public function test_middleware_class_is_loadable(): void
    {
        $this->assertTrue(class_exists(EnsureCustomerContactEmailVerified::class));
    }

    public function test_customer_verify_email_notification_uses_portal_route(): void
    {
        $source = file_get_contents(app_path('Notifications/CustomerVerifyEmail.php'));

        $this->assertStringContainsString('portal.verification.verify', $source);
    }
}
