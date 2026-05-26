<?php

namespace Tests\Feature;

use App\Domain\Delivery\Support\SyncTechnicianDeliveryInProgress;
use App\Http\Controllers\Tenant\DeliveryController;
use ReflectionClass;
use Tests\TestCase;

class DeliveryInProgressFeatureTest extends TestCase
{
    public function test_delivery_controller_defines_notify_arrived(): void
    {
        $ref = new ReflectionClass(DeliveryController::class);
        $this->assertTrue($ref->hasMethod('notifyArrived'));
    }

    public function test_sync_technician_delivery_in_progress_class_is_loadable(): void
    {
        $this->assertTrue(class_exists(SyncTechnicianDeliveryInProgress::class));
    }
}
