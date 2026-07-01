<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Actions;

use App\Domain\Contact\Models\Contact;
use App\Domain\Shipment\Models\Shipment;
use App\Mail\ShipmentTrackingNotification;
use App\Models\AccountSettings;
use App\Services\SMS\SmsService;
use App\Services\TenantMailService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Validation\ValidationException;

class SendShipmentNotification
{
    public function __construct(
        private readonly TenantMailService $tenantMail,
        private readonly SmsService $smsService,
    ) {}

    public function __invoke(Shipment $shipment, ?Authenticatable $actor = null, bool $sendSms = false): void
    {
        if (! $shipment->isPurchased()) {
            throw ValidationException::withMessages([
                'status' => 'Purchase a label before sending a tracking notification.',
            ]);
        }

        $account = AccountSettings::query()->first();
        if ($account === null) {
            throw ValidationException::withMessages([
                'account' => 'Account settings are not configured.',
            ]);
        }

        $trackUrl = $this->trackingUrl($shipment);
        $recipients = $this->resolveRecipients($shipment);

        if ($recipients === []) {
            throw ValidationException::withMessages([
                'recipient' => 'No email address is available for this recipient.',
            ]);
        }

        foreach ($recipients as $recipient) {
            $mailable = new ShipmentTrackingNotification(
                $shipment,
                $account,
                $recipient['contact'],
                $trackUrl,
            );

            $this->tenantMail->send($recipient['email'], $mailable, $actor);
        }

        if ($sendSms) {
            $contact = $recipients[0]['contact'] ?? null;
            if ($actor !== null && $contact instanceof Contact) {
                $this->smsService->sendShipmentTrackingSms($actor, $contact, $shipment, $trackUrl);
            }
        }

        $shipment->update(['notified_at' => now()]);
    }

    /**
     * @return list<array{email: string, contact: Contact|null}>
     */
    private function resolveRecipients(Shipment $shipment): array
    {
        if ($shipment->contact_id !== null) {
            $contact = $shipment->contact;
            $email = $contact?->email ?: $contact?->secondary_email ?: $shipment->recipient_email;
            if (! filled($email)) {
                return [];
            }

            return [['email' => (string) $email, 'contact' => $contact]];
        }

        if ($shipment->vendor_id !== null) {
            $vendor = $shipment->vendor()->with(['linkedContacts', 'primaryContact'])->first();
            $contacts = collect();
            if ($vendor?->primaryContact) {
                $contacts->push($vendor->primaryContact);
            }
            foreach ($vendor?->linkedContacts ?? [] as $linked) {
                if (! $contacts->contains('id', $linked->id)) {
                    $contacts->push($linked);
                }
            }

            $recipients = [];
            foreach ($contacts as $contact) {
                $email = $contact->email ?: $contact->secondary_email;
                if (filled($email)) {
                    $recipients[] = ['email' => (string) $email, 'contact' => $contact];
                }
            }

            return $recipients;
        }

        if (filled($shipment->recipient_email)) {
            return [['email' => $shipment->recipient_email, 'contact' => null]];
        }

        return [];
    }

    private function trackingUrl(Shipment $shipment): string
    {
        $tenant = tenant();
        $domain = $tenant?->domains->first()?->domain;
        $path = route('shipments.track', ['uuid' => $shipment->uuid], false);

        return $domain ? 'https://'.$domain.$path : url($path);
    }
}
