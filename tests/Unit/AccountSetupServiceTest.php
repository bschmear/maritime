<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\AccountSetup\Services\AccountSetupService;
use App\Http\Controllers\Tenant\AccountSetupController;
use App\Models\AccountSettings;
use ReflectionClass;
use Tests\TestCase;

class AccountSetupServiceTest extends TestCase
{
    public function test_account_setup_controller_defines_expected_actions(): void
    {
        $controller = new ReflectionClass(AccountSetupController::class);

        $this->assertTrue($controller->hasMethod('index'));
        $this->assertTrue($controller->hasMethod('update'));
    }

    public function test_account_setup_routes_are_defined_in_tenant_routes_file(): void
    {
        $contents = (string) file_get_contents(base_path('routes/tenant.php'));

        $this->assertStringContainsString('AccountSetupController', $contents);
        $this->assertStringContainsString("'setup.index'", $contents);
        $this->assertStringContainsString("'setup.steps.update'", $contents);
    }

    public function test_account_settings_includes_account_setup_complete_flag(): void
    {
        $fillable = (new AccountSettings)->getFillable();

        $this->assertContains('account_setup_complete', $fillable);
    }

    public function test_account_setup_service_exposes_completion_sync(): void
    {
        $service = new ReflectionClass(AccountSetupService::class);

        $this->assertTrue($service->hasMethod('syncAccountSetupComplete'));
        $this->assertTrue($service->hasMethod('markStep'));
        $this->assertTrue($service->hasMethod('widgetPayload'));
    }
}
