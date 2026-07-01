<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Actions;

use App\Domain\Shipment\Models\Shipment;
use App\Enums\Shipment\Status;
use App\Services\Integrations\EasyPostService;
use Illuminate\Validation\ValidationException;

class BuyShipmentRate
{
    public function __construct(
        private readonly EasyPostService $easyPost,
    ) {}

    public function __invoke(Shipment $shipment, string $rateId, bool $autoNotify = false): Shipment
    {
        if (! $this->easyPost->isConnected()) {
            throw ValidationException::withMessages([
                'easypost' => 'EasyPost is not connected for this workspace.',
            ]);
        }

        if ($shipment->status !== Status::Rated && $shipment->status !== Status::Draft) {
            throw ValidationException::withMessages([
                'status' => 'This shipment cannot be purchased in its current state.',
            ]);
        }

        if (! filled($shipment->easypost_shipment_id)) {
            $rateResult = app(RateShopShipment::class)($shipment);
            $shipment = $rateResult;
        }

        $buyResult = $this->easyPost->buyRate((string) $shipment->easypost_shipment_id, $rateId);
        if (! ($buyResult['success'] ?? false)) {
            throw ValidationException::withMessages([
                'rate_id' => $buyResult['message'] ?? 'Could not purchase the selected rate.',
            ]);
        }

        $this->easyPost->applyPurchasedShipment($shipment, $buyResult['shipment']);
        $shipment->save();

        if ($autoNotify) {
            app(SendShipmentNotification::class)($shipment->fresh());
        }

        return $shipment->fresh();
    }
}
