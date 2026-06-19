<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\BillPayment\Models\BillPayment;
use App\Services\Payments\QuickBooksAccountingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

class PushBillPaymentToQuickBooks implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $billPaymentId,
        public bool $update = false,
    ) {}

    /**
     * @return array{success: bool, bill_payment_id?: string, message?: string}
     */
    public function handle(QuickBooksAccountingService $accounting): array
    {
        $payment = BillPayment::query()->find($this->billPaymentId);
        if ($payment === null) {
            return ['success' => false, 'message' => 'Bill payment not found.'];
        }

        return $this->update
            ? $accounting->updateBillPayment($payment)
            : $accounting->pushBillPayment($payment);
    }

    /**
     * @return array{success: bool, bill_payment_id?: string, message?: string}
     */
    public static function runSync(int $billPaymentId, bool $update = false): array
    {
        $job = new self($billPaymentId, $update);
        $result = $job->handle(app(QuickBooksAccountingService::class));

        if (! ($result['success'] ?? false)) {
            throw new RuntimeException($result['message'] ?? 'Failed to sync bill payment to QuickBooks.');
        }

        return $result;
    }
}
