<?php

namespace Tests\Unit;

use App\Domain\Transaction\Models\Transaction;
use App\Enums\Transaction\TransactionStatus;
use PHPUnit\Framework\TestCase;

class TransactionIsCompletedTest extends TestCase
{
    public function test_completed_status_values(): void
    {
        $transaction = new Transaction;

        $transaction->status = TransactionStatus::Completed->value;
        $this->assertTrue($transaction->isCompleted());

        $transaction->status = 'won';
        $this->assertTrue($transaction->isCompleted());

        $transaction->status = (string) TransactionStatus::Completed->id();
        $this->assertTrue($transaction->isCompleted());
    }

    public function test_non_completed_status_values(): void
    {
        $transaction = new Transaction;

        $transaction->status = TransactionStatus::Pending->value;
        $this->assertFalse($transaction->isCompleted());

        $transaction->status = TransactionStatus::Processing->value;
        $this->assertFalse($transaction->isCompleted());
    }
}
