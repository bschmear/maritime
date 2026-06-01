<?php

namespace Tests\Unit;

use App\Support\PlanSeatPolicy;
use Tests\TestCase;

class PlanSeatPolicyTest extends TestCase
{
    public function test_for_marketing_uses_configured_values(): void
    {
        config([
            'app.included_seats' => 5,
            'app.extra_seats.monthly_price' => 15,
        ]);

        $this->assertSame([
            'included' => 5,
            'extra_monthly_price' => 15.0,
        ], PlanSeatPolicy::forMarketing());
    }
}
