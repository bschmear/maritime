<?php

declare(strict_types=1);

namespace App\Support\QuickBooks;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class QuickBooksImportDateRange
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{txn_date_from: string, txn_date_to: string}
     */
    public static function validate(array $data): array
    {
        $validated = Validator::make($data, [
            'txn_date_from' => ['required', 'date', 'before_or_equal:txn_date_to'],
            'txn_date_to' => ['required', 'date', 'after_or_equal:txn_date_from'],
        ])->validate();

        $from = Carbon::parse($validated['txn_date_from'])->startOfDay();
        $to = Carbon::parse($validated['txn_date_to'])->startOfDay();

        if ($to->gt($from->copy()->addYear())) {
            throw ValidationException::withMessages([
                'txn_date_to' => ['The date range cannot exceed one year.'],
            ]);
        }

        return [
            'txn_date_from' => $from->toDateString(),
            'txn_date_to' => $to->toDateString(),
        ];
    }

    public static function billQuery(string $from, string $to, int $start, int $pageSize): string
    {
        return sprintf(
            "select * from Bill where TxnDate >= '%s' and TxnDate <= '%s' STARTPOSITION %d MAXRESULTS %d",
            $from,
            $to,
            $start,
            $pageSize,
        );
    }

    public static function billPaymentQuery(string $from, string $to, int $start, int $pageSize): string
    {
        return sprintf(
            "select * from BillPayment where TxnDate >= '%s' and TxnDate <= '%s' STARTPOSITION %d MAXRESULTS %d",
            $from,
            $to,
            $start,
            $pageSize,
        );
    }

    public static function allBillsQuery(int $start, int $pageSize): string
    {
        return "select * from Bill STARTPOSITION {$start} MAXRESULTS {$pageSize}";
    }

    public static function allBillPaymentsQuery(int $start, int $pageSize): string
    {
        return "select * from BillPayment STARTPOSITION {$start} MAXRESULTS {$pageSize}";
    }
}
