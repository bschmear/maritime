<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\AccountSettings;
use Tests\TestCase;

class TenantCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.stores.redis' => [
                'driver' => 'array',
                'serialize' => false,
            ],
        ]);
    }

    public function test_account_settings_get_current_uses_request_memoization(): void
    {
        $settings = new AccountSettings;
        $settings->forceFill(['id' => 1, 'timezone' => 'UTC']);
        $settings->exists = true;

        $property = new \ReflectionProperty(AccountSettings::class, 'resolved');
        $property->setAccessible(true);
        $property->setValue(null, $settings);

        try {
            $this->assertSame($settings, AccountSettings::getCurrent());
        } finally {
            AccountSettings::clearCache();
        }
    }
}
