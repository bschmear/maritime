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
        $event   = $this->resolvePublicEvent($uuid);
        $account = AccountSettings::getCurrent();
        [$assets, $leadUrl, $leadQrDataUri] = $this->prepareGroupedAssetsWithQr($event);

        return Inertia::render('Tenant/Public/BoatShowEventShowcase', [
            'event'        => $this->eventPayload($event),
            'assets'       => $assets,
            'account'      => $account,
            'logoUrl'      => $account->logo_url,
            'leadUrl'      => $leadUrl,
            'leadQrDataUri'=> $leadQrDataUri,
            ...$this->publicBrandingProps(),
        ]);
    }

    public function printFlyer(string $uuid): InertiaResponse
    {
        $event   = $this->resolvePublicEvent($uuid);
        $account = AccountSettings::getCurrent();
        [$assets, $leadUrl, $leadQrDataUri] = $this->prepareGroupedAssetsWithQr($event, leadQrSize: 320, assetQrSize: 280);

        $assetsFlat = array_merge(
            $assets['boats'] ?? [],
            $assets['engines'] ?? [],
            $assets['trailers'] ?? [],
        );

        return Inertia::render('Tenant/Public/BoatShowPrintEvent', [
            'event'           => $this->eventPayload($event),
            'assetsFlat'      => $assetsFlat,
            'logoUrl'         => $account->logo_url,
            'companyName'     => $this->companyDisplayName($account),
            'leadUrl'         => $leadUrl,
            'leadQrDataUri'   => $leadQrDataUri,
            'showcaseUrl'     => route('boat-show-events.public.showcase', ['uuid' => $event->uuid], true),
            ...$this->publicBrandingProps(),
        ]);
    }

    public function assetShow(string $uuid, int $asset): InertiaResponse
    {
        $event   = $this->resolvePublicEvent($uuid);
        $payload = EventAssetsPayload::detailForPublic($event, $asset);
        if ($payload === null) {
            abort(404);
        }

        $account          = AccountSettings::getCurrent();
        $leadBase         = route('boat-show-events.public.lead', ['uuid' => $event->uuid], true);
        $leadUrlWithAsset = $leadBase.(str_contains($leadBase, '?') ? '&' : '?').'asset='.$asset;

        return Inertia::render('Tenant/Public/BoatShowEventAssetPublic', [
            'event'           => $this->eventPayload($event),
            'asset'           => $payload,
            'account'         => $account,
            'logoUrl'         => $account->logo_url,
            'showcaseUrl'     => route('boat-show-events.public.showcase', ['uuid' => $event->uuid], true),
            'leadUrlWithAsset'=> $leadUrlWithAsset,
            ...$this->publicBrandingProps(),
        ]);
    }

    public function leadForm(Request $request, string $uuid): InertiaResponse
    {
        $event         = $this->resolvePublicEvent($uuid);
        $account       = AccountSettings::getCurrent();
        $assets        = EventAssetsPayload::groupedForPublic($event);
        $validAssetIds = $event->eventAssets()->pluck('asset_id')->map(fn ($id) => (int) $id)->all();
        $preselectedAssetIds = $this->preselectedAssetIdsFromQuery($request, $validAssetIds);

        return Inertia::render('Tenant/Public/BoatShowEventLead', [
            'event'              => $this->eventPayload($event),
            'assets'             => $assets,
            'account'            => $account,
            'logoUrl'            => $account->logo_url,
            'preselectedAssetIds'=> $preselectedAssetIds,
            ...$this->publicBrandingProps(),
        ]);
    }

    /**
     * @param  list<int>  $validAssetIds
     * @return list<int>
     */
    private function preselectedAssetIdsFromQuery(Request $request, array $validAssetIds): array
    {
        $raw = $request->query('asset');
        if ($raw === null || $raw === '') {
            return [];
        }

        $candidates = is_array($raw)
            ? array_map('intval', $raw)
            : array_map('intval', explode(',', (string) $raw));

        return array_values(array_unique(array_intersect($candidates, $validAssetIds)));
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
            return back()->withInput()->with('error', $e->getMessage() ?: 'Something went wrong. Please try again.');
        }

        if (! ($result['success'] ?? false)) {
            return back()->withInput()->with('error', $result['message'] ?? 'Could not save your submission.');
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
     * Grouped public asset rows with {@code public_url} and {@code qr_data_uri} on each row.
     *
     * @return array{0: array{boats: array<int, array<string, mixed>>, engines: array<int, array<string, mixed>>, trailers: array<int, array<string, mixed>>}, 1: string, 2: string}
     */
    private function prepareGroupedAssetsWithQr(BoatShowEvent $event, int $leadQrSize = 240, int $assetQrSize = 240): array
    {
        $assets = EventAssetsPayload::groupedForPublic($event);

        $leadUrl       = route('boat-show-events.public.lead', ['uuid' => $event->uuid], true);
        $leadQrDataUri = BoatShowQrCode::dataUriForUrl($leadUrl, $leadQrSize);

        $allRows = array_merge(
            $assets['boats'] ?? [],
            $assets['engines'] ?? [],
            $assets['trailers'] ?? [],
        );

        foreach ($allRows as $row) {
            $assetId = $row['id'] ?? null;
            if ($assetId === null) {
                continue;
            }

            $assetUrl = route('boat-show-events.public.asset', [
                'uuid'  => $event->uuid,
                'asset' => $assetId,
            ], true);

            $qr = BoatShowQrCode::dataUriForUrl($assetUrl, $assetQrSize);

            foreach (['boats', 'engines', 'trailers'] as $group) {
                foreach ($assets[$group] as &$asset) {
                    if (($asset['id'] ?? null) === $assetId) {
                        $asset['public_url']  = $assetUrl;
                        $asset['qr_data_uri'] = $qr;
                    }
                }
                unset($asset);
            }
        }

        return [$assets, $leadUrl, $leadQrDataUri];
    }

    private function companyDisplayName(AccountSettings $account): string
    {
        $settings = $account->settings;
        if (is_array($settings) && ! empty($settings['business_name'])) {
            return (string) $settings['business_name'];
        }

        return 'Company';
    }

    /**
     * @return array{brandingAppName: string, brandingAppUrl: string, brandingTermsUrl: string|null}
     */
    private function publicBrandingProps(): array
    {
        $terms = config('app.terms_url');

        return [
            'brandingAppName' => (string) config('app.name'),
            'brandingAppUrl' => (string) config('app.url'),
            'brandingTermsUrl' => is_string($terms) && $terms !== '' ? $terms : null,
        ];
    }

    /** @return array<string, mixed> */
    private function eventPayload(BoatShowEvent $event): array
    {
        return [
            'id'           => $event->id,
            'uuid'         => $event->uuid,
            'display_name' => $event->display_name,
            'venue'        => $event->venue,
            'city'         => $event->city,
            'state'        => $event->state,
            'starts_at'    => $event->starts_at?->toDateString(),
            'ends_at'      => $event->ends_at?->toDateString(),
            'boat_show'    => $event->show ? ['display_name' => $event->show->display_name] : null,
        ];
    }

    private function sendLeadEmails(Request $request, BoatShowEvent $event): void
    {
        if (! config('boat_show.send_immediate_owner_lead_notification')) {
            return;
        }

        $resolved       = TenantAccountOwnerSalesperson::resolve();
        $centralAccount = $resolved['account'];
        $owner          = $centralAccount->owner;

        $first    = $request->input('first_name', '');
        $last     = $request->input('last_name', '');
        $fullName = trim($first.' '.$last);
        $email    = $request->input('email');
        $phone    = $request->input('phone');
        $notes    = $request->input('notes');

        $assetIds         = array_map('intval', (array) $request->input('asset_ids', []));
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
        $tenantLabel     = $centralAccount->name ?? config('app.name');

        $event->refresh();
        $recipientEmails = $this->immediateLeadNotificationRecipients($event, $owner?->email);

        if ($recipientEmails !== []) {
            Mail::to($recipientEmails)->send(new BoatShowLeadSubmitted(
                eventName:        $event->display_name ?? 'Boat show',
                leadFullName:     $fullName,
                leadEmail:        $email,
                leadPhone:        $phone,
                leadNotes:        $notes,
                interestedAssets: $interestedAssets,
                account:          $accountSettings,
                tenantLabel:      $tenantLabel,
            ));
        }
    }

    /** @return list<string> */
    private function immediateLeadNotificationRecipients(BoatShowEvent $event, ?string $fallbackOwnerEmail): array
    {
        $recipients = $event->recipients;
        if (is_string($recipients)) {
            $decoded    = json_decode($recipients, true);
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

        return $fallbackOwnerEmail ? [$fallbackOwnerEmail] : [];
    }
}