<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Contact\Models\Contact;
use App\Domain\Integration\Support\QuickBooksSettings;
use App\Services\Payments\QuickBooksAccountingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PushContactToQuickBooks implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $contactId,
    ) {}

    public function handle(QuickBooksAccountingService $accounting): void
    {
        $settings = QuickBooksSettings::forCurrentTenant();
        if (! $settings->isSyncContactsEnabled()) {
            return;
        }

        if (! $accounting->isConnected()) {
            return;
        }

        $contact = Contact::query()->find($this->contactId);
        if ($contact === null || $contact->quickbooks_customer_id) {
            return;
        }

        $result = $accounting->pushContact($contact);
        if (! ($result['success'] ?? false)) {
            Log::warning('PushContactToQuickBooks failed', [
                'contact_id' => $this->contactId,
                'message' => $result['message'] ?? null,
            ]);
        }
    }
}
