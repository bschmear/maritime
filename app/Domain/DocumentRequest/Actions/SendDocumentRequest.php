<?php

declare(strict_types=1);

namespace App\Domain\DocumentRequest\Actions;

use App\Domain\Contact\Models\Contact;
use App\Domain\DocumentRequest\Enums\DocumentRequestStatus;
use App\Domain\DocumentRequest\Models\DocumentRequest;
use App\Domain\SystemLog\Support\LogSystemEvent;
use App\Enums\System\SystemLogAction;
use App\Mail\DocumentRequestMail;
use App\Models\AccountSettings;
use App\Services\Mail\TenantMailService;
use App\Services\TenantStaffResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

final class SendDocumentRequest
{
    public function __construct(
        private readonly TenantMailService $tenantMail,
    ) {}

    /**
     * @return array{success: bool, record?: DocumentRequest, message?: string}
     */
    public function __invoke(
        Contact $contact,
        string $title,
        ?string $description,
        ?Model $source = null,
    ): array {
        $email = trim((string) ($contact->email ?? ''));
        if ($email === '') {
            throw ValidationException::withMessages([
                'email' => 'This contact does not have a primary email address.',
            ]);
        }

        $customer = $contact->customer;
        if (! $customer) {
            throw ValidationException::withMessages([
                'customer' => 'This contact does not have a customer profile yet.',
            ]);
        }

        $tenant = tenant();
        $domain = $tenant?->domains->first()?->domain;
        $root = $domain ? 'https://'.$domain : rtrim((string) config('app.url'), '/');
        $portalUrl = $root.'/portal/documents?tab=requests';

        $request = DocumentRequest::query()->create([
            'contact_id' => $contact->id,
            'customer_profile_id' => $customer->id,
            'source_type' => $source ? $source::class : null,
            'source_id' => $source?->getKey(),
            'requested_by_user_id' => current_tenant_user_id(),
            'title' => $title,
            'description' => $description,
            'status' => DocumentRequestStatus::Pending,
            'sent_at' => now(),
        ]);

        $settings = AccountSettings::getCurrent();
        $mailable = new DocumentRequestMail(
            $contact,
            $settings,
            $request,
            $portalUrl,
        );
        $mailActor = TenantStaffResolver::webUserForMail();

        try {
            $this->tenantMail->send($email, $mailable, $mailActor);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'email' => $e->getMessage(),
            ]);
        }

        LogSystemEvent::record($contact, SystemLogAction::Updated);

        return [
            'success' => true,
            'record' => $request->fresh(['requestedBy:id,display_name']),
            'email' => [
                'sandbox_mode' => $this->tenantMail->shouldRedirectToSandboxActor($mailable, $mailActor),
                'intended_recipient' => $email,
                'delivery_recipient' => $this->tenantMail->displayRecipient($email, $mailable, $mailActor),
            ],
        ];
    }
}
