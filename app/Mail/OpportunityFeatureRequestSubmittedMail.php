<?php

namespace App\Mail;

use App\Domain\AddOn\Models\AddOn;
use App\Domain\Opportunity\Models\Opportunity;
use App\Domain\Opportunity\Models\OpportunityFeatureRequest;
use App\Domain\User\Models\User;
use App\Models\AccountSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OpportunityFeatureRequestSubmittedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Opportunity $opportunity,
        public OpportunityFeatureRequest $submission,
        public AccountSettings $account,
        public User $notifyUser,
    ) {}

    public function envelope(): Envelope
    {
        $name = $this->account->name ?? 'Notification';

        return new Envelope(
            subject: "Feature request submitted — {$this->opportunity->display_name} — {$name}",
        );
    }

    public function content(): Content
    {
        $tenant = tenant();
        $domain = $tenant?->domains->first()?->domain;
        $path = route('opportunities.show', ['opportunity' => $this->opportunity->id], false);
        $opportunityUrl = $domain ? 'https://'.$domain.$path : url($path);

        $addonNameMap = [];
        $selections = $this->submission->addon_selections ?? [];
        if (is_array($selections) && $selections !== []) {
            $ids = collect($selections)->pluck('catalog_addon_id')->filter()->unique()->values()->all();
            if ($ids !== []) {
                $addonNameMap = AddOn::query()->whereIn('id', $ids)->pluck('name', 'id')->all();
            }
        }

        return new Content(
            view: 'emails.opportunity-feature-request-submitted',
            with: [
                'opportunity' => $this->opportunity,
                'submission' => $this->submission,
                'account' => $this->account,
                'notifyUser' => $this->notifyUser,
                'opportunityUrl' => $opportunityUrl,
                'addonNameMap' => $addonNameMap,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
