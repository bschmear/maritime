<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Controllers\Tenant\AddressAutocompleteController;
use App\Services\RadarAddressAutocompleteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RadarAddressAutocompleteServiceTest extends TestCase
{
    public function test_search_returns_normalized_addresses_from_radar_api(): void
    {
        config([
            'services.radar.secret' => 'prj_live_sk_test',
            'services.radar.publishable' => null,
        ]);

        Http::fake([
            'api.radar.io/v1/search/autocomplete*' => Http::response([
                'meta' => ['code' => 200],
                'addresses' => [
                    [
                        'latitude' => 40.695779,
                        'longitude' => -73.991489,
                        'country' => 'United States',
                        'countryCode' => 'US',
                        'city' => 'Brooklyn',
                        'number' => '1',
                        'postalCode' => '11201',
                        'stateCode' => 'NY',
                        'state' => 'New York',
                        'street' => 'Clinton St',
                        'formattedAddress' => '1 Clinton St, Brooklyn, New York, NY 11201 USA',
                        'placeLabel' => 'Brooklyn Roasting Company',
                    ],
                ],
            ]),
        ]);

        $service = new RadarAddressAutocompleteService;
        $addresses = $service->search('brooklyn roasting');

        $this->assertCount(1, $addresses);
        $this->assertSame('Brooklyn Roasting Company', $addresses[0]['addressLabel']);
        $this->assertSame('Clinton St', $addresses[0]['street']);

        Http::assertSent(function ($request) {
            return str_starts_with($request->url(), 'https://api.radar.io/v1/search/autocomplete')
                && $request['query'] === 'brooklyn roasting'
                && $request['layers'] === 'address'
                && $request->hasHeader('Authorization', 'prj_live_sk_test');
        });
    }

    public function test_search_returns_empty_array_when_no_api_key_configured(): void
    {
        config([
            'services.radar.secret' => null,
            'services.radar.publishable' => null,
        ]);

        Http::fake();

        $service = new RadarAddressAutocompleteService;
        $addresses = $service->search('123 main');

        $this->assertSame([], $addresses);
        Http::assertNothingSent();
    }

    public function test_search_returns_empty_array_on_http_error(): void
    {
        config(['services.radar.secret' => 'prj_live_sk_test']);

        Http::fake([
            'api.radar.io/v1/search/autocomplete*' => Http::response(['error' => 'bad'], 500),
        ]);

        $service = new RadarAddressAutocompleteService;
        $addresses = $service->search('123 main');

        $this->assertSame([], $addresses);
    }

    public function test_controller_validates_query_and_returns_json(): void
    {
        $service = $this->createMock(RadarAddressAutocompleteService::class);
        $service->expects($this->once())
            ->method('search')
            ->with('123 main', null, 10)
            ->willReturn([
                ['street' => 'Main St', 'addressLabel' => '123 Main St'],
            ]);

        $controller = new AddressAutocompleteController($service);
        $response = $controller->search(Request::create('/address-autocomplete', 'GET', [
            'query' => '123 main',
        ]));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(
            [['street' => 'Main St', 'addressLabel' => '123 Main St']],
            $response->getData(true)['data'],
        );
    }

    public function test_controller_rejects_short_query(): void
    {
        $controller = new AddressAutocompleteController(new RadarAddressAutocompleteService);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $controller->search(Request::create('/address-autocomplete', 'GET', [
            'query' => 'ab',
        ]));
    }
}
