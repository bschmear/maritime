<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Contract\Models\Contract;
use App\Domain\Delivery\Models\Delivery;
use App\Domain\Estimate\Models\Estimate;
use App\Domain\ServiceTicket\Models\ServiceTicket;
use App\Enums\Contract\ContractStatus;
use App\Enums\Estimate\EstimateStatus;
use App\Http\Controllers\Controller;
use App\Models\AccountSettings;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class PublicController extends Controller
{
    public function __construct(private NotificationService $notifications) {}

    public function review(Request $request, $uuid)
    {
        $ticket = ServiceTicket::where('uuid', $uuid)
            ->with([
                'customer',
                'subsidiary',
                'location',
                'assetUnit' => function ($query) {
                    $query->with(['asset' => function ($q) {
                        $q->select(['id', 'display_name', 'year', 'make_id'])
                            ->with(['make' => function ($mq) {
                                $mq->select(['id', 'display_name']);
                            }]);
                    }]);
                },
                'serviceItems' => fn ($q) => $q->where('inactive', false)->orderBy('sort_order')->orderBy('id'),
            ])
            ->firstOrFail();

        $account = AccountSettings::getCurrent();
        $logoUrl = $this->resolveLogoUrl($ticket, $account);

        $recordArray = $ticket->toArray();
        $recordArray['created_at'] = $ticket->created_at?->toISOString();
        $recordArray['signed_at'] = $ticket->signed_at?->toISOString();
        $recordArray['declined_at'] = $ticket->declined_at?->toISOString();
        $recordArray['signature_url'] = $ticket->signature_url;

        $recordArray['service_items'] = $ticket->serviceItems->map(fn ($li) => [
            'id' => $li->id,
            'display_name' => $li->display_name,
            'description' => $li->description,
            'quantity' => (float) $li->quantity,
            'unit_price' => (float) $li->unit_price,
            'estimated_hours' => (float) ($li->estimated_hours ?? 0),
            'billable' => $li->billable,
            'warranty' => $li->warranty,
            'billing_type' => $li->billing_type,
        ])->values()->all();

        $enumOptions = [
            'billing_type' => \App\Enums\ServiceItem\BillingType::options(),
        ];

        return Inertia::render('Tenant/Public/ServiceTicketReview', [
            'record' => $recordArray,
            'account' => $account,
            'logoUrl' => $logoUrl,
            'enumOptions' => $enumOptions,
        ]);
    }

    public function approve(Request $request, $uuid)
    {
        $ticket = ServiceTicket::where('uuid', $uuid)->firstOrFail();

        if ($ticket->approved) {
            return back();
        }

        $request->validate([
            'signature_method' => 'required|in:draw,type',
            'signature_data' => 'required|string',
            'signed_name' => 'required|string|max:255',
            'consent' => 'required|accepted',
        ]);

        $account = AccountSettings::getCurrent();

        $items = $ticket->serviceItems()->where('inactive', false)->get();
        $itemsSnapshot = $items->map(fn ($item) => [
            'id' => $item->id,
            'display_name' => $item->display_name,
            'quantity' => (string) $item->quantity,
            'unit_price' => (string) $item->unit_price,
            'billing_type' => $item->billing_type,
            'estimated_hours' => (string) $item->estimated_hours,
        ])->toArray();

        $approvalHash = hash('sha256', json_encode([
            'ticket_id' => $ticket->id,
            'uuid' => $ticket->uuid,
            'estimated_total' => (string) $ticket->estimated_total,
            'ack_text' => $account->service_ticket_ack_text,
            'items' => $itemsSnapshot,
            'timestamp' => now()->toISOString(),
            'ip' => $request->ip(),
        ]));

        $signatureMethod = $request->signature_method === 'draw' ? 1 : 5;
        $signatureFile = null;
        if ($request->signature_method === 'draw') {
            $signatureFile = $this->storeSignatureImage($request->signature_data, $ticket->uuid);
        }

        $ticket->update([
            'approved' => true,
            'signed_at' => now(),
            'signed_ip' => $request->ip(),
            'signed_user_agent' => $request->userAgent(),
            'signed_name' => $request->signed_name,
            'customer_signature' => $request->signature_data,
            'signature_method' => $signatureMethod,
            'signature_hash' => $approvalHash,
            'signature_file' => $signatureFile,
        ]);

        $ticket->refresh();

        $this->notifications->sendServiceTicketCustomerConfirmation($ticket, $account);
        $this->notifications->notifyServiceTicketApproved($ticket, $account);

        return back();
    }

    public function decline(Request $request, $uuid)
    {
        $ticket = ServiceTicket::where('uuid', $uuid)->firstOrFail();

        if ($ticket->approved || $ticket->declined_at) {
            return back();
        }

        $request->validate([
            'decline_reason' => 'nullable|string|max:1000',
        ]);

        $ticket->update([
            'declined_at' => now(),
            'decline_reason' => $request->decline_reason,
        ]);

        return back();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Estimate Review / Approve / Decline
    // ─────────────────────────────────────────────────────────────────────────

    public function reviewEstimate(Request $request, string $uuid)
    {
        $estimate = Estimate::where('uuid', $uuid)
            ->with([
                'customer',
                'user',
                'opportunity',
                'primaryVersion.lineItems.addons',
            ])
            ->firstOrFail();

        $account = AccountSettings::getCurrent();
        $logoUrl = $account->logo_url ?? null;

        $recordArray = $estimate->toArray();
        $recordArray['approved_at'] = $estimate->approved_at?->toISOString();
        $recordArray['approval_note'] = $estimate->approval_note;
        $recordArray['declined_at'] = $estimate->declined_at?->toISOString();
        $recordArray['decline_reason'] = $estimate->decline_reason;
        $recordArray['issue_date'] = $estimate->issue_date?->toISOString();
        $recordArray['expiration_date'] = $estimate->expiration_date?->toISOString();

        $recordArray['line_items'] = $this->buildLineItems($estimate);
        $recordArray['subtotal'] = (float) ($estimate->primaryVersion?->subtotal ?? 0);
        $recordArray['tax'] = (float) ($estimate->primaryVersion?->tax ?? 0);
        $recordArray['total'] = (float) ($estimate->primaryVersion?->total ?? 0);

        return Inertia::render('Tenant/Public/EstimateReview', [
            'record' => $recordArray,
            'account' => $account,
            'logoUrl' => $logoUrl,
        ]);
    }

    public function approveEstimate(Request $request, string $uuid)
    {
        $estimate = Estimate::where('uuid', $uuid)->firstOrFail();

        if ($estimate->status == EstimateStatus::Approved->id() || $estimate->approved_at) {
            return back();
        }

        $request->validate([
            'consent' => 'required|accepted',
            'approval_note' => 'nullable|string|max:1000',
        ]);

        $account = AccountSettings::getCurrent();

        $estimate->update([
            'status' => EstimateStatus::Approved->id(),
            'approved_at' => now(),
            'signed_at' => now(),
            'approval_note' => $request->approval_note,
        ]);

        $estimate->refresh();

        $this->notifications->notifyEstimateApproved($estimate, $account);

        return back();
    }

    public function declineEstimate(Request $request, string $uuid)
    {
        $estimate = Estimate::where('uuid', $uuid)->firstOrFail();

        if ($estimate->status == EstimateStatus::Declined->id() || $estimate->declined_at) {
            return back();
        }

        $request->validate([
            'decline_reason' => 'required|string|max:1000',
        ]);

        $estimate->update([
            'status' => EstimateStatus::Declined->id(),
            'declined_at' => now(),
            'decline_reason' => $request->decline_reason,
        ]);

        $estimate->refresh();

        $this->notifications->notifyEstimateDeclined($estimate, AccountSettings::getCurrent());

        return back();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Delivery Review / Sign
    // ─────────────────────────────────────────────────────────────────────────

    public function reviewDelivery(Request $request, $uuid)
    {
        $delivery = Delivery::where('uuid', $uuid)->firstOrFail();

        $delivery->load([
            'customer',
            'subsidiary',
            'location',
            'assetUnit.asset.make',
            'checklistItems.category',
        ]);

        $account = AccountSettings::getCurrent();
        $logoUrl = $this->resolveLogoUrl($delivery, $account);

        $recordArray = [
            'id' => $delivery->id,
            'uuid' => $delivery->uuid,
            'customer_id' => $delivery->customer_id,
            'asset_unit_id' => $delivery->asset_unit_id,
            'work_order_id' => $delivery->work_order_id,
            'scheduled_at' => $delivery->scheduled_at?->toISOString(),
            'estimated_arrival_at' => $delivery->estimated_arrival_at?->toISOString(),
            'delivered_at' => $delivery->delivered_at?->toISOString(),
            'status' => $delivery->status,
            'technician_id' => $delivery->technician_id,
            'recipient_name' => $delivery->recipient_name,
            'signature_path' => $delivery->signature_path,
            'signed_at' => $delivery->signed_at?->toISOString(),
            'signed_ip' => $delivery->signed_ip,
            'signed_user_agent' => $delivery->signed_user_agent,
            'signature_file' => $delivery->signature_file,
            'signature_hash' => $delivery->signature_hash,
            'internal_notes' => $delivery->internal_notes,
            'customer_notes' => $delivery->customer_notes,
            'address_line_1' => $delivery->address_line_1,
            'address_line_2' => $delivery->address_line_2,
            'city' => $delivery->city,
            'state' => $delivery->state,
            'postal_code' => $delivery->postal_code,
            'country' => $delivery->country,
            'latitude' => $delivery->latitude,
            'longitude' => $delivery->longitude,
            'subsidiary_id' => $delivery->subsidiary_id,
            'location_id' => $delivery->location_id,
            'created_at' => $delivery->created_at?->toISOString(),
            'updated_at' => $delivery->updated_at?->toISOString(),
            'customer' => $delivery->customer?->toArray(),
            'subsidiary' => $delivery->subsidiary?->toArray(),
            'location' => $delivery->location?->toArray(),
            'assetUnit' => $delivery->assetUnit?->toArray(),
            'checklistItems' => $delivery->checklistItems->map->toArray()->toArray(),
            'signature_url' => $delivery->signature_url,
        ];

        return Inertia::render('Tenant/Public/DeliveryReview', [
            'record' => $recordArray,
            'account' => $account,
            'logoUrl' => $logoUrl,
            'enumOptions' => [],
        ]);
    }

    public function signDelivery(Request $request, $uuid)
    {
        $delivery = Delivery::where('uuid', $uuid)->firstOrFail();

        if ($delivery->signed_at) {
            return back();
        }

        $request->validate([
            'signature_method' => 'required|in:draw,type',
            'signature_data' => 'required|string',
            'signed_name' => 'required|string|max:255',
            'recipient_name' => 'nullable|string|max:255',
            'consent' => 'required|accepted',
        ]);

        $account = AccountSettings::getCurrent();

        $signatureFile = null;
        if ($request->signature_method === 'draw') {
            $signatureFile = $this->storeSignatureImage($request->signature_data, $delivery->uuid);
        }

        $delivery->update([
            'signed_at' => now(),
            'signed_ip' => $request->ip(),
            'signed_user_agent' => $request->userAgent(),
            'recipient_name' => $request->recipient_name,
            'signature_file' => $signatureFile,
            'signature_hash' => hash('sha256', json_encode([
                'delivery_id' => $delivery->id,
                'uuid' => $delivery->uuid,
                'signed_name' => $request->signed_name,
                'recipient_name' => $request->recipient_name,
                'timestamp' => now()->toISOString(),
                'ip' => $request->ip(),
            ])),
        ]);

        if (! $delivery->delivered_at) {
            $delivery->update(['delivered_at' => now()]);
        }

        $delivery->refresh();

        $this->notifications->notifyDeliverySigned($delivery, $account);

        return back();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Contract Review / Sign
    // ─────────────────────────────────────────────────────────────────────────

    public function reviewContract(Request $request, string $uuid)
    {
        $contract = Contract::where('uuid', $uuid)
            ->with([
                'customer',
                'transaction' => fn ($q) => $q
                    ->select([
                        'id', 'title', 'sequence', 'customer_name', 'customer_email', 'customer_phone',
                        'tax_rate', 'currency', 'subsidiary_id', 'location_id',
                        'billing_address_line1', 'billing_address_line2', 'billing_city',
                        'billing_state', 'billing_postal', 'billing_country',
                    ])
                    ->with([
                        'items' => fn ($q2) => $q2->with('addons')->orderBy('position')->orderBy('id'),
                        'subsidiary' => fn ($q2) => $q2->select(['id', 'display_name']),
                        'location' => fn ($q2) => $q2->select([
                            'id', 'display_name',
                            'address_line_1', 'address_line_2', 'city', 'state', 'postal_code',
                            'phone', 'email',
                        ]),
                    ]),
            ])
            ->firstOrFail();

        $account = AccountSettings::getCurrent();
        $logoUrl = $contract->transaction?->subsidiary?->logo_url ?? $account->logo_url;

        $recordArray = $contract->toArray();
        $recordArray['created_at'] = $contract->created_at?->toISOString();
        $recordArray['updated_at'] = $contract->updated_at?->toISOString();
        $recordArray['signed_at'] = $contract->signed_at?->toISOString();
        $recordArray['signature_url'] = $contract->signature_file
            ? Storage::disk('s3')->temporaryUrl($contract->signature_file, now()->addHours(2))
            : null;

        return Inertia::render('Tenant/Public/ContractReview', [
            'record' => $recordArray,
            'account' => $account,
            'logoUrl' => $logoUrl,
        ]);
    }

    public function signContract(Request $request, string $uuid)
    {
        $contract = Contract::where('uuid', $uuid)->firstOrFail();

        if ($contract->signed_at) {
            return back();
        }

        $request->validate([
            'signature_method' => 'required|in:draw,type',
            'signature_data' => 'required|string',
            'signed_name' => 'required|string|max:255',
            'consent' => 'required|accepted',
        ]);

        $account = AccountSettings::getCurrent();

        $signatureMethod = $request->signature_method === 'draw' ? 1 : 5;
        $signatureFile = null;
        if ($request->signature_method === 'draw') {
            $signatureFile = $this->storeSignatureImage($request->signature_data, $contract->uuid);
        }

        $signatureHash = hash('sha256', json_encode([
            'contract_id' => $contract->id,
            'uuid' => $contract->uuid,
            'total_amount' => (string) $contract->total_amount,
            'signed_name' => $request->signed_name,
            'timestamp' => now()->toISOString(),
            'ip' => $request->ip(),
        ]));

        $contract->update([
            'status' => ContractStatus::Signed->value,
            'signed_at' => now(),
            'signed_ip' => $request->ip(),
            'signed_user_agent' => $request->userAgent(),
            'signed_name' => $request->signed_name,
            'customer_signature' => $request->signature_method === 'type' ? $request->signature_data : null,
            'signature_method' => $signatureMethod,
            'signature_hash' => $signatureHash,
            'signature_file' => $signatureFile,
        ]);

        $contract->refresh();

        $this->notifications->notifyContractSigned($contract, $account);

        return back();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Shared Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function resolveLogoUrl($record, AccountSettings $account): ?string
    {
        if (isset($record->subsidiary) && $record->subsidiary?->logo_url) {
            return $record->subsidiary->logo_url;
        }

        return $account->logo_url;
    }

    private function storeSignatureImage(string $base64Data, string $uuid): ?string
    {
        if (! preg_match('/^data:image\/(\w+);base64,/', $base64Data, $matches)) {
            return null;
        }

        $extension = $matches[1];
        $decoded = base64_decode(substr($base64Data, strpos($base64Data, ',') + 1));
        if (! $decoded) {
            return null;
        }

        $filename = $uuid.'-signature.'.$extension;
        $key = "private/signatures/{$filename}";

        try {
            $s3Client = Storage::disk('s3')->getClient();
            $s3Client->putObject([
                'Bucket' => Storage::disk('s3')->getConfig()['bucket'],
                'Key' => $key,
                'Body' => $decoded,
                'ContentType' => "image/{$extension}",
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to store signature image: '.$e->getMessage());

            return null;
        }

        return $key;
    }

    private function buildLineItems(Estimate $estimate): array
    {
        if (! $estimate->primaryVersion) {
            return [];
        }

        return $estimate->primaryVersion->lineItems->map(fn ($li) => [
            'id' => $li->id,
            'name' => $li->name,
            'description' => $li->description,
            'quantity' => (float) $li->quantity,
            'unit_price' => (float) $li->unit_price,
            'discount' => (float) ($li->discount ?? 0),
            'line_total' => (float) $li->line_total,
            'addons' => $li->addons->map(fn ($a) => [
                'id' => $a->id,
                'name' => $a->name,
                'price' => (float) $a->price,
                'quantity' => (int) $a->quantity,
            ])->values()->all(),
        ])->values()->all();
    }
}
