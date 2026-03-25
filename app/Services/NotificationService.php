<?php

namespace App\Services;

use App\Domain\Contract\Models\Contract;
use App\Domain\Delivery\Models\Delivery;
use App\Domain\Estimate\Models\Estimate;
use App\Domain\Notification\Models\Notification;
use App\Domain\ServiceTicket\Models\ServiceTicket;
use App\Domain\User\Models\User;
use App\Mail\ContractSignedNotification;
use App\Mail\EstimateApprovalNotification;
use App\Mail\ServiceTicketApproved;
use App\Models\Account;
use App\Models\AccountSettings;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;

class NotificationService
{
    // ─────────────────────────────────────────────────────────────────────────
    // Service Ticket
    // ─────────────────────────────────────────────────────────────────────────

    public function notifyServiceTicketApproved(ServiceTicket $ticket, AccountSettings $account): void
    {
        try {
            $notifyUser = $this->getServiceTicketNotificationUser($account);

            if (!$notifyUser) {
                Log::warning('No user found to notify for service ticket approval', [
                    'ticket_id' => $ticket->id,
                ]);
                return;
            }

            $pdfPath = $this->generateServiceTicketPdf($ticket, $account);

            Notification::create([
                'assigned_to_user_id' => $notifyUser->id,
                'type'                => 'service_ticket_approved',
                'title'               => 'Service Ticket Approved',
                'message'             => "Service ticket #{$ticket->service_ticket_number} has been approved by {$ticket->customer->display_name}.",
                'route'               => 'servicetickets.show',
                'route_params'        => $ticket->id,
            ]);

            $this->sendServiceTicketApprovalEmail($ticket, $account, $notifyUser, $pdfPath);

        } catch (\Exception $e) {
            Log::error('Failed to notify service ticket approved', [
                'ticket_id' => $ticket->id,
                'error'     => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);
        }
    }

    public function sendServiceTicketCustomerConfirmation(ServiceTicket $ticket, AccountSettings $account): void
    {
        $ticket->load(['customer']);

        $customerEmail = $ticket->customer->email ?? null;
        if (!$customerEmail) {
            return;
        }

        try {
            Mail::to($customerEmail)->send(new ServiceTicketApproved($ticket, $account));
        } catch (\Exception $e) {
            Log::error("Failed to send approval confirmation for ticket {$ticket->service_ticket_number}: " . $e->getMessage());
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

            if (!$salesperson) {
                Log::warning('No salesperson found to notify for estimate action', [
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
            Log::error('Failed to notify estimate action', [
                'estimate_id' => $estimate->id,
                'action'      => $action,
                'error'       => $e->getMessage(),
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Delivery
    // ─────────────────────────────────────────────────────────────────────────

    public function notifyDeliverySigned(Delivery $delivery, AccountSettings $account): void
    {
        try {
            $delivery->load(['customer', 'assetUnit.asset', 'technician']);

            $notifyUser = $this->getDeliveryNotificationUser($delivery, $account);

            if (!$notifyUser) {
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
                'type'                => 'delivery_signed',
                'title'               => 'Delivery Signed',
                'message'             => "Delivery for {$assetName} has been signed by {$recipientName} ({$customerName}).",
                'route'               => 'deliveries.show',
                'route_params'        => $delivery->id,
            ]);

            // Optionally send an email — add a Mailable here when ready
            // Mail::to($notifyUser->email)->send(new DeliverySignedNotification($delivery, $account, $notifyUser));

        } catch (\Exception $e) {
            Log::error('Failed to notify delivery signed', [
                'delivery_id' => $delivery->id,
                'error'       => $e->getMessage(),
                'trace'       => $e->getTraceAsString(),
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
                'type'                => 'contract_signed',
                'title'               => 'Contract Signed',
                'message'             => "Contract {$contract->contract_number} has been signed by {$contract->customer?->display_name}.",
                'route'               => 'contracts.show',
                'route_params'        => $contract->id,
            ]);

            Mail::to($notifyUser->email)->send(
                new ContractSignedNotification($contract, $account, $notifyUser)
            );

        } catch (\Exception $e) {
            Log::error('Failed to notify contract signed', [
                'contract_id' => $contract->id,
                'error'       => $e->getMessage(),
                'trace'       => $e->getTraceAsString(),
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
            if ($accountModel && $accountModel->owner) {
                return $accountModel->owner;
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
                'ticket'    => $ticket,
                'account'   => $account,
                'subsidiary' => $ticket->subsidiary,
                'logoUrl'   => $this->resolveLogoUrl($ticket, $account),
            ]);

            $filename = 'service-ticket-' . $ticket->service_ticket_number . '-' . now()->format('Y-m-d-H-i-s') . '.pdf';
            $path     = 'pdfs/' . $filename;

            Storage::disk('s3')->put($path, $pdf->output());

            \App\Domain\Document\Models\Document::create([
                'display_name'  => "Service Ticket #{$ticket->service_ticket_number} - Signed",
                'file'          => $path,
                'file_extension' => 'pdf',
                'file_size'     => strlen($pdf->output()),
                'created_by_id' => null,
                'ai_status'     => 'completed',
            ]);

            return $path;

        } catch (\Exception $e) {
            Log::error('Failed to generate service ticket PDF', [
                'ticket_id' => $ticket->id,
                'error'     => $e->getMessage(),
            ]);
            return null;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Mail Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function sendServiceTicketApprovalEmail(ServiceTicket $ticket, AccountSettings $account, User $notifyUser, ?string $pdfPath): void
    {
        try {
            Mail::to($notifyUser->email)->send(
                new \App\Mail\ServiceTicketApprovalNotification($ticket, $account, $notifyUser, $pdfPath)
            );
        } catch (\Exception $e) {
            Log::error('Failed to send service ticket approval notification email', [
                'ticket_id' => $ticket->id,
                'user_id'   => $notifyUser->id,
                'error'     => $e->getMessage(),
            ]);
        }
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
}