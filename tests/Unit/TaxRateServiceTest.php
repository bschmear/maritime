<?php

namespace Tests\Unit;

use App\Services\TaxRateService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TaxRateServiceTest extends TestCase
{
    #[Test]
    public function normalize_state_code_from_abbreviation_and_name(): void
    {
        $service = app(TaxRateService::class);

        $this->assertSame('FL', $service->normalizeStateCode('fl'));
        $this->assertSame('FL', $service->normalizeStateCode('Florida'));
        $this->assertSame('CA', $service->normalizeStateCode('California'));
    }

    #[Test]
    public function state_label_expands_abbreviation(): void
    {
        $service = app(TaxRateService::class);

        $this->assertSame('Florida', $service->stateLabel('FL'));
    }

    #[Test]
    public function lookup_by_address_fallback_uses_state_code(): void
    {
        $service = new class extends TaxRateService
        {
            protected function fetchFromStripe(array $address): array
            {
                return ['rate_decimal' => null, 'jurisdiction_code' => null, 'jurisdiction_label' => null];
            }
        };

        $lookup = $service->lookupByAddress([
            'state' => 'FL',
            'city' => 'Fort Lauderdale',
            'postal_code' => '33316',
        ]);

        $this->assertSame('FL', $lookup['jurisdiction_code']);
        $this->assertSame('Fort Lauderdale, FL, 33316', $lookup['jurisdiction_label']);
        $this->assertSame(6.0, $lookup['tax_rate']);
    }
}
