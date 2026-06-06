<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Tenant\MsoController;
use App\Http\Controllers\Tenant\MsoRecordController;
use App\Http\Controllers\Tenant\RecordController;
use ReflectionClass;
use Tests\TestCase;

class MsoWorkflowFeatureTest extends TestCase
{
    public function test_mso_routes_are_defined_in_tenant_routes_file(): void
    {
        $contents = (string) file_get_contents(base_path('routes/tenant.php'));

        $this->assertStringContainsString("prefix('mso')->name('mso.')", $contents);
        $this->assertStringContainsString("[MsoController::class, 'index']", $contents);
        $this->assertStringContainsString("[MsoController::class, 'pending']", $contents);
        $this->assertStringContainsString("[MsoController::class, 'create']", $contents);
        $this->assertStringContainsString("[MsoController::class, 'show']", $contents);
        $this->assertStringContainsString("'records.builder'", $contents);
        $this->assertStringContainsString("'records.submit'", $contents);
        $this->assertStringContainsString("[MsoController::class, 'units']", $contents);
        $this->assertStringContainsString("[MsoController::class, 'batch']", $contents);
        $this->assertStringContainsString('MsoRecordController::class', $contents);
    }

    public function test_mso_controllers_define_expected_actions(): void
    {
        $mso = new ReflectionClass(MsoController::class);
        $this->assertTrue($mso->hasMethod('index'));
        $this->assertTrue($mso->hasMethod('pending'));
        $this->assertTrue($mso->hasMethod('units'));
        $this->assertTrue($mso->hasMethod('batch'));
        $this->assertTrue($mso->hasMethod('create'));
        $this->assertTrue($mso->hasMethod('show'));
        $this->assertTrue($mso->hasMethod('saveBuilder'));
        $this->assertTrue($mso->hasMethod('submit'));

        $record = new ReflectionClass(MsoRecordController::class);
        $this->assertTrue($record->isSubclassOf(RecordController::class));
    }
}
