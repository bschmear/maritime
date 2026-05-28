<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Invoice\Models\Invoice;
use App\Services\Payments\QuickBooksAccountingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

class PushInvoiceToQuickBooks implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $invoiceId,
    ) {}

    /**
     * @return array{success: bool, invoice_id?: string, invoice_url?: string, message?: string}
     */
    public function handle(QuickBooksAccountingService $accounting): array
    {
        $invoice = Invoice::query()->find($this->invoiceId);
        if ($invoice === null) {
            return ['success' => false, 'message' => 'Invoice not found.'];
        }

        return $accounting->pushInvoice($invoice);
    }

    /**
     * Run synchronously and throw on failure (for send-to-customer gate).
     *
     * @return array{success: bool, invoice_id?: string, invoice_url?: string, message?: string}
     */
    public static function runSync(int $invoiceId): array
    {
        $job = new self($invoiceId);
        $result = $job->handle(app(QuickBooksAccountingService::class));

        if (! ($result['success'] ?? false)) {
            throw new RuntimeException($result['message'] ?? 'Failed to sync invoice to QuickBooks.');
        }

        return $result;
    }
}
