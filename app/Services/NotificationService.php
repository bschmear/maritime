<?php

namespace App\Services;

use App\Domain\Contact\Models\Contact;
use App\Domain\Contract\Models\Contract;
use App\Domain\Delivery\Models\Delivery;
use App\Domain\Delivery\Support\DeliveryApproverResolver;
use App\Domain\Document\Actions\CreateDocument;
use App\Domain\Document\Models\Document;
use App\Domain\DocumentRequest\Models\DocumentRequest;
use App\Domain\Estimate\Models\Estimate;
use App\Domain\Financing\Models\Financing;
use App\Domain\Notification\Models\Notification;
use App\Domain\Opportunity\Models\Opportunity;
use App\Domain\Opportunity\Models\OpportunityFeatureRequest;
use App\Domain\ServiceTicket\Models\ServiceTicket;
use App\Domain\User\Models\User;
use App\Domain\WarrantyClaim\Models\WarrantyClaim;
use App\Domain\WarrantyClaim\Support\LogWarrantyClaimVendorEmailCommunication;
use App\Domain\WorkOrder\Models\WorkOrder;
use App\Mail\ContractSignedNotification;
use App\Mail\DeliveryRequestReviewedMail;
use App\Mail\DeliveryRequestSubmittedMail;
use App\Mail\EstimateApprovalNotification;
use App\Mail\OpportunityFeatureRequestSubmittedMail;
use App\Mail\ServiceTicketApprovalNotification;
use App\Mail\ServiceTicketApproved;
use App\Mail\WarrantyClaimSentToVendor;
use App\Models\Account;
use App\Models\AccountSettings;
use App\Services\Mail\TenantMailService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class NotificationService
{
    public function __construct(
        private readonly TenantMailService $tenantMail,
        private readonly LogWarrantyClaimVendorEmailCommunication $logWarrantyClaimVendorEmailCommunication,
    ) {}
    // ─────────────────────────────────────────────────────────────────────────
    // Work Order
    // ─────────────────────────────────────────────────────────────────────────

    public function notifyWorkOrderPendingManagerApproval(WorkOrder $workOrder): void
    {
        try {
            $workOrder->loadMissing(['managerUser', 'assignedUser']);

            $manager = $workOrder->managerUser;
            if (! $manager) {
                Log::warning('No manager assigned for work order pending approval notification', [
                    'work_order_id' => $workOrder->id,
                ]);

                return;
            }

            $label = $workOrder->display_name;
            $technician = $workOrder->assignedUser?->display_name ?? 'Technician';

            Notification::create([
                'assigned_to_user_id' => $manager->id,
                'type' => 'work_order_pending_approval',
                'title' => 'Work Order Pending Approval',
                'message' => "{$label} submitted by {$technician} is pending manager approval.",
                'route' => 'workorders.show',
                'route_params' => ['workorder' => $workOrder->id],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to notify work order pending manager approval', [
                'work_order_id' => $workOrder->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Service Ticket
    // ─────────────────────────────────────────────────────────────────────────

    public function notifyServiceTicketApproved(ServiceTicket $ticket, AccountSettings $account): void
    {
        try {
            $notifyUser = $this->getServiceTicketNotificationUser($account);

            if (! $notifyUser) {
                Log::warning('No user found to notify for service ticket approval', [
                    'ticket_id' => $ticket->id,
                ]);

                return;
            }

            $pdfPath = $this->generateServiceTicketPdf($ticket, $account);

            Notification::create([
                'assigned_to_user_id' => $notifyUser->id,
                'type' => 'service_ticket_approved',
                'title' => 'Service Ticket Approved',
                'message' => "Service ticket #{$ticket->service_ticket_number} has been approved by {$ticket->customer->display_name}.",
                'route' => 'servicetickets.show',
                'route_params' => ['serviceticket' => $ticket->id],
            ]);

            $this->sendServiceTicketApprovalEmail($ticket, $account, $notifyUser, $pdfPath);

        } catch (\Exception $e) {
            Log::error('Failed to notify service ticket approved', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    public function sendServiceTicketCustomerConfirmation(ServiceTicket $ticket, AccountSettings $account, ?Authenticatable $actor = null): void
    {
        $ticket->load(['customer']);

        $customerEmail = $ticket->customer->email ?? null;
        $mailable = new ServiceTicketApproved($ticket, $account);

        if (! $this->tenantMail->canSend($customerEmail, $mailable, $actor)) {
            return;
        }

        try {
            $this->tenantMail->send($customerEmail, $mailable, $actor);
        } catch (\Exception $e) {
            Log::error("Failed to send approval confirmation for ticket {$ticket->service_ticket_number}: ".$e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Warranty claim → vendor contacts
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @param  iterable<int, Contact>  $contacts
     */
    public function sendWarrantyClaimToVendorContacts(WarrantyClaim $claim, AccountSettings $account, iterable $contacts, ?Authenticatable $actor = null): void
    {
        $tenant = tenant();
        $domain = $tenant?->domains->first()?->domain;
        $reviewPath = route('warranty-claims.review', ['uuid' => $claim->uuid], false);
        $reviewUrl = $domain ? 'https://'.$domain.$reviewPath : url($reviewPath);
        $vendorPortalLoginUrl = $domain ? 'https://'.$domain.'/vendor/portal/login' : url('/vendor/portal/login');

        $vendorRecipients = [];
        foreach ($contacts as $contact) {
            if (! $contact instanceof Contact) {
                continue;
            }
            $email = $contact->email ?: $contact->secondary_email;
            if ($email === null || trim((string) $email) === '') {
                continue;
            }
            $vendorRecipients[] = ['email' => $email, 'contact' => $contact];
        }

        if ($vendorRecipients === []) {
            return;
        }

        $sandboxProbe = new WarrantyClaimSentToVendor(
            $claim,
            $account,
            $vendorRecipients[0]['contact'],
            $reviewUrl,
            $vendorPortalLoginUrl,
        );

        if ($this->tenantMail->shouldRedirectToSandboxActor($sandboxProbe, $actor)) {
            try {
                $this->tenantMail->send(
                    $vendorRecipients[0]['email'],
                    $sandboxProbe,
                    $actor,
                );
                foreach ($vendorRecipients as $recipient) {
                    ($this->logWarrantyClaimVendorEmailCommunication)(
                        $claim,
                        $recipient['contact'],
                        $recipient['email'],
                        $account,
                        $reviewUrl,
                        $vendorPortalLoginUrl,
                        $actor,
                        true,
                    );
                }
            } catch (\Exception $e) {
                Log::error('Failed to send warranty claim to vendor contact (sandbox)', [
                    'claim_id' => $claim->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return;
        }

        foreach ($vendorRecipients as $recipient) {
            try {
                $mailable = new WarrantyClaimSentToVendor(
                    $claim,
                    $account,
                    $recipient['contact'],
                    $reviewUrl,
                    $vendorPortalLoginUrl,
                );
                $this->tenantMail->send($recipient['email'], $mailable, $actor);
                ($this->logWarrantyClaimVendorEmailCommunication)(
                    $claim,
                    $recipient['contact'],
                    $recipient['email'],
                    $account,
                    $reviewUrl,
                    $vendorPortalLoginUrl,
                    $actor,
                );
            } catch (\Exception $e) {
                Log::error('Failed to send warranty claim to vendor contact', [
                    'claim_id' => $claim->id,
                    'contact_id' => $recipient['contact']->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * In-app notification when a manufacturer portal contact saves non-empty line feedback.
     *
     * @param  list<array{id: int, excerpt: string}>  $feedbackLines
     */
    public function notifyWarrantyClaimVendorLineFeedback(
        User $assignedUser,
        WarrantyClaim $claim,
        Contact $vendorContact,
        array $feedbackLines,
    ): void {
        if ($feedbackLines === []) {
            return;
        }

        $claimRef = $claim->display_name ?? ('Claim #'.$claim->id);
        $who = $vendorContact->display_name
            ?: trim(implode(' ', array_filter([(string) $vendorContact->first_name, (string) $vendorContact->last_name])))
            ?: 'A manufacturer contact';
        $count = count($feedbackLines);
        $preview = $feedbackLines[0]['excerpt'] ?? '';
        $message = $count === 1
            ? "{$who} updated line feedback on {$claimRef}: {$preview}"
            : "{$who} updated feedback on {$count} lines on {$claimRef}. Latest: {$preview}";

        try {
            Notification::create([
                'assigned_to_user_id' => $assignedUser->id,
                'type' => 'warranty_claim_vendor_line_feedback',
                'title' => 'Vendor line feedback — '.$claimRef,
                'message' => $message,
                'route' => 'warrantyclaims.show',
                'route_params' => ['warrantyclaim' => $claim->id],
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to notify assigned user of warranty vendor line feedback', [
                'claim_id' => $claim->id,
                'user_id' => $assignedUser->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Estimate
    // ─────────────────────────────────────────────────────────────────────────

    public function notifyEstimateApproved(Estimate $estimate, AccountSettings $account): void
    {
        $this->notifyEstimateAction($estimate, $account, 'approved');
    }

    public function notifyEstimateDeclined(Estimate $estimate, AccountSettings $account): void
    {
        $this->notifyEstimateAction($estimate, $account, 'declined');
    }

    private function notifyEstimateAction(Estimate $estimate, AccountSettings $account, string $action): void
    {
        try {
            $estimate->load(['user', 'customer', 'primaryVersion']);

            $salesperson = $estimate->user;

            if (! $salesperson) {
                Log::warning('No salesperson found to notify for estimate action', [
                    'estimate_id' => $estimate->id,
                    'action' => $action,
                ]);

                return;
            }

            $isApproved = $action === 'approved';

            Notification::create([
                'assigned_to_user_id' => $salesperson->id,
                'type' => "estimate_{$action}",
                'title' => $isApproved ? 'Estimate Approved' : 'Estimate Declined',
                'message' => $isApproved
                    ? "Estimate {$estimate->display_name} has been approved by {$estimate->customer?->display_name}."
                    : "Estimate {$estimate->display_name} was declined by {$estimate->customer?->display_name}.",
                'route' => 'estimates.show',
                'route_params' => ['estimate' => $estimate->id],
            ]);

            $mailable = new EstimateApprovalNotification($estimate, $account, $salesperson, $action);
            $this->tenantMail->send($salesperson->email, $mailable);

        } catch (\Exception $e) {
            Log::error('Failed to notify estimate action', [
                'estimate_id' => $estimate->id,
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function notifyDeliverySigned(Delivery $delivery, AccountSettings $account): void
    {
        try {
            $delivery->load(['customer', 'assetUnit.asset', 'technician']);

            $notifyUser = $this->getDeliveryNotificationUser($delivery, $account);

            if (! $notifyUser) {
                Log::warning('No user found to notify for delivery signed', [
                    'delivery_id' => $delivery->id,
                ]);

                return;
            }

            $assetName = $delivery->assetUnit?->asset?->display_name ?? 'Unknown Asset';
            $customerName = $delivery->customer?->display_name ?? 'Unknown Customer';
            $recipientName = $delivery->recipient_name ?? $customerName;

            Notification::create([
                'assigned_to_user_id' => $notifyUser->id,
                'type' => 'delivery_signed',
                'title' => 'Delivery Signed',
                'message' => "Delivery for {$assetName} has been signed by {$recipientName} ({$customerName}).",
                'route' => 'deliveries.show',
                'route_params' => ['delivery' => $delivery->id],
            ]);

            // Optionally send an email — add a Mailable here when ready
            // Mail::to($notifyUser->email)->send(new DeliverySignedNotification($delivery, $account, $notifyUser));

        } catch (\Exception $e) {
            Log::error('Failed to notify delivery signed', [
                'delivery_id' => $delivery->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    public function notifyDeliveryRequestSubmitted(Delivery $delivery, AccountSettings $account): void
    {
        try {
            $delivery->loadMissing(['location', 'requestedBy', 'customer']);
            $approver = DeliveryApproverResolver::forLocation($delivery->location);

            if (! $approver) {
                Log::warning('No approver found for delivery request submission', [
                    'delivery_id' => $delivery->id,
                    'location_id' => $delivery->location_id,
                ]);

                return;
            }

            $requesterName = $delivery->requestedBy?->display_name ?? 'A team member';
            $locationName = $delivery->location?->display_name ?? 'location';

            Notification::create([
                'assigned_to_user_id' => $approver->id,
                'type' => 'delivery_request_submitted',
                'title' => 'Delivery Request Submitted',
                'message' => "{$requesterName} submitted {$delivery->display_name} departing from {$locationName}.",
                'route' => 'deliveries.show',
                'route_params' => ['delivery' => $delivery->id],
            ]);

            $email = $approver->email ?? null;
            if ($email !== null && trim((string) $email) !== '') {
                $mailable = new DeliveryRequestSubmittedMail($delivery, $account, $approver);
                $this->tenantMail->send($email, $mailable);
            }
        } catch (\Exception $e) {
            Log::error('Failed to notify delivery request submitted', [
                'delivery_id' => $delivery->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function notifyDeliveryRequestReviewed(Delivery $delivery, AccountSettings $account): void
    {
        try {
            $delivery->loadMissing(['requestedBy', 'reviewedBy', 'customer', 'location']);

            $notifyUser = $delivery->requestedBy;
            if (! $notifyUser) {
                Log::warning('No requester found for delivery request review notification', [
                    'delivery_id' => $delivery->id,
                ]);

                return;
            }

            $decisionLabel = match ($delivery->review_decision) {
                'approved' => 'approved',
                'denied' => 'denied',
                'reschedule_requested' => 'marked for reschedule',
                default => 'updated',
            };

            Notification::create([
                'assigned_to_user_id' => $notifyUser->id,
                'type' => 'delivery_request_reviewed',
                'title' => 'Delivery Request Reviewed',
                'message' => "Your delivery request {$delivery->display_name} was {$decisionLabel}.",
                'route' => 'deliveries.show',
                'route_params' => ['delivery' => $delivery->id],
            ]);

            $email = $notifyUser->email ?? null;
            if ($email !== null && trim((string) $email) !== '') {
                $mailable = new DeliveryRequestReviewedMail($delivery, $account, $notifyUser);
                $this->tenantMail->send($email, $mailable);
            }
        } catch (\Exception $e) {
            Log::error('Failed to notify delivery request reviewed', [
                'delivery_id' => $delivery->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Contract
    // ─────────────────────────────────────────────────────────────────────────

    public function notifyContractSigned(Contract $contract, AccountSettings $account): void
    {
        try {
            $contract->loadMissing(['customer', 'transaction.user']);

            $notifyUser = $contract->transaction?->user ?? $this->getAccountOwner();

            if (! $notifyUser) {
                Log::warning('No user found to notify for contract signed', [
                    'contract_id' => $contract->id,
                ]);

                return;
            }

            Notification::create([
                'assigned_to_user_id' => $notifyUser->id,
                'type' => 'contract_signed',
                'title' => 'Contract Signed',
                'message' => "Contract {$contract->contract_number} has been signed by {$contract->customer?->display_name}.",
                'route' => 'contracts.show',
                'route_params' => ['contract' => $contract->id],
            ]);

            $mailable = new ContractSignedNotification($contract, $account, $notifyUser);
            $this->tenantMail->send($notifyUser->email, $mailable);

        } catch (\Exception $e) {
            Log::error('Failed to notify contract signed', [
                'contract_id' => $contract->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Opportunity — customer feature request form
    // ─────────────────────────────────────────────────────────────────────────

    public function notifyOpportunityFeatureRequestSubmitted(
        Opportunity $opportunity,
        OpportunityFeatureRequest $submission,
        AccountSettings $account
    ): void {
        try {
            $opportunity->loadMissing(['customer', 'salesperson']);

            $notifyUser = $opportunity->salesperson ?? $this->getAccountOwner();

            if (! $notifyUser) {
                Log::warning('No user found to notify for opportunity feature request submission', [
                    'opportunity_id' => $opportunity->id,
                    'submission_id' => $submission->id,
                ]);

                return;
            }

            $customerName = $opportunity->customer?->display_name ?? 'Customer';
            $assetLabel = $submission->asset_display_name ?? 'Asset';

            Notification::create([
                'assigned_to_user_id' => $notifyUser->id,
                'type' => 'opportunity_feature_request_submitted',
                'title' => 'Customer submitted feature request',
                'message' => "{$submission->signer_name} submitted choices for {$assetLabel} on {$opportunity->display_name} ({$customerName}).",
                'route' => 'opportunities.show',
                'route_params' => ['opportunity' => $opportunity->id],
            ]);

            $email = $notifyUser->email ?? null;
            if ($email !== null && trim((string) $email) !== '') {
                $mailable = new OpportunityFeatureRequestSubmittedMail($opportunity, $submission, $account, $notifyUser);
                $this->tenantMail->send($email, $mailable);
            }
        } catch (\Exception $e) {
            Log::error('Failed to notify opportunity feature request submission', [
                'opportunity_id' => $opportunity->id,
                'submission_id' => $submission->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    public function notifyDocumentRequestFulfilled(DocumentRequest $documentRequest, Document $document): void
    {
        try {
            $notifyUser = $documentRequest->requested_by_user_id
                ? User::find($documentRequest->requested_by_user_id)
                : null;

            if (! $notifyUser) {
                $notifyUser = $this->getAccountOwner();
            }

            if (! $notifyUser) {
                Log::warning('No user found to notify for document request fulfillment', [
                    'document_request_id' => $documentRequest->id,
                ]);

                return;
            }

            $contactName = $documentRequest->contact?->display_name ?? 'Customer';

            Notification::create([
                'assigned_to_user_id' => $notifyUser->id,
                'type' => 'document_request_fulfilled',
                'title' => 'Document request fulfilled',
                'message' => "{$contactName} uploaded \"{$documentRequest->title}\".",
                'route' => 'contacts.show',
                'route_params' => ['contact' => $documentRequest->contact_id],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to notify document request fulfilled', [
                'document_request_id' => $documentRequest->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // User Resolution
    // ─────────────────────────────────────────────────────────────────────────

    private function getServiceTicketNotificationUser(AccountSettings $account): ?User
    {
        if ($account->service_ticket_signed_notify_user_id) {
            return User::find($account->service_ticket_signed_notify_user_id);
        }

        return $this->getAccountOwner();
    }

    /**
     * For deliveries: prefer the assigned technician, fall back to account owner.
     */
    private function getDeliveryNotificationUser(Delivery $delivery, AccountSettings $account): ?User
    {
        if ($delivery->technician_id) {
            $technician = User::find($delivery->technician_id);
            if ($technician) {
                return $technician;
            }
        }

        return $this->getAccountOwner();
    }

    private function getAccountOwner(): ?User
    {
        $tenant = tenant();
        if ($tenant) {
            $accountModel = Account::where('tenant_id', $tenant->id)->first();
            if ($accountModel && $accountModel->owner_id) {
                // Re-fetch via the domain User model so the declared return type
                // (?App\Domain\User\Models\User) matches what we hand back.
                return User::find($accountModel->owner_id);
            }
        }

        return null;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PDF Generation
    // ─────────────────────────────────────────────────────────────────────────

    private function generateServiceTicketPdf(ServiceTicket $ticket, AccountSettings $account): ?string
    {
        try {
            $ticket->load([
                'customer',
                'subsidiary',
                'location',
                'assetUnit.asset',
                'serviceItems' => fn ($q) => $q->where('inactive', false)->orderBy('sort_order'),
            ]);

            $pdf = Pdf::loadView('pdfs.service-ticket', [
                'ticket' => $ticket,
                'account' => $account,
                'subsidiary' => $ticket->subsidiary,
                'logoUrl' => $account->logo_url,
            ]);

            $filename = 'service-ticket-'.$ticket->service_ticket_number.'-'.now()->format('Y-m-d-H-i-s').'.pdf';
            $path = CreateDocument::storagePathForFilename($filename);
            $pdfOutput = $pdf->output();

            Storage::disk('s3')->put($path, $pdfOutput, ['visibility' => 'private']);

            Document::create([
                'display_name' => "Service Ticket #{$ticket->service_ticket_number} - Signed",
                'file' => $path,
                'file_extension' => 'pdf',
                'file_size' => strlen($pdfOutput),
                'created_by_id' => null,
                'ai_status' => 'completed',
            ]);

            return $path;

        } catch (\Exception $e) {
            Log::error('Failed to generate service ticket PDF', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Financing
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $metrics
     */
    public function notifyFinancingAtRisk(Financing $financing, array $metrics, int $userId): void
    {
        try {
            $financing->loadMissing(['assetUnit', 'vendor']);
            $unitLabel = $financing->assetUnit?->display_name ?? "Unit #{$financing->asset_unit_id}";
            $lender = $financing->vendor?->display_name ?? 'lender';
            $days = (int) ($metrics['days_financed'] ?? 0);
            $interestCost = number_format((float) ($metrics['total_interest_cost'] ?? 0), 2);

            Notification::create([
                'assigned_to_user_id' => $userId,
                'type' => 'financing_at_risk',
                'title' => 'Financing at risk — sell or pay off',
                'message' => "{$unitLabel} ({$lender}) has been financed {$days} days with \${$interestCost} total interest cost. Consider selling or paying off to protect margin.",
                'route' => 'financings.show',
                'route_params' => ['financing' => $financing->id],
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to create financing at-risk notification', [
                'financing_id' => $financing->id,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Mail Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function sendServiceTicketApprovalEmail(ServiceTicket $ticket, AccountSettings $account, User $notifyUser, ?string $pdfPath): void
    {
        try {
            $mailable = new ServiceTicketApprovalNotification($ticket, $account, $notifyUser, $pdfPath);
            $this->tenantMail->send($notifyUser->email, $mailable);
        } catch (\Exception $e) {
            Log::error('Failed to send service ticket approval notification email', [
                'ticket_id' => $ticket->id,
                'user_id' => $notifyUser->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
