<?php

namespace Tests\Unit;

use App\Domain\ConsignmentAgreement\Support\SyncAssetUnitForConsignmentMarking;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SyncAssetUnitForConsignmentMarkingTest extends TestCase
{
    public function test_apply_method_exists(): void
    {
        $this->assertTrue(method_exists(SyncAssetUnitForConsignmentMarking::class, 'apply'));
    }

    public function test_apply_throws_when_no_owner_contact_can_be_resolved(): void
    {
        $this->expectException(ValidationException::class);

        $unit = new \App\Domain\AssetUnit\Models\AssetUnit([
            'is_consignment' => false,
            'is_customer_owned' => false,
        ]);

        SyncAssetUnitForConsignmentMarking::apply($unit, null);
    }
}
