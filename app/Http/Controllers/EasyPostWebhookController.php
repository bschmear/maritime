<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Shipment\Models\Shipment;
use App\Enums\Shipment\Status;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EasyPostWebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        $secret = config('services.easypost.webhook_secret');
        if (filled($secret)) {
            $header = (string) $request->header('X-Hmac-Signature', '');
            $payload = $request->getContent();
            $expected = base64_encode(hash_hmac('sha256', $payload, $secret, true));
            if (! hash_equals($expected, $header)) {
                abort(401, 'Invalid webhook signature.');
            }
        }

        $event = $request->input('description') ?? $request->input('result.object');
        $result = $request->input('result');

        if (! is_array($result)) {
            return response()->json(['received' => true]);
        }

        if (($result['object'] ?? null) === 'Tracker') {
            $this->handleTrackerUpdated($result);
        }

        return response()->json(['received' => true]);
    }

    /**
     * @param  array<string, mixed>  $tracker
     */
    private function handleTrackerUpdated(array $tracker): void
    {
        $trackerId = $tracker['id'] ?? null;
        $shipmentId = $tracker['shipment_id'] ?? null;
        $trackingCode = $tracker['tracking_code'] ?? null;
        $status = $tracker['status'] ?? null;
        $publicUrl = $tracker['public_url'] ?? null;

        $query = Shipment::query();
        if (filled($trackerId)) {
            $query->where('easypost_tracker_id', $trackerId);
        } elseif (filled($shipmentId)) {
            $query->where('easypost_shipment_id', $shipmentId);
        } elseif (filled($trackingCode)) {
            $query->where('tracking_code', $trackingCode);
        } else {
            return;
        }

        $shipment = $query->first();
        if ($shipment === null) {
            return;
        }

        $events = $shipment->tracking_events ?? [];
        $events[] = [
            'status' => $status,
            'message' => $tracker['status_detail'] ?? null,
            'datetime' => $tracker['updated_at'] ?? now()->toIso8601String(),
        ];

        $mappedStatus = $this->mapTrackerStatus(is_string($status) ? $status : null);

        $shipment->fill([
            'easypost_tracker_id' => $trackerId ?? $shipment->easypost_tracker_id,
            'tracking_code' => $trackingCode ?? $shipment->tracking_code,
            'public_tracking_url' => $publicUrl ?? $shipment->public_tracking_url,
            'tracking_events' => $events,
        ]);

        if ($mappedStatus !== null) {
            $shipment->status = $mappedStatus;
        }

        $shipment->save();
    }

    private function mapTrackerStatus(?string $status): ?Status
    {
        return match ($status) {
            'pre_transit', 'unknown' => Status::Purchased,
            'in_transit', 'out_for_delivery' => Status::InTransit,
            'delivered' => Status::Delivered,
            'return_to_sender', 'failure', 'cancelled' => Status::Cancelled,
            default => null,
        };
    }
}
