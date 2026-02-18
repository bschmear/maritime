<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Domain\ServiceTicket\Models\ServiceTicket;
use App\Mail\ServiceTicketApproved;
use App\Models\AccountSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class PublicController extends Controller
{
    public function review(Request $request, $uuid)
    {
        
        $ticket = ServiceTicket::where('uuid', $uuid)
            ->with([
                'customer',
                'subsidiary',
                'location',
                'assetUnit',
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

        // 1 = Digital drawn, 5 = Digital typed
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

        $this->sendApprovalConfirmation($ticket, $account);

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

    /**
     * Send approval confirmation email to the customer.
     */
    private function sendApprovalConfirmation(ServiceTicket $ticket, AccountSettings $account): void
    {
        $ticket->load(['customer', 'subsidiary', 'location', 'assetUnit', 'serviceItems']);

        $customerEmail = $ticket->customer->email ?? null;
        if (!$customerEmail) {
            return;
        }

        try {
            Mail::to($customerEmail)->send(new ServiceTicketApproved($ticket, $account));
        } catch (\Exception $e) {
            \Log::error("Failed to send approval confirmation for ticket {$ticket->service_ticket_number}: " . $e->getMessage());
        }
    }

    /**
     * Decode a base64 signature data URL and upload as PNG to S3.
     */
    private function storeSignatureImage(string $base64Data, string $uuid): ?string
    {
        if (!preg_match('/^data:image\/(\w+);base64,/', $base64Data, $matches)) {
            return null;
        }

        $extension = $matches[1];
        $decoded = base64_decode(substr($base64Data, strpos($base64Data, ',') + 1));
        if (!$decoded) {
            return null;
        }

        $filename = $uuid . '-signature.' . $extension;
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
            \Log::error('Failed to store signature image: ' . $e->getMessage());
            return null;
        }

        return $key;
    }

    /**
     * Resolve the logo URL: subsidiary logo > account logo.
     */
    private function resolveLogoUrl(ServiceTicket $ticket, AccountSettings $account): ?string
    {
        if ($ticket->subsidiary && $ticket->subsidiary->logo_url) {
            return $ticket->subsidiary->logo_url;
        }

        return $account->logo_url;
    }
}
