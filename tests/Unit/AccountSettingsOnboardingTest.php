<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\AccountSettings;
use Tests\TestCase;

class AccountSettingsOnboardingTest extends TestCase
{
    public function test_fillable_includes_onboarding_flags(): void
    {
        $fillable = (new AccountSettings)->getFillable();

        $this->assertContains('onboarding_complete', $fillable);
        $this->assertContains('account_overviewed', $fillable);
    }

    public function test_casts_include_onboarding_booleans(): void
    {
        $casts = (new AccountSettings)->getCasts();

        $this->assertSame('boolean', $casts['onboarding_complete'] ?? null);
        $this->assertSame('boolean', $casts['account_overviewed'] ?? null);
    }
}
