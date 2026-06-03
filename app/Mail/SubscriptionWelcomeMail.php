<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Account;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionWelcomeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public Account $account,
        public Plan $plan,
        public string $dashboardUrl,
        public int $trialDays = 14,
    ) {}

    public function envelope(): Envelope
    {
        $workspace = trim((string) $this->account->name);

        return new Envelope(
            subject: $workspace !== ''
                ? "Welcome to {$workspace} — get started"
                : 'Welcome — your workspace is ready',
        );
    }

    public function content(): Content
    {
        $greetingName = trim($this->user->full_name);
        if ($greetingName === '') {
            $greetingName = trim((string) ($this->user->name ?? ''));
        }
        if ($greetingName === '') {
            $greetingName = 'there';
        }

        return new Content(
            view: 'emails.subscription-welcome',
            with: [
                'greetingName' => $greetingName,
                'workspaceName' => $this->account->name,
                'planName' => $this->plan->name,
                'dashboardUrl' => $this->dashboardUrl,
                'trialDays' => $this->trialDays,
                'appName' => (string) config('app.name'),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
