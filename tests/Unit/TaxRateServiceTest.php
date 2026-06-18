<?php

namespace Tests\Unit;

use App\Services\Tax\TaxJurisdictionRateStore;
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
    public function normalize_country_code_from_name_and_abbreviation(): void
    {
        $service = app(TaxRateService::class);

        $this->assertSame('US', $service->normalizeCountryCode('US'));
        $this->assertSame('US', $service->normalizeCountryCode('United States'));
        $this->assertSame('US', $service->normalizeCountryCode('UNITED STATES'));
        $this->assertSame('CA', $service->normalizeCountryCode('Canada'));
    }

    #[Test]
    public function lookup_by_address_uses_stored_jurisdiction_rate_when_available(): void
    {
        $this->mock(TaxJurisdictionRateStore::class, function ($mock): void {
            $mock->shouldReceive('resolve')->once()->andReturn([
                'tax_rate' => 6.0,
                'tax_rate_decimal' => 0.06,
                'jurisdiction_code' => 'FL',
                'jurisdiction_label' => 'Naples, FL, 34112 (Collier County)',
            ]);
        });

        $service = app(TaxRateService::class);

        $lookup = $service->lookupByAddress([
            'state' => 'FL',
            'city' => 'Naples',
            'postal_code' => '34112',
            'country' => 'US',
        ]);

        $this->assertSame(6.0, $lookup['tax_rate']);
        $this->assertSame('FL', $lookup['jurisdiction_code']);
        $this->assertStringContainsString('Collier County', (string) $lookup['jurisdiction_label']);
    }

    #[Test]
    public function lookup_by_address_falls_back_to_state_rate_when_ai_unavailable(): void
    {
        $this->mock(TaxJurisdictionRateStore::class, function ($mock): void {
            $mock->shouldReceive('resolve')->once()->andReturn(null);
        });

        $service = app(TaxRateService::class);

        $lookup = $service->lookupByAddress([
            'state' => 'California',
            'city' => 'Los Angeles',
            'postal_code' => '90001',
            'country' => 'United States',
        ]);

        $this->assertSame('CA', $lookup['jurisdiction_code']);
        $this->assertSame(7.25, $lookup['tax_rate']);
    }

    #[Test]
    public function lookup_by_address_fallback_uses_state_rate_without_zip(): void
    {
        $this->mock(TaxJurisdictionRateStore::class, function ($mock): void {
            $mock->shouldReceive('resolve')->once()->andReturn(null);
        });

        $service = app(TaxRateService::class);

        $lookup = $service->lookupByAddress([
            'state' => 'FL',
            'city' => 'Fort Lauderdale',
        ]);

        $this->assertSame('FL', $lookup['jurisdiction_code']);
        $this->assertSame('Fort Lauderdale, FL', $lookup['jurisdiction_label']);
        $this->assertSame(6.0, $lookup['tax_rate']);
    }
}
