<?php

declare(strict_types=1);

namespace App\Domain\Reports\Support;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Builds normalized sales-tax rows for liability / payable reports.
 *
 * @phpstan-type TaxRow array{
 *   source_type: string,
 *   source_id: int,
 *   source_label: string,
 *   customer_name: string,
 *   jurisdiction: string,
 *   tax_rate: float,
 *   taxable_amount: float,
 *   tax_amount: float,
 *   tax_collected: float,
 *   document_date: string,
 *   invoice_status: string|null,
 *   payment_status: string,
 * }
 */
final class CollectSalesTaxReportRows
{
    public const BASIS_ACCRUAL = 'accrual';

    public const BASIS_CASH = 'cash';

    /**
     * State for tax jurisdiction: lives on {@code contact_addresses}, not {@code contacts}.
     *
     * @param  string  $contactIdSql  SQL expression for contact id (e.g. {@code invoices.contact_id}, {@code contacts.id})
     */
    private static function contactPrimaryStateSubquery(string $contactIdSql): string
    {
        return '(SELECT ca.state FROM contact_addresses ca WHERE ca.contact_id = '.$contactIdSql
            .' ORDER BY ca.is_primary DESC, ca.id ASC LIMIT 1)';
    }

    /**
     * @return array{rows: list<TaxRow>, summary: array<string, float|int>}
     */
    public static function collect(
        Carbon $from,
        Carbon $to,
        ?int $subsidiaryId,
        ?int $locationId,
        string $basis = self::BASIS_ACCRUAL,
    ): array {
        $basis = $basis === self::BASIS_CASH ? self::BASIS_CASH : self::BASIS_ACCRUAL;

        $cashByInvoiceId = $basis === self::BASIS_CASH
            ? self::cashTaxCollectedByInvoiceId($from, $to, $subsidiaryId, $locationId)
            : [];

        $rows = array_merge(
            self::invoiceRows($from, $to, $subsidiaryId, $locationId, $cashByInvoiceId),
            self::uninvoicedTransactionRows($from, $to, $subsidiaryId, $locationId),
            self::uninvoicedServiceTicketRows($from, $to, $subsidiaryId, $locationId),
        );

        return [
            'rows' => $rows,
            'summary' => self::buildSummary($rows),
        ];
    }

