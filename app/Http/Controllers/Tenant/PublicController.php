<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Domain\ServiceTicket\Models\ServiceTicket;
use App\Domain\Delivery\Models\Delivery;
use App\Domain\Estimate\Models\Estimate;
use App\Enums\Estimate\EstimateStatus;
use App\Mail\ServiceTicketApproved;
use App\Mail\EstimateApprovalNotification;
use App\Models\AccountSettings;
use App\Domain\Notification\Models\Notification;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
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

        $ticket->refresh();

        $this->sendApprovalConfirmation($ticket, $account);

        // Create notification and send email to designated user
        $this->notifyApproval($ticket, $account);

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
     * Create notification and send email to designated user when service ticket is approved.
     */
    private function notifyApproval(ServiceTicket $ticket, AccountSettings $account): void
    {
        try {
            // Get the user to notify
            $notifyUser = $this->getNotificationUser($account);

            if (!$notifyUser) {
                \Log::warning('No user found to notify for service ticket approval', [
                    'ticket_id' => $ticket->id,
                    'account_id' => $account->id ?? null
                ]);
                return;
            }

            // Generate PDF
            $pdfPath = $this->generateServiceTicketPdf($ticket, $account);

            // Create notification record
            Notification::create([
                'assigned_to_user_id' => $notifyUser->id,
                'type' => 'service_ticket_approved',
                'title' => 'Service Ticket Approved',
                'message' => "Service ticket #{$ticket->service_ticket_number} has been approved by {$ticket->customer->display_name}.",
                'route' => 'servicetickets.show',
                'route_params' => $ticket->id,
            ]);

            // Send email notification
            $this->sendApprovalNotificationEmail($ticket, $account, $notifyUser, $pdfPath);

        } catch (\Exception $e) {
            \Log::error('Failed to create approval notification', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Get the user to notify for service ticket approvals.
     */
    private function getNotificationUser(AccountSettings $account)
    {
        // If a specific user is set, use them
        if ($account->service_ticket_signed_notify_user_id) {
            return \App\Domain\User\Models\User::find($account->service_ticket_signed_notify_user_id);
        }

        // Default to account owner
        $tenant = tenant();
        if ($tenant) {
            $accountModel = Account::where('tenant_id', $tenant->id)->first();
            if ($accountModel && $accountModel->owner) {
                return $accountModel->owner;
            }
        }

        return null;
    }

    /**
     * Generate PDF of the service ticket.
     */
    private function generateServiceTicketPdf(ServiceTicket $ticket, AccountSettings $account): ?string
    {
        try {
            // Load all necessary relationships
            $ticket->load([
                'customer',
                'subsidiary',
                'location',
                'assetUnit.asset',
                'serviceItems' => function ($query) {
                    $query->where('inactive', false)->orderBy('sort_order');
                }
            ]);

            // Prepare data for PDF
            $pdfData = [
                'ticket' => $ticket,
                'account' => $account,
                'subsidiary' => $ticket->subsidiary,
                'logoUrl' => $this->resolveLogoUrl($ticket, $account),
            ];

            // Generate PDF
            $pdf = Pdf::loadView('pdfs.service-ticket', $pdfData);

            // Generate filename
            $filename = 'service-ticket-' . $ticket->service_ticket_number . '-' . now()->format('Y-m-d-H-i-s') . '.pdf';
            $path = 'pdfs/' . $filename;

            // Save PDF to storage
            Storage::disk('s3')->put($path, $pdf->output());

            // Create document record
            $document = \App\Domain\Document\Models\Document::create([
                'display_name' => "Service Ticket #{$ticket->service_ticket_number} - Signed",
                'file' => $path,
                'file_extension' => 'pdf',
                'file_size' => strlen($pdf->output()),
                'created_by_id' => null, // System generated
                'ai_status' => 'completed', // PDF doesn't need AI processing
            ]);

            return $path;

        } catch (\Exception $e) {
            \Log::error('Failed to generate service ticket PDF', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Send approval notification email to designated user.
     */
    private function sendApprovalNotificationEmail(ServiceTicket $ticket, AccountSettings $account, $notifyUser, ?string $pdfPath): void
    {
        try {
            Mail::to($notifyUser->email)->send(
                new \App\Mail\ServiceTicketApprovalNotification($ticket, $account, $notifyUser, $pdfPath)
            );
        } catch (\Exception $e) {
            \Log::error('Failed to send approval notification email', [
                'ticket_id' => $ticket->id,
                'user_id' => $notifyUser->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send approval confirmation email to the customer.
     */
    private function sendApprovalConfirmation(ServiceTicket $ticket, AccountSettings $account): void
    {
        $ticket->load(['customer']);

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
    private function resolveLogoUrl($record, AccountSettings $account): ?string
    {
        if (isset($record->subsidiary) && $record->subsidiary && $record->subsidiary->logo_url) {
            return $record->subsidiary->logo_url;
        }

        return $account->logo_url;
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
        $recordArray['signature_url'] = $estimate->signature_url;
        $recordArray['signed_at']     = $estimate->signed_at?->toISOString();
        $recordArray['declined_at']   = $estimate->declined_at?->toISOString();
        $recordArray['issue_date']    = $estimate->issue_date?->toISOString();
        $recordArray['expiration_date'] = $estimate->expiration_date?->toISOString();

        $lineItems = [];
        if ($estimate->primaryVersion) {
            $lineItems = $estimate->primaryVersion->lineItems->map(fn ($li) => [
                'id'          => $li->id,
                'name'        => $li->name,
                'description' => $li->description,
                'quantity'    => (float) $li->quantity,
                'unit_price'  => (float) $li->unit_price,
                'discount'    => (float) ($li->discount ?? 0),
                'line_total'  => (float) $li->line_total,
                'addons'      => $li->addons->map(fn ($a) => [
                    'id'       => $a->id,
                    'name'     => $a->name,
                    'price'    => (float) $a->price,
                    'quantity' => (int) $a->quantity,
                ])->values()->all(),
            ])->values()->all();
        }

        $recordArray['line_items'] = $lineItems;
        $recordArray['subtotal']   = (float) ($estimate->primaryVersion?->subtotal ?? 0);
        $recordArray['tax']        = (float) ($estimate->primaryVersion?->tax ?? 0);
        $recordArray['total']      = (float) ($estimate->primaryVersion?->total ?? 0);

        return Inertia::render('Tenant/Public/EstimateReview', [
            'record'  => $recordArray,
            'account' => $account,
            'logoUrl' => $logoUrl,
        ]);
    }

    public function approveEstimate(Request $request, string $uuid)
    {
        $estimate = Estimate::where('uuid', $uuid)->firstOrFail();

        if ($estimate->status == EstimateStatus::Approved->id() || $estimate->signed_at) {
            return back();
        }

        $request->validate([
            'signature_method' => 'required|in:draw,type',
            'signature_data'   => 'required|string',
            'signed_name'      => 'required|string|max:255',
            'consent'          => 'required|accepted',
        ]);

        $account = AccountSettings::getCurrent();

        $signatureFile = null;
        if ($request->signature_method === 'draw') {
            $signatureFile = $this->storeSignatureImage($request->signature_data, $estimate->uuid);
        }

        $approvalHash = hash('sha256', json_encode([
            'estimate_id' => $estimate->id,
            'uuid'        => $estimate->uuid,
            'total'       => (string) ($estimate->primaryVersion?->total ?? '0'),
            'timestamp'   => now()->toISOString(),
            'ip'          => $request->ip(),
        ]));

        $estimate->update([
            'status'            => EstimateStatus::Approved->id(),
            'signed_at'         => now(),
            'signed_ip'         => $request->ip(),
            'signed_user_agent' => $request->userAgent(),
            'signed_name'       => $request->signed_name,
            'signed_email'      => $estimate->customer?->email,
            'signature_hash'    => $approvalHash,
            'signature_file'    => $signatureFile,
        ]);

        $estimate->refresh();

        $this->notifyEstimateAction($estimate, $account, 'approved');

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
            'status'         => EstimateStatus::Declined->id(),
            'declined_at'    => now(),
            'decline_reason' => $request->decline_reason,
        ]);

        $estimate->refresh();

        $this->notifyEstimateAction($estimate, AccountSettings::getCurrent(), 'declined');

        return back();
    }

    private function notifyEstimateAction(Estimate $estimate, AccountSettings $account, string $action): void
    {
        try {
            $estimate->load(['user', 'customer', 'primaryVersion']);

            $salesperson = $estimate->user;
            if (!$salesperson) {
                \Log::warning('No salesperson found to notify for estimate action', [
                    'estimate_id' => $estimate->id,
                    'action'      => $action,
                ]);
                return;
            }

            $isApproved = $action === 'approved';

            Notification::create([
                'assigned_to_user_id' => $salesperson->id,
                'type'                => "estimate_{$action}",
                'title'               => $isApproved ? 'Estimate Approved' : 'Estimate Declined',
                'message'             => $isApproved
                    ? "Estimate {$estimate->display_name} has been approved by {$estimate->customer?->display_name}."
                    : "Estimate {$estimate->display_name} was declined by {$estimate->customer?->display_name}.",
                'route'               => 'estimates.show',
                'route_params'        => $estimate->id,
            ]);

            Mail::to($salesperson->email)->send(
                new EstimateApprovalNotification($estimate, $account, $salesperson, $action)
            );
        } catch (\Exception $e) {
            \Log::error('Failed to notify estimate action', [
                'estimate_id' => $estimate->id,
                'action'      => $action,
                'error'       => $e->getMessage(),
            ]);
        }
    }

    public function reviewDelivery(Request $request, $uuid)
    {
        $delivery = Delivery::where('uuid', $uuid)->firstOrFail();

        // Load relationships separately to ensure they're fresh
        $delivery->load([
            'customer',
            'subsidiary',
            'location',
            'assetUnit.asset.make',
            'checklistItems' => function ($q) {
                $q->with('category')->orderBy('category_id')->orderBy('sort_order');
            }
        ]);
// dd($delivery);
        $account = AccountSettings::getCurrent();
        $logoUrl = $this->resolveLogoUrl($delivery, $account);

        // Ensure all relationships are properly loaded and included in the response
        $delivery->load([
            'customer',
            'subsidiary',
            'location',
            'assetUnit.asset.make',
            'checklistItems.category'
        ]);

        // Manually build the record array to ensure all data is included
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
            'checklistItems' => $delivery->checklistItems->map(function ($item) {
                return $item->toArray();
            })->toArray(),
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

        // Mark delivery as delivered if not already
        if (!$delivery->delivered_at) {
            $delivery->update(['delivered_at' => now()]);
        }

        $delivery->refresh();

        return back();
    }
}
