<?php

declare(strict_types=1);

namespace App\Domain\WarrantyClaim\Support;

use App\Domain\Communication\Actions\CreateCommunication;
use App\Domain\Contact\Models\Contact;
use App\Domain\User\Models\User;
use App\Domain\WarrantyClaim\Models\WarrantyClaim;
use App\Enums\Communication\Channel;
use App\Enums\Communication\CommunicationType;
use App\Enums\Communication\Status;
use App\Models\AccountSettings;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

final class LogWarrantyClaimVendorEmailCommunication
{
    public function __construct(
        private readonly CreateCommunication $createCommunication,
    ) {}

    public function __invoke(
        WarrantyClaim $claim,
        Contact $contact,
        string $intendedEmail,
        AccountSettings $account,
        string $reviewUrl,
        string $vendorPortalLoginUrl,
        mixed $actor = null,
        bool $sandboxRedirect = false,
    ): void {
        $userId = $this->resolveActorUserId($actor, $claim);
        if ($userId === null) {
            Log::warning('Warranty claim vendor email: cannot log communication, no valid user_id', [
                'claim_id' => $claim->id,
                'contact_id' => $contact->id,
            ]);

            return;
        }

        $claimRef = $claim->display_name ?? ('Claim #'.$claim->id);
        $accountName = $account->name ?? 'Warranty';
        $subject = Str::limit("Warranty claim {$claimRef} — {$accountName}", 255, '');
        $contactLabel = $contact->display_name
            ?: trim(implode(' ', array_filter([(string) $contact->first_name, (string) $contact->last_name])))
            ?: 'Manufacturer contact';

        $claimPath = Route::has('warrantyclaims.show')
            ? route('warrantyclaims.show', $claim->getKey(), false)
            : '/warrantyclaims/'.$claim->getKey();
        $domain = tenant()?->domains->first()?->domain;
        $claimUrl = $domain ? 'https://'.$domain.$claimPath : url($claimPath);

        $notes = sprintf(
            "Emailed warranty claim %s to %s (%s).\n\nThe message included the claim review link and manufacturer portal sign-in instructions.\n\nReview link: %s\nPortal login: %s\n\nView claim in app: %s",
            $claimRef,
            $contactLabel,
            trim($intendedEmail),
            $reviewUrl,
            $vendorPortalLoginUrl,
            $claimUrl,
        );

        if ($sandboxRedirect) {
            $notes .= "\n\n(Sandbox mode: delivery was redirected to the signed-in staff user.)";
        }

        $payload = [
            'user_id' => $userId,
            'communication_type_id' => CommunicationType::Email->id(),
            'direction' => 'outbound',
            'subject' => $subject,
            'notes' => $notes,
            'needs_follow_up' => false,
            'is_private' => false,
            'status_id' => Status::Closed->id(),
            'channel_id' => Channel::Email->id(),
            'priority_id' => 2,
            'tags' => ['warranty_claim', 'vendor_notification'],
            'date_contacted' => now()->toIso8601String(),
            'assigned_to' => $userId,
        ];

        $this->logForCommunicable('Contact', (int) $contact->id, $payload);

        $vendorId = (int) ($claim->vendor_id ?? 0);
        if ($vendorId > 0) {
            $this->logForCommunicable('Vendor', $vendorId, $payload);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function logForCommunicable(string $type, int $id, array $payload): void
    {
        try {
            $result = ($this->createCommunication)(array_merge($payload, [
                'communicable_type' => $type,
                'communicable_id' => $id,
            ]));

            if (! ($result['success'] ?? false)) {
                Log::warning('Warranty claim vendor email: communication log failed', [
                    'communicable_type' => $type,
                    'communicable_id' => $id,
                    'message' => $result['message'] ?? 'unknown',
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Warranty claim vendor email: communication log exception', [
                'communicable_type' => $type,
                'communicable_id' => $id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function resolveActorUserId(mixed $actor, WarrantyClaim $claim): ?int
    {
        if (is_object($actor) && isset($actor->id)) {
            $id = (int) $actor->id;
            if ($id > 0 && User::query()->whereKey($id)->exists()) {
                return $id;
            }
        }

        $createdBy = (int) ($claim->created_by_user_id ?? 0);
        if ($createdBy > 0 && User::query()->whereKey($createdBy)->exists()) {
            return $createdBy;
        }

        return null;
    }
}
