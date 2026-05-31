<?php

namespace App\Services\Mail;

use App\Mail\AccountInvitation;
use App\Models\AccountSettings;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

/**
 * Tenant outbound mail: sandbox routing and queue vs sync dispatch.
 *
 * When sandbox mode is on, customer/vendor operational mail is sent to the signed-in
 * user instead of external recipients. Staff account invitations are exempt.
 */
class TenantMailService
{
    /** @var list<class-string<Mailable>> */
    private const SANDBOX_EXEMPT = [
        AccountInvitation::class,
    ];

    public function isSandboxActive(): bool
    {
        if (! tenant()) {
            return false;
        }

        return AccountSettings::getCurrent()->smsSandboxMode();
    }

    public function isExempt(Mailable $mailable): bool
    {
        return $this->isExemptMailableClass($mailable::class);
    }

    /**
     * @param  class-string<Mailable>  $mailableClass
     */
    public function isExemptMailableClass(string $mailableClass): bool
    {
        return in_array($mailableClass, self::SANDBOX_EXEMPT, true);
    }

    public function shouldRedirectToSandboxActor(Mailable $mailable, ?Authenticatable $actor = null): bool
    {
        if (! $this->isSandboxActive() || $this->isExempt($mailable)) {
            return false;
        }

        $actor = $actor ?? Auth::user();

        return $actor !== null && filled($actor->email);
    }

    /**
     * @param  string|array<int, string>|null  $intended
     * @return list<string>
     */
    public function resolveRecipients(string|array|null $intended, Mailable $mailable, ?Authenticatable $actor = null): array
    {
        if ($this->shouldRedirectToSandboxActor($mailable, $actor)) {
            $email = trim((string) (($actor ?? Auth::user())?->email ?? ''));

            return $email !== '' ? [$email] : [];
        }

        return $this->normalizeRecipients($intended);
    }

    /**
     * @param  string|array<int, string>|null  $intended
     */
    public function canSend(string|array|null $intended, Mailable $mailable, ?Authenticatable $actor = null): bool
    {
        return $this->resolveRecipients($intended, $mailable, $actor) !== [];
    }

    public function validationErrorMessage(Mailable $mailable): string
    {
        if ($this->isSandboxActive() && ! $this->isExempt($mailable)) {
            return 'Sandbox mode sends email to you, but your account has no email address on file.';
        }

        return 'No recipient email address is available.';
    }

    /**
     * @param  string|array<int, string>|null  $intended
     */
    public function displayRecipient(string|array|null $intended, Mailable $mailable, ?Authenticatable $actor = null): string
    {
        $resolved = $this->resolveRecipients($intended, $mailable, $actor);

        if ($resolved === []) {
            $normalized = $this->normalizeRecipients($intended);

            return implode(', ', $normalized);
        }

        $label = implode(', ', $resolved);

        if ($this->shouldRedirectToSandboxActor($mailable, $actor)) {
            return "{$label} (sandbox — you)";
        }

        return $label;
    }

    /**
     * @param  string|array<int, string>|null  $to
     */
    public function send(string|array|null $to, Mailable $mailable, ?Authenticatable $actor = null): void
    {
        $recipients = $this->resolveRecipients($to, $mailable, $actor);

        if ($recipients === []) {
            throw new \InvalidArgumentException($this->validationErrorMessage($mailable));
        }

        $pending = Mail::to($recipients);

        if ($mailable instanceof ShouldQueue) {
            $pending->queue($mailable);
        } else {
            $pending->send($mailable);
        }
    }

    /**
     * @param  string|array<int, string>|null  $intended
     * @return list<string>
     */
    private function normalizeRecipients(string|array|null $intended): array
    {
        if ($intended === null) {
            return [];
        }

        $list = is_array($intended) ? $intended : [$intended];

        return array_values(array_filter(
            array_map(static fn ($email) => trim((string) $email), $list),
            static fn (string $email) => $email !== '',
        ));
    }
}
