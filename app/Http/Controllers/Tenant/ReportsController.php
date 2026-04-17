<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Invoice\Models\Invoice;
use App\Domain\InvoiceItem\Models\InvoiceItem;
use App\Domain\Location\Models\Location;
use App\Domain\ServiceTicketServiceItem\Models\ServiceTicketServiceItem;
use App\Domain\Subsidiary\Models\Subsidiary;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ReportsController extends Controller
{
    public function pnl(Request $request)
    {
        $defaultFrom = now()->subDays(29)->toDateString();
        $defaultTo = now()->toDateString();
        [$from, $to, $dateFrom, $dateTo] = $this->resolveDateRange($request, $defaultFrom, $defaultTo);
        $subsidiaryId = $request->integer('subsidiary_id') ?: null;
        $locationId = $request->integer('location_id') ?: null;

        $boatSalesQ = DB::table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->leftJoin('transactions', 'transactions.id', '=', 'invoices.transaction_id')
            ->whereNotIn('invoices.status', ['draft', 'void'])
            ->whereBetween('invoices.created_at', [$from, $to])
            ->where('invoice_items.itemable_type', \App\Domain\Asset\Models\Asset::class);
        $this->applySubsidiaryLocationFilters($boatSalesQ, $subsidiaryId, $locationId, 'transactions');
        $boatSales = (float) ($boatSalesQ->sum('invoice_items.total') ?? 0);

        $partsSalesQ = DB::table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->leftJoin('transactions', 'transactions.id', '=', 'invoices.transaction_id')
            ->whereNotIn('invoices.status', ['draft', 'void'])
            ->whereBetween('invoices.created_at', [$from, $to])
            ->where('invoice_items.itemable_type', \App\Domain\InventoryItem\Models\InventoryItem::class);
        $this->applySubsidiaryLocationFilters($partsSalesQ, $subsidiaryId, $locationId, 'transactions');
        $partsSales = (float) ($partsSalesQ->sum('invoice_items.total') ?? 0);

        // Service ticket items are the canonical source for service billable/cost lines.
        $serviceRevenueQ = ServiceTicketServiceItem::query()
            ->join('service_tickets', 'service_tickets.id', '=', 'service_ticket_service_items.service_ticket_id')
            ->whereBetween('service_tickets.created_at', [$from, $to])
            ->where('service_ticket_service_items.inactive', false);
        if ($subsidiaryId !== null) {
            $serviceRevenueQ->where('service_tickets.subsidiary_id', $subsidiaryId);
        }
        if ($locationId !== null) {
            $serviceRevenueQ->where('service_tickets.location_id', $locationId);
        }
        $serviceRevenue = (float) ($serviceRevenueQ->sum('service_ticket_service_items.total_price') ?? 0);

        $boatCostQ = DB::table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->leftJoin('transactions', 'transactions.id', '=', 'invoices.transaction_id')
            ->leftJoin('assets', function ($join) {
                $join->on('assets.id', '=', 'invoice_items.itemable_id')
                    ->where('invoice_items.itemable_type', '=', \App\Domain\Asset\Models\Asset::class);
            })
            ->leftJoin('asset_variants', 'asset_variants.id', '=', 'invoice_items.asset_variant_id')
            ->whereNotIn('invoices.status', ['draft', 'void'])
            ->whereBetween('invoices.created_at', [$from, $to])
            ->where('invoice_items.itemable_type', \App\Domain\Asset\Models\Asset::class);
        $this->applySubsidiaryLocationFilters($boatCostQ, $subsidiaryId, $locationId, 'transactions');
        $boatCost = (float) ($boatCostQ->sum(DB::raw('invoice_items.quantity * COALESCE(asset_variants.default_cost, assets.default_cost, 0)')) ?? 0);

        $partsCostQ = DB::table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->leftJoin('transactions', 'transactions.id', '=', 'invoices.transaction_id')
            ->leftJoin('inventory_items', function ($join) {
                $join->on('inventory_items.id', '=', 'invoice_items.itemable_id')
                    ->where('invoice_items.itemable_type', '=', \App\Domain\InventoryItem\Models\InventoryItem::class);
            })
            ->whereNotIn('invoices.status', ['draft', 'void'])
            ->whereBetween('invoices.created_at', [$from, $to])
            ->where('invoice_items.itemable_type', \App\Domain\InventoryItem\Models\InventoryItem::class);
        $this->applySubsidiaryLocationFilters($partsCostQ, $subsidiaryId, $locationId, 'transactions');
        $partsCost = (float) ($partsCostQ->sum(DB::raw('invoice_items.quantity * COALESCE(inventory_items.default_cost, 0)')) ?? 0);

        $serviceCostQ = ServiceTicketServiceItem::query()
            ->join('service_tickets', 'service_tickets.id', '=', 'service_ticket_service_items.service_ticket_id')
            ->whereBetween('service_tickets.created_at', [$from, $to])
            ->where('service_ticket_service_items.inactive', false);
        if ($subsidiaryId !== null) {
            $serviceCostQ->where('service_tickets.subsidiary_id', $subsidiaryId);
        }
        if ($locationId !== null) {
            $serviceCostQ->where('service_tickets.location_id', $locationId);
        }
        $serviceCost = (float) ($serviceCostQ->sum('service_ticket_service_items.total_cost') ?? 0);

        $income = [
            'boat_sales' => $boatSales,
            'service_revenue' => $serviceRevenue,
            'parts_accessories' => $partsSales,
        ];
        $totalIncome = array_sum($income);

        $cogs = [
            'boat_cost' => $boatCost,
            'service_cost' => $serviceCost,
            'parts_cost' => $partsCost,
        ];
        $totalCogs = array_sum($cogs);
        $grossProfit = $totalIncome - $totalCogs;

        // Placeholder until expense accounts are mapped.
        $totalExpenses = 0.0;
        $netProfit = $grossProfit - $totalExpenses;

        $subsidiaries = Subsidiary::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($s) => [
                'id' => (int) $s->id,
                'label' => trim((string) ($s->display_name ?? $s->name ?? ('Subsidiary #'.$s->id))),
            ])
            ->values()
            ->all();

        $locations = Location::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($l) => [
                'id' => (int) $l->id,
                'label' => trim((string) ($l->display_name ?? $l->name ?? ('Location #'.$l->id))),
            ])
            ->values()
            ->all();

        return Inertia::render('Tenant/Reports/Pnl', [
            'recordTitle' => 'Profit & Loss',
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'subsidiary_id' => $subsidiaryId,
                'location_id' => $locationId,
            ],
            'dateRange' => sprintf('%s - %s', $from->toDateString(), $to->toDateString()),
            'options' => [
                'subsidiaries' => $subsidiaries,
                'locations' => $locations,
            ],
            'report' => [
                'income' => $income,
                'total_income' => $totalIncome,
                'cogs' => $cogs,
                'total_cogs' => $totalCogs,
                'gross_profit' => $grossProfit,
                'total_expenses' => $totalExpenses,
                'net_profit' => $netProfit,
            ],
        ]);
    }

    public function balanceSheet(Request $request)
    {
        return Inertia::render('Tenant/Reports/BalanceSheet');
    }

    public function cashFlow(Request $request)
    {
        return Inertia::render('Tenant/Reports/CashFlow');
    }

    public function salesTaxLiability(Request $request)
    {
        return Inertia::render('Tenant/Reports/SalesTaxLiability');
    }

    public function salesTaxPayable(Request $request)
    {
        return Inertia::render('Tenant/Reports/SalesTaxPayable');
    }

    public function salesByCustomer(Request $request)
    {
        [$from, $to, $dateFrom, $dateTo] = $this->resolveDateRange($request);
        $view = strtolower(trim((string) $request->query('view', 'summary')));
        if (! in_array($view, ['summary', 'detail'], true)) {
            $view = 'summary';
        }

        /** @var Collection<int, object> $groups */
        $groups = $this->invoiceBaseQuery($from, $to)
            ->selectRaw(
                'contact_id,
                MAX(customer_name) as customer_name,
                COUNT(*) as invoice_count,
                SUM(total) as total_sales,
                SUM(subtotal) as total_subtotal,
                SUM(tax_total) as total_tax,
                SUM(amount_paid) as total_paid,
                SUM(amount_due) as total_due'
            )
            ->groupBy('contact_id')
            ->orderByDesc('total_sales')
            ->get();

        $rows = $groups->map(function ($group) {
            return [
                'contact_id' => $group->contact_id ? (int) $group->contact_id : null,
                'customer_name' => trim((string) ($group->customer_name ?? '')) ?: 'Unknown Customer',
                'invoice_count' => (int) $group->invoice_count,
                'total_sales' => (float) $group->total_sales,
                'total_subtotal' => (float) $group->total_subtotal,
                'total_tax' => (float) $group->total_tax,
                'total_paid' => (float) $group->total_paid,
                'total_due' => (float) $group->total_due,
            ];
        })->values();

        $summary = [
            'customer_count' => $rows->count(),
            'invoice_count' => (int) $rows->sum('invoice_count'),
            'total_sales' => (float) $rows->sum('total_sales'),
            'total_subtotal' => (float) $rows->sum('total_subtotal'),
            'total_tax' => (float) $rows->sum('total_tax'),
            'total_paid' => (float) $rows->sum('total_paid'),
            'total_due' => (float) $rows->sum('total_due'),
        ];

        $detailRows = [];
        if ($view === 'detail') {
            /** @var Collection<int, Invoice> $invoices */
            $invoices = $this->invoiceBaseQuery($from, $to)
                ->orderBy('customer_name')
                ->orderByDesc('created_at')
                ->get([
                    'id',
                    'sequence',
                    'contact_id',
                    'customer_name',
                    'status',
                    'total',
                    'subtotal',
                    'tax_total',
                    'amount_paid',
                    'amount_due',
                    'created_at',
                ]);

            $detailRows = $invoices->map(fn (Invoice $i) => [
                'id' => (int) $i->id,
                'invoice_id' => (int) $i->id,
                'invoice_label' => $i->display_name,
                'contact_id' => $i->contact_id ? (int) $i->contact_id : null,
                'customer_name' => trim((string) ($i->customer_name ?? '')) ?: 'Unknown Customer',
                'status' => (string) ($i->status ?? ''),
                'created_at' => $i->created_at?->toIso8601String(),
                'total' => (float) $i->total,
                'subtotal' => (float) $i->subtotal,
                'tax_total' => (float) $i->tax_total,
                'amount_paid' => (float) $i->amount_paid,
                'amount_due' => (float) $i->amount_due,
            ])->values()->all();
        }

        return Inertia::render('Tenant/Reports/SalesByCustomer', [
            'recordTitle' => 'Sales By Customer',
            'rows' => $rows,
            'detailRows' => $detailRows,
            'summary' => $summary,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'dateRange' => sprintf('%s - %s', $from->toDateString(), $to->toDateString()),
            'viewMode' => $view,
        ]);
    }

    public function salesByCustomerInvoices(Request $request, int $contact): JsonResponse
    {
        [$from, $to] = $this->resolveDateRange($request);

        /** @var Collection<int, Invoice> $invoices */
        $invoices = $this->invoiceBaseQuery($from, $to)
            ->where('contact_id', $contact)
            ->orderByDesc('created_at')
            ->get([
                'id',
                'sequence',
                'contact_id',
                'customer_name',
                'status',
                'total',
                'subtotal',
                'tax_total',
                'amount_paid',
                'amount_due',
                'created_at',
            ]);

        $rows = $invoices->map(fn (Invoice $i) => [
            'id' => (int) $i->id,
            'invoice_id' => (int) $i->id,
            'invoice_label' => $i->display_name,
            'contact_id' => $i->contact_id ? (int) $i->contact_id : null,
            'customer_name' => trim((string) ($i->customer_name ?? '')) ?: 'Unknown Customer',
            'status' => (string) ($i->status ?? ''),
            'created_at' => $i->created_at?->toIso8601String(),
            'total' => (float) $i->total,
            'subtotal' => (float) $i->subtotal,
            'tax_total' => (float) $i->tax_total,
            'amount_paid' => (float) $i->amount_paid,
            'amount_due' => (float) $i->amount_due,
        ])->values();

        return response()->json([
            'contact_id' => $contact,
            'summary' => [
                'invoice_count' => (int) $rows->count(),
                'total_sales' => (float) $rows->sum('total'),
                'total_subtotal' => (float) $rows->sum('subtotal'),
                'total_tax' => (float) $rows->sum('tax_total'),
                'total_paid' => (float) $rows->sum('amount_paid'),
                'total_due' => (float) $rows->sum('amount_due'),
            ],
            'rows' => $rows->all(),
        ]);
    }

    public function salesByItemSummary(Request $request)
    {
        return $this->salesByItem($request, 'summary');
    }

    public function salesByItemDetail(Request $request)
    {
        return $this->salesByItem($request, 'detail');
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: string, 3: string}
     */
    private function resolveDateRange(Request $request, ?string $defaultFrom = null, ?string $defaultTo = null): array
    {
        $defaultFrom = $defaultFrom ?: now()->startOfYear()->toDateString();
        $defaultTo = $defaultTo ?: now()->endOfYear()->toDateString();

        $dateFrom = $request->string('date_from')->toString() ?: $defaultFrom;
        $dateTo = $request->string('date_to')->toString() ?: $defaultTo;

        $from = Carbon::parse($dateFrom)->startOfDay();
        $to = Carbon::parse($dateTo)->endOfDay();

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
            [$dateFrom, $dateTo] = [$from->toDateString(), $to->toDateString()];
        }

        return [$from, $to, $dateFrom, $dateTo];
    }

    private function invoiceBaseQuery(Carbon $from, Carbon $to)
    {
        return Invoice::query()
            ->whereNotIn('status', ['draft', 'void'])
            ->whereBetween('created_at', [$from, $to]);
    }

    private function salesByItem(Request $request, string $mode)
    {
        [$from, $to, $dateFrom, $dateTo] = $this->resolveDateRange($request);
        $mode = $mode === 'detail' ? 'detail' : 'summary';

        /** @var Collection<int, object> $summaryRows */
        $summaryRows = InvoiceItem::query()
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->leftJoin('asset_variants', 'asset_variants.id', '=', 'invoice_items.asset_variant_id')
            ->whereNotIn('invoices.status', ['draft', 'void'])
            ->whereBetween('invoices.created_at', [$from, $to])
            ->selectRaw(
                "CASE
                    WHEN invoice_items.asset_variant_id IS NOT NULL
                        AND COALESCE(NULLIF(asset_variants.display_name, ''), NULLIF(asset_variants.name, '')) IS NOT NULL
                    THEN CONCAT(invoice_items.name, ' - ', COALESCE(NULLIF(asset_variants.display_name, ''), NULLIF(asset_variants.name, '')))
                    ELSE invoice_items.name
                END as item_label,
                COUNT(*) as line_count,
                SUM(invoice_items.quantity) as quantity_total,
                SUM(invoice_items.subtotal) as subtotal_total,
                SUM(invoice_items.tax_amount) as tax_total,
                SUM(invoice_items.total) as total_sales"
            )
            ->groupByRaw(
                "CASE
                    WHEN invoice_items.asset_variant_id IS NOT NULL
                        AND COALESCE(NULLIF(asset_variants.display_name, ''), NULLIF(asset_variants.name, '')) IS NOT NULL
                    THEN CONCAT(invoice_items.name, ' - ', COALESCE(NULLIF(asset_variants.display_name, ''), NULLIF(asset_variants.name, '')))
                    ELSE invoice_items.name
                END"
            )
            ->orderByDesc('total_sales')
            ->get();

        $rows = $summaryRows->map(fn ($row) => [
            'item_name' => trim((string) ($row->item_label ?? '')) ?: 'Unnamed Item',
            'line_count' => (int) $row->line_count,
            'quantity_total' => (float) $row->quantity_total,
            'subtotal_total' => (float) $row->subtotal_total,
            'tax_total' => (float) $row->tax_total,
            'total_sales' => (float) $row->total_sales,
        ])->values()->all();

        $summary = [
            'item_count' => count($rows),
            'line_count' => (int) collect($rows)->sum('line_count'),
            'quantity_total' => (float) collect($rows)->sum('quantity_total'),
            'subtotal_total' => (float) collect($rows)->sum('subtotal_total'),
            'tax_total' => (float) collect($rows)->sum('tax_total'),
            'total_sales' => (float) collect($rows)->sum('total_sales'),
        ];

        $detailRows = [];
        if ($mode === 'detail') {
            /** @var Collection<int, InvoiceItem> $items */
            $items = InvoiceItem::query()
                ->with([
                    'invoice' => fn ($q) => $q->select(['id', 'sequence', 'customer_name', 'created_at', 'status']),
                    'assetVariant' => fn ($q) => $q->select(['id', 'name', 'display_name']),
                ])
                ->whereHas('invoice', function ($q) use ($from, $to) {
                    $q->whereNotIn('status', ['draft', 'void'])
                        ->whereBetween('created_at', [$from, $to]);
                })
                ->orderByDesc('id')
                ->get([
                    'id',
                    'invoice_id',
                    'name',
                    'asset_variant_id',
                    'quantity',
                    'subtotal',
                    'tax_amount',
                    'total',
                ]);

            $detailRows = $items->map(fn (InvoiceItem $item) => [
                'id' => (int) $item->id,
                'item_name' => $this->invoiceItemLabel($item),
                'quantity' => (float) $item->quantity,
                'subtotal' => (float) $item->subtotal,
                'tax_total' => (float) $item->tax_amount,
                'total_sales' => (float) $item->total,
                'invoice_id' => (int) $item->invoice_id,
                'invoice_label' => $item->invoice?->display_name ?? ('INV-'.$item->invoice?->sequence),
                'customer_name' => $item->invoice?->customer_name ?: 'Unknown Customer',
                'invoice_date' => $item->invoice?->created_at?->toIso8601String(),
                'invoice_status' => $item->invoice?->status,
            ])->values()->all();
        }

        return Inertia::render('Tenant/Reports/SalesByItem', [
            'recordTitle' => $mode === 'detail' ? 'Sales By Item Detail' : 'Sales By Item Summary',
            'rows' => $rows,
            'detailRows' => $detailRows,
            'summary' => $summary,
            'viewMode' => $mode,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'dateRange' => sprintf('%s - %s', $from->toDateString(), $to->toDateString()),
        ]);
    }

    private function invoiceItemLabel(InvoiceItem $item): string
    {
        $base = trim((string) ($item->name ?? ''));
        $variant = trim((string) ($item->assetVariant?->display_name ?: $item->assetVariant?->name ?: ''));
        if ($base === '' && $variant === '') {
            return 'Unnamed Item';
        }
        if ($variant === '') {
            return $base;
        }
        if ($base === '') {
            return $variant;
        }

        return $base.' - '.$variant;
    }

    private function applySubsidiaryLocationFilters(
        QueryBuilder $query,
        ?int $subsidiaryId,
        ?int $locationId,
        string $tableAlias = 'transactions'
    ): void {
        if ($subsidiaryId !== null) {
            $query->where($tableAlias.'.subsidiary_id', $subsidiaryId);
        }
        if ($locationId !== null) {
            $query->where($tableAlias.'.location_id', $locationId);
        }
    }
}
