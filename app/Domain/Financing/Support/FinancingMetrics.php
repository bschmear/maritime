<?php

declare(strict_types=1);

namespace App\Domain\Financing\Support;

use App\Domain\Financing\Models\Financing;
use App\Enums\Financing\BillType;
use App\Enums\Financing\Status;
use App\Models\AccountSettings;
use Carbon\Carbon;

class FinancingMetrics
{
    public function __construct(
        private readonly Financing $financing,
    ) {}

    public function toArray(): array
    {
        return [
            'days_financed' => $this->daysFinanced(),
            'interest_days_elapsed' => $this->interestDaysElapsed(),
            'interest_months_elapsed' => $this->interestMonthsElapsed(),
            'loan_term_months' => $this->loanTermMonths(),
            'total_interest_paid' => $this->totalInterestPaid(),
            'total_principal_paid' => $this->totalPrincipalPaid(),
            'accrued_interest' => $this->accruedInterest(),
            'estimated_total_interest' => $this->estimatedTotalInterest(),
            'estimated_monthly_interest' => $this->estimatedMonthlyInterest(),
            'estimated_monthly_payment' => $this->estimatedMonthlyPayment(),
            'total_interest_cost' => $this->totalInterestCost(),
            'monthly_payment_due' => $this->monthlyPaymentDue(),
            'next_payment_date' => $this->financing->next_payment_date?->toDateString(),
            'at_risk' => $this->isAtRisk(),
            'days_threshold' => $this->daysThreshold(),
            'interest_threshold' => $this->interestThreshold(),
        ];
    }

    /**
     * Inventory aging — prefers lender-reported aging_days, else days since financed_at.
     */
    public function daysFinanced(): int
    {
        if ($this->financing->aging_days !== null) {
            return (int) $this->financing->aging_days;
        }

        $start = $this->financing->financed_at;

        if ($start === null) {
            return 0;
        }

        return (int) max(0, $start->diffInDays(Carbon::today()));
    }

    /**
     * Days used for interest accrual — never uses lender aging_days.
     */
    public function interestDaysElapsed(): int
    {
        $anchor = $this->interestAccrualStart();

        if ($anchor === null) {
            return 0;
        }

        return (int) max(0, $anchor->diffInDays(Carbon::today()));
    }

    public function interestMonthsElapsed(): float
    {
        $days = $this->interestDaysElapsed();

        if ($days <= 0) {
            return 0.0;
        }

        return round($days / 30.4375, 1);
    }

    public function loanTermMonths(): ?int
    {
        $months = $this->financing->loan_term_months;

        return $months !== null && (int) $months > 0 ? (int) $months : null;
    }

    public function totalInterestPaid(): float
    {
        return $this->sumPaymentsForBillType(BillType::Interest);
    }

    public function totalPrincipalPaid(): float
    {
        return $this->sumPaymentsForBillType(BillType::Principal);
    }

    /**
     * Simple interest accrued on original principal since interest start (or invoice date).
     */
    public function accruedInterest(): float
    {
        $principal = (float) ($this->financing->principal_amount ?? 0);
        $rate = (float) ($this->financing->annual_interest_rate ?? 0);
        $days = $this->interestDaysElapsed();

        if ($principal <= 0 || $rate <= 0 || $days <= 0) {
            return 0.0;
        }

        return round($principal * ($rate / 100) * ($days / 365), 2);
    }

    /**
     * Total simple interest over the full loan term (principal × rate × term in years).
     */
    public function estimatedTotalInterest(): ?float
    {
        $principal = (float) ($this->financing->principal_amount ?? 0);
        $rate = (float) ($this->financing->annual_interest_rate ?? 0);
        $termMonths = $this->loanTermMonths();

        if ($principal <= 0 || $rate <= 0 || $termMonths === null) {
            return null;
        }

        return round($principal * ($rate / 100) * ($termMonths / 12), 2);
    }

    /**
     * Monthly interest-only estimate on principal.
     */
    public function estimatedMonthlyInterest(): ?float
    {
        $principal = (float) ($this->financing->principal_amount ?? 0);
        $rate = (float) ($this->financing->annual_interest_rate ?? 0);

        if ($principal <= 0 || $rate <= 0) {
            return null;
        }

        return round($principal * ($rate / 100) / 12, 2);
    }

    /**
     * Estimated monthly payment — stored value, or interest-only estimate when term is set.
     */
    public function estimatedMonthlyPayment(): ?float
    {
        if ($this->financing->monthly_payment_amount !== null) {
            return (float) $this->financing->monthly_payment_amount;
        }

        return $this->estimatedMonthlyInterest();
    }

    public function totalInterestCost(): float
    {
        return round($this->totalInterestPaid() + $this->accruedInterest(), 2);
    }

    public function monthlyPaymentDue(): ?float
    {
        $openBill = $this->financing->bills()
            ->where('financing_bill_type', BillType::Interest->value)
            ->where('balance', '>', 0)
            ->orderBy('due_date')
            ->first();

        if ($openBill !== null) {
            return (float) $openBill->balance;
        }

        return $this->estimatedMonthlyPayment();
    }

    public function daysThreshold(): ?int
    {
        if ($this->financing->days_alert_threshold !== null) {
            return (int) $this->financing->days_alert_threshold;
        }

        $accountDays = AccountSettings::getCurrent()->financing_max_days_in_inventory;
        if ($accountDays === null || (int) $accountDays <= 0) {
            return null;
        }

        return (int) $accountDays;
    }

    public function interestThreshold(): ?float
    {
        if ($this->financing->interest_alert_threshold !== null) {
            return (float) $this->financing->interest_alert_threshold;
        }

        $accountInterest = AccountSettings::getCurrent()->financing_interest_alert_amount;
        if ($accountInterest === null || (float) $accountInterest <= 0) {
            return null;
        }

        return (float) $accountInterest;
    }

    public function isAtRisk(): bool
    {
        if ($this->financing->status !== Status::Active) {
            return false;
        }

        $daysThreshold = $this->daysThreshold();
        $interestThreshold = $this->interestThreshold();

        if ($daysThreshold === null && $interestThreshold === null) {
            return false;
        }

        $daysExceeded = $daysThreshold !== null && $this->daysFinanced() >= $daysThreshold;
        $interestExceeded = $interestThreshold !== null && $this->totalInterestCost() >= $interestThreshold;

        if ($daysThreshold !== null && $interestThreshold !== null) {
            return $daysExceeded && $interestExceeded;
        }

        return $daysExceeded || $interestExceeded;
    }

    private function interestAccrualStart(): ?Carbon
    {
        $lastBillDate = $this->financing->bills()
            ->where('financing_bill_type', BillType::Interest->value)
            ->max('txn_date');

        if ($lastBillDate !== null) {
            return $lastBillDate instanceof Carbon ? $lastBillDate : Carbon::parse($lastBillDate);
        }

        $start = $this->financing->interest_start_date
            ?? $this->financing->financed_at;

        return $start;
    }

    private function sumPaymentsForBillType(BillType $type): float
    {
        $this->financing->loadMissing([
            'bills.billPaymentLines',
        ]);

        $total = 0.0;

        foreach ($this->financing->bills as $bill) {
            if ($bill->financing_bill_type !== $type->value) {
                continue;
            }

            foreach ($bill->billPaymentLines as $line) {
                $total += (float) ($line->amount ?? 0);
            }
        }

        return round($total, 2);
    }
}
