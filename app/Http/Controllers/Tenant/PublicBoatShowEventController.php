<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\Asset\Models\Asset;
use App\Domain\BoatShowEvent\Actions\SubmitBoatShowEventLead;
use App\Domain\BoatShowEvent\Models\BoatShowEvent;
use App\Domain\BoatShowEvent\Support\BoatShowQrCode;
use App\Domain\BoatShowEvent\Support\EventAssetsPayload;
use App\Domain\BoatShowEvent\Support\TenantAccountOwnerSalesperson;
use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use App\Mail\BoatShowLeadSubmitted;
use App\Models\AccountSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class PublicBoatShowEventController extends Controller
{
    public function __construct(
        private SubmitBoatShowEventLead $submitBoatShowEventLead,
    ) {}

    public function showcase(string $uuid): InertiaResponse
    {
        $event = $this->resolvePublicEvent($uuid);

        $account = AccountSettings::getCurrent();
        $assets = EventAssetsPayload::groupedForPublic($event);

        $leadUrl = route('boat-show-events.public.lead', ['uuid' => $event->uuid], true);
        $leadQrDataUri = BoatShowQrCode::dataUriForUrl($leadUrl);

        return Inertia::render('Tenant/Public/BoatShowEventShowcase', [
            'event' => $this->eventPayload($event),
            'assets' => $assets,
            'account' => $account,
            'logoUrl' => $account->logo_url,
            'leadUrl' => $leadUrl,
            'leadQrDataUri' => $leadQrDataUri,
        ]);
    }

    public function leadForm(string $uuid): InertiaResponse
    {
        $event = $this->resolvePublicEvent($uuid);

        $account = AccountSettings::getCurrent();
        $assets = EventAssetsPayload::groupedForPublic($event);

        return Inertia::render('Tenant/Public/BoatShowEventLead', [
            'event' => $this->eventPayload($event),
            'assets' => $assets,
            'account' => $account,
            'logoUrl' => $account->logo_url,
        ]);
    }

    public function leadStore(Request $request, string $uuid): RedirectResponse
    {
        $event = $this->resolvePublicEvent($uuid);

        try {
            $result = ($this->submitBoatShowEventLead)($event, $request->all());
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return back()
                ->withInput()
                ->with('error', $e->getMessage() ?: 'Something went wrong. Please try again.');
        }

        if (! ($result['success'] ?? false)) {
            return back()
                ->withInput()
                ->with('error', $result['message'] ?? 'Could not save your submission.');
        }

        $this->sendLeadEmails($request, $event);

        return back()->with('success', 'Thank you! We received your information and will be in touch.');
    }

    private function resolvePublicEvent(string $uuid): BoatShowEvent
    {
        return BoatShowEvent::query()
            ->where('uuid', $uuid)
            ->where('active', true)
            ->with(['show:id,display_name,slug'])
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function eventPayload(BoatShowEvent $event): array
    {
        return [
            'id' => $event->id,
            'uuid' => $event->uuid,
            'display_name' => $event->display_name,
            'venue' => $event->venue,
            'city' => $event->city,
            'state' => $event->state,
            'starts_at' => $event->starts_at?->toDateString(),
            'ends_at' => $event->ends_at?->toDateString(),
            'boat_show' => $event->show ? [
                'display_name' => $event->show->display_name,
            ] : null,
        ];
    }

    private function sendLeadEmails(Request $request, BoatShowEvent $event): void
    {
        if (! config('boat_show.send_immediate_owner_lead_notification')) {
            return;
        }

        $resolved = TenantAccountOwnerSalesperson::resolve();
        $centralAccount = $resolved['account'];
        $owner = $centralAccount->owner;

        $first = $request->input('first_name', '');
        $last = $request->input('last_name', '');
        $fullName = trim($first.' '.$last);
        $email = $request->input('email');
        $phone = $request->input('phone');
        $notes = $request->input('notes');

        $assetIds = array_map('intval', (array) $request->input('asset_ids', []));
        $interestedAssets = [];
        if ($assetIds !== []) {
            $interestedAssets = Asset::query()
                ->whereIn('id', $assetIds)
                ->orderBy('id')
                ->get(['id', 'display_name'])
                ->map(fn ($a) => ['display_name' => $a->display_name])
                ->values()
                ->all();
        }

        $accountSettings = AccountSettings::getCurrent();
        $tenantLabel = $centralAccount->name ?? config('app.name');

        $event->refresh();
        $recipientEmails = $this->immediateLeadNotificationRecipients($event, $owner?->email);

        if ($recipientEmails !== []) {
            Mail::to($recipientEmails)->send(new BoatShowLeadSubmitted(
                eventName: $event->display_name ?? 'Boat show',
                leadFullName: $fullName,
                leadEmail: $email,
                leadPhone: $phone,
                leadNotes: $notes,
                interestedAssets: $interestedAssets,
                account: $accountSettings,
                tenantLabel: $tenantLabel,
            ));
        }
    }

    /**
     * Emails for the instant "new boat show lead" alert: event "Notify users", else central owner.
     *
     * @return list<string>
     */
    private function immediateLeadNotificationRecipients(BoatShowEvent $event, ?string $fallbackOwnerEmail): array
    {
        $recipients = $event->recipients;
        if (is_string($recipients)) {
            $decoded = json_decode($recipients, true);
            $recipients = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($recipients)) {
            $recipients = [];
        }

        $ids = $recipients['user_ids'] ?? null;
        if (is_array($ids) && $ids !== []) {
            $emails = User::query()
                ->whereIn('id', array_map('intval', $ids))
                ->pluck('email')
                ->filter(fn (mixed $e) => is_string($e) && $e !== '')
                ->unique()
                ->values()
                ->all();

            if ($emails !== []) {
                return $emails;
            }
        }

        if ($fallbackOwnerEmail) {
            return [$fallbackOwnerEmail];
        }

        return [];
    }
}
