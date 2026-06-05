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
    public function normalize_country_code_from_name_and_abbreviation(): void
    {
        $service = app(TaxRateService::class);

        $this->assertSame('US', $service->normalizeCountryCode('US'));
        $this->assertSame('US', $service->normalizeCountryCode('United States'));
        $this->assertSame('US', $service->normalizeCountryCode('UNITED STATES'));
        $this->assertSame('CA', $service->normalizeCountryCode('Canada'));
    }

    #[Test]
    public function lookup_by_address_normalizes_country_and_state_for_stripe(): void
    {
        $service = new class extends TaxRateService
        {
            public ?array $capturedAddress = null;

            protected function fetchFromStripe(array $address): array
            {
                $this->capturedAddress = $address;

                return [
                    'rate_decimal' => 0.07,
                    'jurisdiction_code' => 'FL',
                    'jurisdiction_label' => 'Florida',
                ];
            }
        };

        $lookup = $service->lookupByAddress([
            'state' => 'Florida',
            'city' => 'Fort Lauderdale',
            'postal_code' => '33316',
            'country' => 'United States',
        ]);

        $this->assertSame('US', $service->capturedAddress['country'] ?? null);
        $this->assertSame('FL', $service->capturedAddress['state'] ?? null);
        $this->assertSame(7.0, $lookup['tax_rate']);
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
