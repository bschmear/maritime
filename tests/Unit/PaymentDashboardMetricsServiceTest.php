<?php

namespace Tests\Unit;

use App\Services\Dashboard\PaymentDashboardMetricsService;
use Illuminate\Http\Request;
use Tests\TestCase;

class PaymentDashboardMetricsServiceTest extends TestCase
{
    public function test_resolve_payment_list_period_returns_null_for_all(): void
    {
        $request = Request::create('/payments', 'GET', ['period' => 'all']);
        $svc = new PaymentDashboardMetricsService;

        $this->assertNull($svc->resolvePaymentListPeriod($request));
    }

    public function test_resolve_payment_list_period_mtd(): void
    {
        $request = Request::create('/payments', 'GET', ['period' => 'mtd']);
        $svc = new PaymentDashboardMetricsService;

        $bounds = $svc->resolvePaymentListPeriod($request);
        $this->assertNotNull($bounds);
        $this->assertSame('mtd', $bounds['key']);
        $this->assertTrue($bounds['start']->lte($bounds['end']));
    }

    public function test_resolve_payment_list_period_custom_invalid_returns_null(): void
    {
        $request = Request::create('/payments', 'GET', ['period' => 'custom']);
        $svc = new PaymentDashboardMetricsService;

        $this->assertNull($svc->resolvePaymentListPeriod($request));
    }
}
