<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\Contact\Models\Contact;
use App\Domain\Integration\Support\EasyPostIntegrationSettings;
use App\Domain\Shipment\Actions\BuyShipmentRate;
use App\Domain\Shipment\Actions\CreateShipment;
use App\Domain\Shipment\Actions\RateShopShipment;
use App\Domain\Shipment\Actions\SendShipmentNotification;
use App\Domain\Shipment\Models\Shipment;
use App\Domain\Vendor\Models\Vendor;
use App\Enums\Shipment\Status;
use App\Http\Controllers\Controller;
use App\Services\Integrations\EasyPostService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShipmentController extends Controller
{
    public function __construct(
        private readonly CreateShipment $createShipment,
        private readonly RateShopShipment $rateShopShipment,
        private readonly BuyShipmentRate $buyShipmentRate,
        private readonly SendShipmentNotification $sendShipmentNotification,
        private readonly EasyPostService $easyPost,
    ) {}

    public function index(Request $request): Response
    {
        $this->ensureEasyPostConnected();

        $shipments = Shipment::query()
            ->with([
                'contact:id,first_name,last_name,display_name,email',
                'vendor:id,display_name',
            ])
            ->orderByDesc('created_at')
            ->paginate(table_per_page($request))
            ->withQueryString()
            ->through(fn (Shipment $shipment) => $this->serializeShipment($shipment));

        return Inertia::render('Tenant/Shipment/Index', [
            'shipments' => $shipments,
            'statusOptions' => collect(Status::cases())->map(fn (Status $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ]),
        ]);
    }

    public function create(): Response
    {
        $this->ensureEasyPostConnected();

        return Inertia::render('Tenant/Shipment/Create', [
            'contacts' => Contact::query()
                ->select(['id', 'first_name', 'last_name', 'display_name', 'email', 'phone', 'mobile'])
                ->orderBy('display_name')
                ->limit(500)
                ->get(),
            'vendors' => Vendor::query()
                ->select(['id', 'display_name'])
                ->orderBy('display_name')
                ->limit(500)
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureEasyPostConnected();

        $shipment = ($this->createShipment)(
            $request->all(),
            current_tenant_profile()?->getKey(),
        );

        return redirect()->route('shipments.show', $shipment)->with('success', 'Shipment created.');
    }

    public function show(Shipment $shipment): Response
    {
        $this->ensureEasyPostConnected();

        $shipment->load([
            'contact:id,first_name,last_name,display_name,email,phone,mobile',
            'vendor:id,display_name',
            'createdBy:id,first_name,last_name',
        ]);

        return Inertia::render('Tenant/Shipment/Show', [
            'shipment' => $this->serializeShipment($shipment, detailed: true),
            'statusOptions' => collect(Status::cases())->map(fn (Status $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ]),
        ]);
    }

    public function rates(Shipment $shipment): RedirectResponse
    {
        $this->ensureEasyPostConnected();
        ($this->rateShopShipment)($shipment);

        return back()->with('success', 'Shipping rates updated.');
    }

    public function buy(Request $request, Shipment $shipment): RedirectResponse
    {
        $this->ensureEasyPostConnected();

        $validated = $request->validate([
            'rate_id' => ['required', 'string'],
            'auto_notify' => ['sometimes', 'boolean'],
        ]);

        ($this->buyShipmentRate)(
            $shipment,
            $validated['rate_id'],
            $request->boolean('auto_notify'),
        );

        return back()->with('success', 'Label purchased successfully.');
    }

    public function notify(Request $request, Shipment $shipment): RedirectResponse
    {
        $this->ensureEasyPostConnected();

        ($this->sendShipmentNotification)(
            $shipment,
            auth()->user(),
            $request->boolean('send_sms'),
        );

        return back()->with('success', 'Tracking notification sent.');
    }

    public function refund(Shipment $shipment): RedirectResponse
    {
        $this->ensureEasyPostConnected();

        if (! filled($shipment->easypost_shipment_id)) {
            return back()->withErrors(['refund' => 'No EasyPost shipment to refund.']);
        }

        $result = $this->easyPost->refund((string) $shipment->easypost_shipment_id);
        if (! ($result['success'] ?? false)) {
            return back()->withErrors(['refund' => $result['message'] ?? 'Refund failed.']);
        }

        $shipment->update(['status' => Status::Refunded]);

        return back()->with('success', 'Shipment refunded.');
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeShipment(Shipment $shipment, bool $detailed = false): array
    {
        $data = [
            'id' => $shipment->id,
            'uuid' => $shipment->uuid,
            'display_name' => $shipment->display_name,
            'status' => $shipment->status?->value,
            'status_label' => $shipment->status?->label(),
            'recipient_name' => $shipment->recipient_name,
            'recipient_email' => $shipment->recipient_email,
            'recipient_type' => $shipment->contact_id ? 'contact' : 'vendor',
            'contact' => $shipment->contact,
            'vendor' => $shipment->vendor,
            'carrier' => $shipment->carrier,
            'service' => $shipment->service,
            'tracking_code' => $shipment->tracking_code,
            'rate_cents' => $shipment->rate_cents,
            'purchased_at' => $shipment->purchased_at?->toIso8601String(),
            'notified_at' => $shipment->notified_at?->toIso8601String(),
            'created_at' => $shipment->created_at?->toIso8601String(),
        ];

        if ($detailed) {
            $data = array_merge($data, [
                'from_address' => $shipment->from_address,
                'to_address' => $shipment->to_address,
                'parcel' => $shipment->parcel,
                'rates_snapshot' => $shipment->rates_snapshot ?? [],
                'label_url' => $shipment->label_url,
                'public_tracking_url' => $shipment->public_tracking_url,
                'tracking_events' => $shipment->tracking_events ?? [],
                'notes' => $shipment->notes,
                'created_by' => $shipment->createdBy,
                'track_url' => route('shipments.track', ['uuid' => $shipment->uuid]),
            ]);
        }

        return $data;
    }

    private function ensureEasyPostConnected(): void
    {
        if (! EasyPostIntegrationSettings::forCurrentTenant()->isConnected()) {
            abort(403, 'EasyPost must be connected and enabled before managing shipments.');
        }
    }
}
