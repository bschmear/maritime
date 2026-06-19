<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Bill\Models\Bill;
use App\Services\Payments\QuickBooksAccountingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

class PushBillToQuickBooks implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $billId,
        public bool $update = false,
    ) {}

    /**
     * @return array{success: bool, bill_id?: string, message?: string}
     */
    public function handle(QuickBooksAccountingService $accounting): array
    {
        $bill = Bill::query()->find($this->billId);
        if ($bill === null) {
            return ['success' => false, 'message' => 'Bill not found.'];
        }

        return $this->update
            ? $accounting->updateBill($bill)
            : $accounting->pushBill($bill);
    }

    /**
     * @return array{success: bool, bill_id?: string, message?: string}
     */
    public static function runSync(int $billId, bool $update = false): array
    {
        $job = new self($billId, $update);
        $result = $job->handle(app(QuickBooksAccountingService::class));

        if (! ($result['success'] ?? false)) {
            throw new RuntimeException($result['message'] ?? 'Failed to sync bill to QuickBooks.');
        }

        return $result;
    }
}
