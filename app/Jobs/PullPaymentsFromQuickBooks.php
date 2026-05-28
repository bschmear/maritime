<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Integration\Models\Integration;
use App\Domain\Integration\Support\QuickBooksSettings;
use App\Domain\Invoice\Models\Invoice;
use App\Domain\Payment\Actions\StoreRecordedPayment;
use App\Domain\Payment\Models\Payment;
use App\Enums\Integration\IntegrationType;
use App\Enums\Payments\PaymentProcessor;
use App\Services\Payments\QuickBooksAccountingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PullPaymentsFromQuickBooks implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $invoiceId,
        public ?int $recordedByUserId = null,
    ) {}

    /**
     * @return array{imported: int, skipped: int, message?: string}
     */
    public function handle(
        QuickBooksAccountingService $accounting,
        StoreRecordedPayment $storePayment,
    ): array {
        $settings = QuickBooksSettings::forCurrentTenant();
        if (! $settings->isSyncPaymentsEnabled()) {
            return ['imported' => 0, 'skipped' => 0, 'message' => 'Payment sync is not enabled.'];
        }

        $integration = Integration::query()
            ->where('integration_type', IntegrationType::QuickBooks)
            ->first();

        if (! $integration?->access_token) {
            return ['imported' => 0, 'skipped' => 0, 'message' => 'QuickBooks is not connected.'];
        }

        $invoice = Invoice::query()->find($this->invoiceId);
        if ($invoice === null || ! $invoice->quickbooks_invoice_id) {
            return ['imported' => 0, 'skipped' => 0, 'message' => 'Invoice is not linked to QuickBooks.'];
        }

        try {
            $rows = $accounting->paymentsForInvoice($integration, (string) $invoice->quickbooks_invoice_id);
        } catch (\Throwable $e) {
            Log::error('PullPaymentsFromQuickBooks query failed', [
                'invoice_id' => $this->invoiceId,
                'error' => $e->getMessage(),
            ]);

            return ['imported' => 0, 'skipped' => 0, 'message' => $e->getMessage()];
        }

        $imported = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $qboPaymentId = isset($row['Id']) ? (string) $row['Id'] : '';
            if ($qboPaymentId === '') {
                $skipped++;

                continue;
            }

            $exists = Payment::query()
                ->where('invoice_id', $invoice->id)
                ->where('processor', PaymentProcessor::Quickbooks->value)
                ->where('processor_transaction_id', $qboPaymentId)
                ->exists();

            if ($exists) {
                $skipped++;

                continue;
            }

            $amount = $this->resolvePaymentAmount($row, $invoice);
            if ($amount <= 0) {
                $skipped++;

                continue;
            }

            $paidAt = null;
            if (! empty($row['TxnDate'])) {
                $paidAt = (string) $row['TxnDate'];
            }

            try {
                $storePayment([
                    'invoice_id' => $invoice->id,
                    'amount' => $amount,
                    'payment_method_code' => 'check',
                    'processor' => PaymentProcessor::Quickbooks->value,
                    'reference_number' => $row['PaymentRefNum'] ?? null,
                    'memo' => 'Imported from QuickBooks',
                    'paid_at' => $paidAt,
                    'apply_to_invoice' => true,
                    'processor_transaction_id' => $qboPaymentId,
                ], $this->recordedByUserId);
                $invoice->refresh();
                $imported++;
            } catch (\Throwable $e) {
                Log::warning('PullPaymentsFromQuickBooks: could not store payment', [
                    'invoice_id' => $invoice->id,
                    'qbo_payment_id' => $qboPaymentId,
                    'error' => $e->getMessage(),
                ]);
                $skipped++;
            }
        }

        return ['imported' => $imported, 'skipped' => $skipped];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function resolvePaymentAmount(array $row, Invoice $invoice): float
    {
        if (isset($row['TotalAmt'])) {
            return round((float) $row['TotalAmt'], 2);
        }

        $lines = $row['Line'] ?? [];
        if ($lines !== [] && ! array_is_list($lines)) {
            $lines = [$lines];
        }

        $sum = 0.0;
        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }
            $linked = $line['LinkedTxn'] ?? [];
            if ($linked !== [] && ! array_is_list($linked)) {
                $linked = [$linked];
            }
            foreach ($linked as $txn) {
                if (! is_array($txn)) {
                    continue;
                }
                if (($txn['TxnType'] ?? '') === 'Invoice' && (string) ($txn['TxnId'] ?? '') === (string) $invoice->quickbooks_invoice_id) {
                    $sum += (float) ($line['Amount'] ?? 0);
                }
            }
        }

        return round($sum, 2);
    }

    /**
     * @return array{imported: int, skipped: int, message?: string}
     */
    public static function runSync(int $invoiceId, ?int $recordedByUserId = null): array
    {
        $job = new self($invoiceId, $recordedByUserId);

        return $job->handle(
            app(QuickBooksAccountingService::class),
            app(StoreRecordedPayment::class),
        );
    }
}
