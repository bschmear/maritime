<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Shipment\Models\Shipment;
use App\Enums\Shipment\Status;
use App\Services\Integrations\EasyPostService;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EasyPostShipmentFlowTest extends TestCase
{
    #[Test]
    public function it_applies_purchased_shipment_fields_from_easypost_response(): void
    {
        $service = new EasyPostService;

        $shipment = new Shipment([
            'status' => Status::Rated,
            'from_address' => ['street1' => '1 Main'],
            'to_address' => ['street1' => '2 Oak'],
            'parcel' => ['weight' => 16],
        ]);

        $easypostShipment = (object) [
            'selected_rate' => (object) [
                'carrier' => 'USPS',
                'service' => 'Priority',
                'rate' => '12.50',
            ],
            'tracker' => (object) [
                'id' => 'trk_123',
                'tracking_code' => '9400111899223197428490',
                'public_url' => 'https://track.easypost.com/example',
            ],
            'postage_label' => (object) [
                'label_url' => 'https://easypost-files.s3-us-west-2.amazonaws.com/files/postage_label/label.pdf',
            ],
        ];

        $service->applyPurchasedShipment($shipment, $easypostShipment);

        $this->assertSame(Status::Purchased, $shipment->status);
        $this->assertSame('USPS', $shipment->carrier);
        $this->assertSame('Priority', $shipment->service);
        $this->assertSame(1250, $shipment->rate_cents);
        $this->assertSame('9400111899223197428490', $shipment->tracking_code);
        $this->assertNotNull($shipment->purchased_at);
    }

    #[Test]
    public function easy_post_service_can_be_mocked_for_rate_shopping(): void
    {
        $mock = Mockery::mock(EasyPostService::class);
        $mock->shouldReceive('createShipment')->once()->andReturn([
            'success' => true,
            'shipment' => (object) ['id' => 'shp_123', 'rates' => []],
            'rates' => [
                ['id' => 'rate_1', 'carrier' => 'USPS', 'service' => 'Ground', 'rate' => '8.00'],
            ],
        ]);

        $this->app->instance(EasyPostService::class, $mock);

        $service = $this->app->make(EasyPostService::class);
        $result = $service->createShipment([], [], []);

        $this->assertTrue($result['success']);
        $this->assertSame('rate_1', $result['rates'][0]['id']);
    }
}
