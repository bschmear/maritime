<?php

namespace Tests\Unit;

use App\Models\TaxJurisdictionRate;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TaxJurisdictionRateTest extends TestCase
{
    #[Test]
    public function rate_is_stale_when_fetched_before_current_month(): void
    {
        Carbon::setTestNow('2026-06-15 12:00:00');

        $rate = new TaxJurisdictionRate([
            'fetched_at' => Carbon::parse('2026-05-31 23:59:59'),
        ]);

        $this->assertTrue($rate->isStale());
    }

    #[Test]
    public function rate_is_fresh_when_fetched_during_current_month(): void
    {
        Carbon::setTestNow('2026-06-15 12:00:00');

        $rate = new TaxJurisdictionRate([
            'fetched_at' => Carbon::parse('2026-06-01 00:00:01'),
        ]);

        $this->assertFalse($rate->isStale());
    }

    #[Test]
    public function lookup_result_uses_total_rate_percent(): void
    {
        $rate = new TaxJurisdictionRate([
            'total_rate_percent' => 7.0,
            'jurisdiction_code' => 'FL',
            'jurisdiction_label' => 'Fort Lauderdale, FL, 33316 (Broward County)',
        ]);

        $result = $rate->toLookupResult();

        $this->assertSame(7.0, $result['tax_rate']);
        $this->assertSame(0.07, $result['tax_rate_decimal']);
    }
}
