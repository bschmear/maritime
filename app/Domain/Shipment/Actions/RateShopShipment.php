<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Actions;

use App\Domain\Shipment\Models\Shipment;
use App\Enums\Shipment\Status;
use App\Services\Integrations\EasyPostService;
use Illuminate\Validation\ValidationException;

class RateShopShipment
{
    public function __construct(
        private readonly EasyPostService $easyPost,
    ) {}

    public function __invoke(Shipment $shipment): Shipment
    {
        if (! $this->easyPost->isConnected()) {
            throw ValidationException::withMessages([
                'easypost' => 'EasyPost is not connected for this workspace.',
            ]);
        }

        if ($shipment->isPurchased()) {
            throw ValidationException::withMessages([
                'status' => 'Rates cannot be refreshed after a label is purchased.',
            ]);
        }

        $params = $this->easyPost->buildShipmentParams(
            $shipment->from_address ?? [],
            $shipment->to_address ?? [],
            $shipment->parcel ?? [],
        );

        $result = $this->easyPost->createShipment(
            $params['from_address'],
            $params['to_address'],
            $params['parcel'],
        );

        if (! ($result['success'] ?? false)) {
            throw ValidationException::withMessages([
                'rates' => $result['message'] ?? 'Could not retrieve shipping rates.',
            ]);
        }

        $easypostShipment = $result['shipment'];
        $shipmentId = is_object($easypostShipment) ? $easypostShipment->id : ($easypostShipment['id'] ?? null);

        $shipment->fill([
            'easypost_shipment_id' => $shipmentId,
            'rates_snapshot' => $result['rates'] ?? [],
            'status' => Status::Rated,
        ]);
        $shipment->save();

        return $shipment->fresh();
    }
}