    /**
     * @param  list<TaxRow>  $rows
     * @return list<array{jurisdiction: string, tax_rate: float, taxable_amount: float, tax_amount: float, tax_collected: float, row_count: int}>
     */
    public static function groupForLiability(array $rows): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $key = $row['jurisdiction'].'|'.self::formatRateKey($row['tax_rate']);
            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'jurisdiction' => $row['jurisdiction'],
                    'tax_rate' => (float) $row['tax_rate'],
                    'taxable_amount' => 0.0,
                    'tax_amount' => 0.0,
                    'tax_collected' => 0.0,
                    'row_count' => 0,
                ];
            }
            $groups[$key]['taxable_amount'] += $row['taxable_amount'];
            $groups[$key]['tax_amount'] += $row['tax_amount'];
            $groups[$key]['tax_collected'] += $row['tax_collected'];
            $groups[$key]['row_count']++;
        }
        ksort($groups);

        return array_values(array_map(fn (array $g) => [
            'jurisdiction' => $g['jurisdiction'],
            'tax_rate' => round($g['tax_rate'], 3),
            'taxable_amount' => round($g['taxable_amount'], 2),
            'tax_amount' => round($g['tax_amount'], 2),
            'tax_collected' => round($g['tax_collected'], 2),
            'row_count' => $g['row_count'],
        ], $groups));
    }

    /**
     * @param  list<TaxRow>  $rows
     * @return list<array{source_type: string, payment_status: string, taxable_amount: float, tax_amount: float, tax_collected: float, row_count: int}>
     */
    public static function groupForPayable(array $rows): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $key = $row['source_type'].'|'.$row['payment_status'];
            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'source_type' => $row['source_type'],
                    'payment_status' => $row['payment_status'],
                    'taxable_amount' => 0.0,
                    'tax_amount' => 0.0,
                    'tax_collected' => 0.0,
                    'row_count' => 0,
                ];
            }
            $groups[$key]['taxable_amount'] += $row['taxable_amount'];
            $groups[$key]['tax_amount'] += $row['tax_amount'];
            $groups[$key]['tax_collected'] += $row['tax_collected'];
            $groups[$key]['row_count']++;
        }
        uasort($groups, fn ($a, $b) => strcmp($a['source_type'].' '.$a['payment_status'], $b['source_type'].' '.$b['payment_status']));

        return array_values(array_map(fn (array $g) => [
            'source_type' => $g['source_type'],
            'payment_status' => $g['payment_status'],
            'taxable_amount' => round($g['taxable_amount'], 2),
            'tax_amount' => round($g['tax_amount'], 2),
            'tax_collected' => round($g['tax_collected'], 2),
            'row_count' => $g['row_count'],
        ], $groups));
    }

    public static function allocatePaymentTax(float $paymentNet, float $invoiceTotal, float $invoiceTax): float
    {
        if ($invoiceTotal <= 0 || $invoiceTax <= 0 || $paymentNet <= 0) {
            return 0.0;
        }

        return round($paymentNet / $invoiceTotal * $invoiceTax, 2);
    }

    private static function formatRateKey(float $rate): string
    {
        return number_format($rate, 3, '.', '');
    }

    /**
     * @param  list<TaxRow>  $rows
     * @return array<string, float|int>
     */
    private static function buildSummary(array $rows): array
    {
        $taxable = 0.0;
        $tax = 0.0;
        $collected = 0.0;
        $uninvoicedTax = 0.0;

        foreach ($rows as $row) {
            $taxable += $row['taxable_amount'];
            $tax += $row['tax_amount'];
            $collected += $row['tax_collected'];
            if ($row['payment_status'] === 'uninvoiced') {
                $uninvoicedTax += $row['tax_amount'];
            }
        }

        return [
            'row_count' => count($rows),
            'taxable_total' => round($taxable, 2),
            'tax_total' => round($tax, 2),
            'tax_collected_total' => round($collected, 2),
            'uninvoiced_tax_total' => round($uninvoicedTax, 2),
        ];
    }

    /**
     * @return array<int, float> invoice_id => tax collected in period
     */
    private static function cashTaxCollectedByInvoiceId(
        Carbon $from,
        Carbon $to,
        ?int $subsidiaryId,
        ?int $locationId,
    ): array {
        $totals = [];

        $paymentQuery = DB::table('payments')
            ->join('invoices', 'invoices.id', '=', 'payments.invoice_id')
            ->leftJoin('transactions', 'transactions.id', '=', 'invoices.transaction_id')
            ->whereNull('payments.deleted_at')
            ->whereNull('invoices.deleted_at')
            ->whereNotIn('invoices.status', ['draft', 'void'])
            ->whereIn('payments.status', ['completed', 'partially_refunded'])
            ->whereBetween(DB::raw('COALESCE(payments.paid_at, payments.created_at)'), [$from, $to]);

        self::applyPnlSubsidiaryLocationFilters($paymentQuery, $subsidiaryId, $locationId);

        foreach ($paymentQuery->get(['invoices.id as invoice_id', 'invoices.total as invoice_total', 'invoices.tax_total as invoice_tax', 'payments.net_amount']) as $r) {
            $id = (int) $r->invoice_id;
            $portion = self::allocatePaymentTax(
                (float) ($r->net_amount ?? 0),
                (float) ($r->invoice_total ?? 0),
                (float) ($r->invoice_tax ?? 0),
            );
            $totals[$id] = ($totals[$id] ?? 0) + $portion;
        }

        $refundQuery = DB::table('payment_refunds')
            ->join('payments', 'payments.id', '=', 'payment_refunds.payment_id')
            ->join('invoices', 'invoices.id', '=', 'payments.invoice_id')
            ->leftJoin('transactions', 'transactions.id', '=', 'invoices.transaction_id')
            ->whereNull('payments.deleted_at')
            ->whereNull('invoices.deleted_at')
            ->whereNotIn('invoices.status', ['draft', 'void'])
            ->where('payment_refunds.status', 'completed')
            ->whereBetween(DB::raw('COALESCE(payment_refunds.updated_at, payment_refunds.created_at)'), [$from, $to]);

        self::applyPnlSubsidiaryLocationFilters($refundQuery, $subsidiaryId, $locationId);

        foreach ($refundQuery->get(['invoices.id as invoice_id', 'invoices.total as invoice_total', 'invoices.tax_total as invoice_tax', 'payment_refunds.amount']) as $r) {
            $id = (int) $r->invoice_id;
            $portion = self::allocatePaymentTax(
                (float) ($r->amount ?? 0),
                (float) ($r->invoice_total ?? 0),
                (float) ($r->invoice_tax ?? 0),
            );
            $totals[$id] = ($totals[$id] ?? 0) - $portion;
        }

        foreach ($totals as $id => $v) {
            $totals[$id] = round($v, 2);
        }

        return $totals;
    }

    /**
     * @param  array<int, float>  $cashByInvoiceId
     * @return list<TaxRow>
     */
    private static function invoiceRows(
        Carbon $from,
        Carbon $to,
        ?int $subsidiaryId,
        ?int $locationId,
        array $cashByInvoiceId,
    ): array {
        $contactStateSql = self::contactPrimaryStateSubquery('invoices.contact_id');

        $jurisdictionExpr = <<<SQL
            COALESCE(
                NULLIF(TRIM(transactions.tax_jurisdiction), ''),
                NULLIF(TRIM(invoices.billing_state), ''),
                NULLIF(TRIM(transactions.billing_state), ''),
                NULLIF(TRIM({$contactStateSql}), ''),
                NULLIF(TRIM(txn_loc.state), ''),
                NULLIF(TRIM(inv_loc.state), ''),
                'Unknown'
            )
            SQL;

        $query = DB::table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->leftJoin('transactions', 'transactions.id', '=', 'invoices.transaction_id')
            ->leftJoin('locations as txn_loc', 'txn_loc.id', '=', 'transactions.location_id')
            ->leftJoin('locations as inv_loc', 'inv_loc.id', '=', 'invoices.location_id')
            ->whereNull('invoices.deleted_at')
            ->where(function ($q) {
                $q->whereNull('transactions.id')
                    ->orWhereNull('transactions.deleted_at');
            })
            ->where('invoice_items.billable_to', '=', 'customer')
            ->whereNotIn('invoices.status', ['draft', 'void'])
            ->whereBetween('invoices.created_at', [$from, $to])
            ->where(function ($q) {
                $q->where('invoice_items.tax_amount', '>', 0)
                    ->orWhere(function ($q2) {
                        $q2->where('invoice_items.taxable', '=', true)
                            ->where('invoice_items.tax_rate', '>', 0);
                    });
            });

        self::applyPnlSubsidiaryLocationFilters($query, $subsidiaryId, $locationId);

        $aggregates = $query
            ->groupBy('invoices.id', 'invoice_items.tax_rate')
            ->selectRaw(
                'invoices.id as invoice_id,'.
                ' MAX(invoices.sequence) as sequence,'.
                " MAX(COALESCE(NULLIF(TRIM(invoices.customer_name), ''), 'Unknown Customer')) as customer_name,".
                ' MAX(invoices.status) as invoice_status,'.
                ' MAX(invoices.created_at) as document_at,'.
                " MAX({$jurisdictionExpr}) as jurisdiction,".
                ' invoice_items.tax_rate as tax_rate,'.
                ' SUM(invoice_items.subtotal) as taxable_amount,'.
                ' SUM(invoice_items.tax_amount) as tax_amount'
            )
            ->havingRaw('SUM(invoice_items.tax_amount) > 0.0001')
            ->orderByRaw('MAX(invoices.created_at)')
            ->orderBy('invoices.id')
            ->get();

        $out = [];
        foreach ($aggregates as $r) {
            $invoiceId = (int) $r->invoice_id;
            $taxAmount = (float) $r->tax_amount;
            $taxRate = (float) ($r->tax_rate ?? 0);
            $status = (string) ($r->invoice_status ?? '');
            $paymentStatus = self::invoicePaymentBucket($status);

            // Cash collected is allocated across rate buckets in a second pass.
            $out[] = [
                'source_type' => 'invoice',
                'source_id' => $invoiceId,
                'source_label' => 'INV-'.((string) ($r->sequence ?? $invoiceId)),
                'customer_name' => (string) ($r->customer_name ?? 'Unknown Customer'),
                'jurisdiction' => (string) ($r->jurisdiction ?? 'Unknown'),
                'tax_rate' => $taxRate,
                'taxable_amount' => round((float) ($r->taxable_amount ?? 0), 2),
                'tax_amount' => round($taxAmount, 2),
                'tax_collected' => 0.0, // filled in second pass
                'document_date' => Carbon::parse($r->document_at)->toDateString(),
                'invoice_status' => $status,
                'payment_status' => $paymentStatus,
            ];
        }

        // Allocate cash collected across invoice rate-buckets by tax share.
        $taxSumByInvoice = [];
        foreach ($out as $row) {
            if ($row['source_type'] !== 'invoice') {
                continue;
            }
            $id = $row['source_id'];
            $taxSumByInvoice[$id] = ($taxSumByInvoice[$id] ?? 0) + $row['tax_amount'];
        }
        foreach ($out as $i => $row) {
            if ($row['source_type'] !== 'invoice') {
                continue;
            }
            $id = $row['source_id'];
            $invoiceCash = $cashByInvoiceId[$id] ?? 0.0;
            $sumTax = $taxSumByInvoice[$id] ?? 0;
            if ($sumTax > 0 && $invoiceCash != 0.0) {
                $out[$i]['tax_collected'] = round($invoiceCash * ($row['tax_amount'] / $sumTax), 2);
            }
        }

        return $out;
    }

    private static function invoicePaymentBucket(string $status): string
    {
        return match ($status) {
            'paid' => 'paid',
            'partial' => 'partial',
            default => 'open',
        };
    }

    /**
     * @return list<TaxRow>
     */
    private static function uninvoicedTransactionRows(
        Carbon $from,
        Carbon $to,
        ?int $subsidiaryId,
        ?int $locationId,
    ): array {
        $invoicedIds = DB::table('invoices')
            ->whereNull('deleted_at')
            ->whereNotNull('transaction_id')
            ->whereNotIn('status', ['draft', 'void'])
            ->pluck('transaction_id')
            ->all();

        $contactStateSql = self::contactPrimaryStateSubquery('contacts.id');

        $jurisdictionExpr = <<<SQL
            COALESCE(
                NULLIF(TRIM(transactions.tax_jurisdiction), ''),
                NULLIF(TRIM(transactions.billing_state), ''),
                NULLIF(TRIM({$contactStateSql}), ''),
                NULLIF(TRIM(txn_loc.state), ''),
                'Unknown'
            )
            SQL;

        $q = DB::table('transactions')
            ->leftJoin('customer_profiles', 'customer_profiles.id', '=', 'transactions.customer_id')
            ->leftJoin('contacts', 'contacts.id', '=', 'customer_profiles.contact_id')
            ->leftJoin('locations as txn_loc', 'txn_loc.id', '=', 'transactions.location_id')
            ->whereNull('transactions.deleted_at')
            ->whereIn('transactions.status', ['pending', 'processing'])
            ->where('transactions.tax_total', '>', 0)
            ->whereBetween(DB::raw('COALESCE(transactions.won_at, transactions.updated_at)'), [$from, $to]);

        if ($invoicedIds !== []) {
            $q->whereNotIn('transactions.id', $invoicedIds);
        }

        if ($subsidiaryId !== null) {
            $q->where('transactions.subsidiary_id', $subsidiaryId);
        }
        if ($locationId !== null) {
            $q->where('transactions.location_id', $locationId);
        }

        $rows = $q->orderBy('transactions.id')->get([
            'transactions.id',
            'transactions.sequence',
            'transactions.subtotal',
            'transactions.tax_total',
            'transactions.tax_rate',
            DB::raw($jurisdictionExpr.' as jurisdiction'),
            DB::raw("COALESCE(NULLIF(TRIM(contacts.display_name), ''), 'Unknown Customer') as customer_name"),
            DB::raw('COALESCE(transactions.won_at, transactions.updated_at) as document_at'),
        ]);

        $out = [];
        foreach ($rows as $r) {
            $tid = (int) $r->id;
            $taxTotal = (float) ($r->tax_total ?? 0);
            $subtotal = (float) ($r->subtotal ?? 0);
            $rate = (float) ($r->tax_rate ?? 0);
            $out[] = [
                'source_type' => 'transaction',
                'source_id' => $tid,
                'source_label' => 'TXN-'.((string) ($r->sequence ?? $tid)),
                'customer_name' => (string) ($r->customer_name ?? 'Unknown Customer'),
                'jurisdiction' => (string) ($r->jurisdiction ?? 'Unknown'),
                'tax_rate' => $rate,
                'taxable_amount' => round($subtotal, 2),
                'tax_amount' => round($taxTotal, 2),
                'tax_collected' => 0.0,
                'document_date' => Carbon::parse($r->document_at)->toDateString(),
                'invoice_status' => null,
                'payment_status' => 'uninvoiced',
            ];
        }

        return $out;
    }

    /**
     * @return list<TaxRow>
     */
    private static function uninvoicedServiceTicketRows(
        Carbon $from,
        Carbon $to,
        ?int $subsidiaryId,
        ?int $locationId,
    ): array {
        $draft = 1;
        $cancelled = 6;

        $contactStateSql = self::contactPrimaryStateSubquery('contacts.id');

        $q = DB::table('service_tickets')
            ->leftJoin('customer_profiles', 'customer_profiles.id', '=', 'service_tickets.customer_id')
            ->leftJoin('contacts', 'contacts.id', '=', 'customer_profiles.contact_id')
            ->leftJoin('locations as st_loc', 'st_loc.id', '=', 'service_tickets.location_id')
            ->whereNull('service_tickets.deleted_at')
            ->whereNotIn('service_tickets.status', [$draft, $cancelled])
            ->where('service_tickets.estimated_tax', '>', 0)
            ->whereNull('service_tickets.transaction_id')
            ->whereNotExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('work_orders')
                    ->join('invoices', 'invoices.work_order_id', '=', 'work_orders.id')
                    ->whereNull('work_orders.deleted_at')
                    ->whereNull('invoices.deleted_at')
                    ->whereNotIn('invoices.status', ['draft', 'void'])
                    ->whereColumn('work_orders.service_ticket_id', 'service_tickets.id');
            });

        if ($subsidiaryId !== null) {
            $q->where('service_tickets.subsidiary_id', $subsidiaryId);
        }
        if ($locationId !== null) {
            $q->where('service_tickets.location_id', $locationId);
        }

        $tickets = $q->orderBy('service_tickets.id')->get([
            'service_tickets.id',
            'service_tickets.service_ticket_number',
            'service_tickets.estimated_tax',
            'service_tickets.estimated_subtotal',
            'service_tickets.tax_rate',
            'service_tickets.signed_at',
            'service_tickets.updated_at',
            DB::raw("COALESCE(NULLIF(TRIM({$contactStateSql}), ''), NULLIF(TRIM(st_loc.state), ''), 'Unknown') as jurisdiction"),
            DB::raw("COALESCE(NULLIF(TRIM(contacts.display_name), ''), 'Unknown Customer') as customer_name"),
        ]);

        $out = [];
        foreach ($tickets as $t) {
            $ticketId = (int) $t->id;

            $woAgg = DB::table('work_orders')
                ->whereNull('deleted_at')
                ->where('service_ticket_id', $ticketId)
                ->selectRaw('COUNT(*) as c, SUM(COALESCE(estimated_tax, 0)) as tax_sum, SUM(CASE WHEN COALESCE(tax_rate, 0) > 0 THEN COALESCE(estimated_tax, 0) / (tax_rate / 100.0) ELSE 0 END) as taxable_from_wo')
                ->first();

            $woCount = (int) ($woAgg->c ?? 0);
            $taxFromWo = (float) ($woAgg->tax_sum ?? 0);
            $taxableFromWo = (float) ($woAgg->taxable_from_wo ?? 0);

            if ($woCount > 0 && $taxFromWo > 0.0001) {
                $taxAmount = $taxFromWo;
                $taxable = $taxableFromWo > 0 ? $taxableFromWo : (float) ($t->estimated_subtotal ?? 0);
                $avgRate = $taxable > 0 ? round(100 * $taxAmount / $taxable, 3) : (float) ($t->tax_rate ?? 0);
            } else {
                $taxAmount = (float) ($t->estimated_tax ?? 0);
                $taxable = (float) ($t->estimated_subtotal ?? 0);
                $avgRate = (float) ($t->tax_rate ?? 0);
            }

            $woDates = DB::table('work_orders')
                ->whereNull('deleted_at')
                ->where('service_ticket_id', $ticketId)
                ->max('completed_at');

            $docAt = $woDates ?: $t->signed_at ?: $t->updated_at;
            if (! $docAt) {
                continue;
            }
            $docCarbon = Carbon::parse($docAt);
            if ($docCarbon->lt($from) || $docCarbon->gt($to)) {
                continue;
            }

            $out[] = [
                'source_type' => 'service_ticket',
                'source_id' => $ticketId,
                'source_label' => (string) ($t->service_ticket_number ?? ('ST-'.$ticketId)),
                'customer_name' => (string) ($t->customer_name ?? 'Unknown Customer'),
                'jurisdiction' => (string) ($t->jurisdiction ?? 'Unknown'),
                'tax_rate' => $avgRate,
                'taxable_amount' => round($taxable, 2),
                'tax_amount' => round($taxAmount, 2),
                'tax_collected' => 0.0,
                'document_date' => $docCarbon->toDateString(),
                'invoice_status' => null,
                'payment_status' => 'uninvoiced',
            ];
        }

        return $out;
    }

    private static function applyPnlSubsidiaryLocationFilters(Builder $query, ?int $subsidiaryId, ?int $locationId): void
    {
        if ($subsidiaryId !== null) {
            $query->where(function ($q) use ($subsidiaryId) {
                $q->where('transactions.subsidiary_id', $subsidiaryId)
                    ->orWhere(function ($q2) use ($subsidiaryId) {
                        $q2->whereNull('transactions.id')
                            ->where('invoices.subsidiary_id', $subsidiaryId);
                    });
            });
        }
        if ($locationId !== null) {
            $query->where(function ($q) use ($locationId) {
                $q->where('transactions.location_id', $locationId)
                    ->orWhere(function ($q2) use ($locationId) {
                        $q2->whereNull('transactions.id')
                            ->where('invoices.location_id', $locationId);
                    });
            });
        }
    }
}
