<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Domain\Integration\Support\EasyPostIntegrationSettings;
use App\Domain\Shipment\Models\Shipment;
use EasyPost\EasyPostClient;
use EasyPost\Exception\General\EasyPostException;
use RuntimeException;

class EasyPostService
{
    private ?EasyPostIntegrationSettings $settings = null;

    public function isConnected(): bool
    {
        return $this->settings()->isConnected();
    }

    /**
     * @return array{success: bool, message: string, user_id?: string}
     */
    public function testConnection(?string $apiKey = null): array
    {
        try {
            $client = $this->client($apiKey);
            $user = $client->user->retrieveMe();

            $userId = is_array($user) ? ($user['id'] ?? null) : ($user->id ?? null);

            return [
                'success' => true,
                'message' => 'Connected to EasyPost successfully.',
                'user_id' => $userId,
            ];
        } catch (EasyPostException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $fromAddress
     * @param  array<string, mixed>  $toAddress
     * @param  array<string, mixed>  $parcel
     * @return array{success: bool, message?: string, shipment?: mixed, rates?: list<array<string, mixed>>}
     */
    public function createShipment(array $fromAddress, array $toAddress, array $parcel): array
    {
        $this->ensureConnected();

        try {
            $client = $this->client();
            $shipment = $client->shipment->create([
                'from_address' => $fromAddress,
                'to_address' => $toAddress,
                'parcel' => $parcel,
            ]);

            return [
                'success' => true,
                'shipment' => $shipment,
                'rates' => $this->normalizeRates($shipment->rates ?? []),
            ];
        } catch (EasyPostException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{success: bool, message?: string, shipment?: mixed}
     */
    public function buyRate(string $easypostShipmentId, string $rateId): array
    {
        $this->ensureConnected();

        try {
            $client = $this->client();
            $shipment = $client->shipment->buy($easypostShipmentId, ['rate' => ['id' => $rateId]]);

            return [
                'success' => true,
                'shipment' => $shipment,
            ];
        } catch (EasyPostException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{success: bool, message?: string, shipment?: mixed}
     */
    public function refund(string $easypostShipmentId): array
    {
        $this->ensureConnected();

        try {
            $client = $this->client();
            $shipment = $client->shipment->refund($easypostShipmentId);

            return [
                'success' => true,
                'shipment' => $shipment,
            ];
        } catch (EasyPostException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{success: bool, message?: string, tracker?: mixed}
     */
    public function retrieveTracker(string $trackerId): array
    {
        $this->ensureConnected();

        try {
            $client = $this->client();
            $tracker = $client->tracker->retrieve($trackerId);

            return [
                'success' => true,
                'tracker' => $tracker,
            ];
        } catch (EasyPostException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $fromAddress
     * @param  array<string, mixed>  $toAddress
     * @param  array<string, mixed>  $parcel
     * @return array<string, mixed>
     */
    public function buildShipmentParams(array $fromAddress, array $toAddress, array $parcel): array
    {
        return [
            'from_address' => $this->normalizeAddress($fromAddress),
            'to_address' => $this->normalizeAddress($toAddress),
            'parcel' => [
                'length' => (float) ($parcel['length'] ?? 0),
                'width' => (float) ($parcel['width'] ?? 0),
                'height' => (float) ($parcel['height'] ?? 0),
                'weight' => (float) ($parcel['weight'] ?? 0),
            ],
        ];
    }

    /**
     * @param  mixed  $easypostShipment
     */
    public function applyPurchasedShipment(Shipment $shipment, mixed $easypostShipment): void
    {
        $tracker = is_object($easypostShipment) ? ($easypostShipment->tracker ?? null) : ($easypostShipment['tracker'] ?? null);
        $postageLabel = is_object($easypostShipment) ? ($easypostShipment->postage_label ?? null) : ($easypostShipment['postage_label'] ?? null);
        $selectedRate = is_object($easypostShipment) ? ($easypostShipment->selected_rate ?? null) : ($easypostShipment['selected_rate'] ?? null);

        $trackingCode = is_object($tracker) ? ($tracker->tracking_code ?? null) : ($tracker['tracking_code'] ?? null);
        $trackerId = is_object($tracker) ? ($tracker->id ?? null) : ($tracker['id'] ?? null);
        $publicUrl = is_object($tracker) ? ($tracker->public_url ?? null) : ($tracker['public_url'] ?? null);
        $labelUrl = is_object($postageLabel) ? ($postageLabel->label_url ?? null) : ($postageLabel['label_url'] ?? null);
        $carrier = is_object($selectedRate) ? ($selectedRate->carrier ?? null) : ($selectedRate['carrier'] ?? null);
        $service = is_object($selectedRate) ? ($selectedRate->service ?? null) : ($selectedRate['service'] ?? null);
        $rateCents = is_object($selectedRate) ? ($selectedRate->rate ?? null) : ($selectedRate['rate'] ?? null);

        $shipment->fill([
            'tracking_code' => $trackingCode,
            'easypost_tracker_id' => $trackerId,
            'public_tracking_url' => $publicUrl,
            'label_url' => $labelUrl,
            'carrier' => $carrier,
            'service' => $service,
            'rate_cents' => $rateCents !== null ? (int) round((float) $rateCents * 100) : null,
            'status' => \App\Enums\Shipment\Status::Purchased,
            'purchased_at' => now(),
        ]);
    }

  /**
     * @param  list<mixed>  $rates
     * @return list<array<string, mixed>>
     */
    public function normalizeRates(array $rates): array
    {
        $normalized = [];

        foreach ($rates as $rate) {
            $normalized[] = [
                'id' => is_object($rate) ? $rate->id : ($rate['id'] ?? null),
                'carrier' => is_object($rate) ? $rate->carrier : ($rate['carrier'] ?? null),
                'service' => is_object($rate) ? $rate->service : ($rate['service'] ?? null),
                'rate' => is_object($rate) ? $rate->rate : ($rate['rate'] ?? null),
                'currency' => is_object($rate) ? ($rate->currency ?? 'USD') : ($rate['currency'] ?? 'USD'),
                'delivery_days' => is_object($rate) ? ($rate->delivery_days ?? null) : ($rate['delivery_days'] ?? null),
                'est_delivery_days' => is_object($rate) ? ($rate->est_delivery_days ?? null) : ($rate['est_delivery_days'] ?? null),
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $address
     * @return array<string, mixed>
     */
    public function normalizeAddress(array $address): array
    {
        return array_filter([
            'name' => $address['name'] ?? null,
            'company' => $address['company'] ?? null,
            'street1' => $address['street1'] ?? null,
            'street2' => $address['street2'] ?? null,
            'city' => $address['city'] ?? null,
            'state' => $address['state'] ?? null,
            'zip' => $address['zip'] ?? null,
            'country' => $address['country'] ?? 'US',
            'phone' => $address['phone'] ?? null,
            'email' => $address['email'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    private function ensureConnected(): void
    {
        if (! $this->isConnected()) {
            throw new RuntimeException('EasyPost is not connected for this workspace.');
        }
    }

    private function client(?string $apiKey = null): EasyPostClient
    {
        $key = $apiKey ?? $this->settings()->apiKey();

        if (! filled($key)) {
            throw new RuntimeException('EasyPost API key is not configured.');
        }

        return new EasyPostClient($key);
    }

    private function settings(): EasyPostIntegrationSettings
    {
        return $this->settings ??= EasyPostIntegrationSettings::forCurrentTenant();
    }
}
