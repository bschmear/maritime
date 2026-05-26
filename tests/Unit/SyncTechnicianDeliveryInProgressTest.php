<?php

namespace Tests\Unit;

use App\Domain\Delivery\Support\SyncTechnicianDeliveryInProgress;
use Tests\TestCase;

class SyncTechnicianDeliveryInProgressTest extends TestCase
{
    public function test_recompute_for_empty_user_ids_does_not_throw(): void
    {
        SyncTechnicianDeliveryInProgress::recomputeForUserIds([]);
        $this->assertTrue(true);
    }

    public function test_sync_for_delivery_method_exists(): void
    {
        $this->assertTrue(method_exists(SyncTechnicianDeliveryInProgress::class, 'syncForDelivery'));
    }
}
