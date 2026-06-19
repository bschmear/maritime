<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Asset\Models\Asset;
use App\Domain\Financing\Models\Financing;
use App\Domain\InventoryItem\Models\InventoryItem;
use App\Domain\Invoice\Models\Invoice;
use App\Domain\InvoiceItem\Models\InvoiceItem;
use App\Domain\Location\Models\Location;
use App\Domain\Reports\Support\CollectSalesTaxReportRows;
use App\Domain\Subsidiary\Models\Subsidiary;
use App\Domain\Vendor\Models\Vendor;
use App\Domain\WorkOrder\Models\WorkOrder;
use App\Enums\Financing\Status as FinancingStatus;
use App\Enums\ServiceTicketServiceItem\WarrantyCoverageType;
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

        $dealershipWarranty = WarrantyCoverageType::Dealership->value;
        $manufacturerWarranty = WarrantyCoverageType::Manufacturer->value;

        // Customer-billable revenue only (excludes dealership-internal and manufacturer-billable lines).
        $boatSales = (float) ($this->pnlInvoiceItemsBase($from, $to, $subsidiaryId, $locationId)
            ->where('invoice_items.itemable_type', Asset::class)
            ->where('invoice_items.billable_to', 'customer')
            ->sum('invoice_items.subtotal') ?? 0);

        $partsSales = (float) ($this->pnlInvoiceItemsBase($from, $to, $subsidiaryId, $locationId)
            ->where('invoice_items.itemable_type', InventoryItem::class)
            ->where('invoice_items.billable_to', 'customer')
            ->sum('invoice_items.subtotal') ?? 0);

        $serviceMorphClause = function ($q): void {
            $q->whereNull('invoice_items.itemable_type')
                ->orWhereNotIn('invoice_items.itemable_type', [Asset::class, InventoryItem::class]);
        };

        $serviceRevenueBase = $this->pnlInvoiceItemsBase($from, $to, $subsidiaryId, $locationId)
            ->where('invoice_items.billable_to', 'customer')
            ->where($serviceMorphClause);

        $serviceRevenue = (float) ($serviceRevenueBase->clone()->sum('invoice_items.subtotal') ?? 0);

        $serviceFromWorkOrders = (float) ($serviceRevenueBase->clone()
            ->whereNotNull('invoices.work_order_id')
            ->sum('invoice_items.subtotal') ?? 0);

        $specCogsByTli = DB::table('transaction_line_item_selected_options')
            ->selectRaw('transaction_line_item_id, SUM(COALESCE(cost, 0)) as spec_cogs_sum')
            ->groupBy('transaction_line_item_id');

        $boatCostExpr = trim(<<<'SQL'
            SUM(
                (invoice_items.quantity * (
                    CASE
                        WHEN COALESCE(invoice_items.cost, 0) > 0 THEN invoice_items.cost
                        ELSE COALESCE(asset_units.cost, asset_variants.default_cost, assets.default_cost, 0)
                    END
                ))
                + (
                    CASE
                        WHEN COALESCE(invoice_items.cost, 0) > 0 THEN 0
                        ELSE COALESCE(tli_spec_cogs.spec_cogs_sum, 0)
                    END
                )
            )
            SQL);

        $boatCost = (float) ($this->pnlInvoiceItemsBase($from, $to, $subsidiaryId, $locationId)
            ->leftJoin('assets', function ($join) {
                $join->on('assets.id', '=', 'invoice_items.itemable_id')
                    ->where('invoice_items.itemable_type', '=', Asset::class);
            })
            ->leftJoin('asset_variants', 'asset_variants.id', '=', 'invoice_items.asset_variant_id')
            ->leftJoin('asset_units', 'asset_units.id', '=', 'invoice_items.asset_unit_id')
            ->leftJoinSub($specCogsByTli, 'tli_spec_cogs', function ($join) {
                $join->on('tli_spec_cogs.transaction_line_item_id', '=', 'invoice_items.transaction_line_item_id');
            })
            ->where('invoice_items.itemable_type', Asset::class)
            ->selectRaw($boatCostExpr.' as pnl_sum')
            ->value('pnl_sum') ?? 0);

        $partsCost = (float) ($this->pnlInvoiceItemsBase($from, $to, $subsidiaryId, $locationId)
            ->leftJoin('inventory_items', function ($join) {
                $join->on('inventory_items.id', '=', 'invoice_items.itemable_id')
                    ->where('invoice_items.itemable_type', '=', InventoryItem::class);
            })
            ->where('invoice_items.itemable_type', InventoryItem::class)
            ->selectRaw('SUM(invoice_items.quantity * COALESCE(invoice_items.cost, inventory_items.default_cost, 0)) as pnl_sum')
            ->value('pnl_sum') ?? 0);

        $serviceCostBase = $this->pnlInvoiceItemsBase($from, $to, $subsidiaryId, $locationId)
            ->where($serviceMorphClause);

        $serviceCost = (float) ($serviceCostBase->clone()
            ->selectRaw('SUM(invoice_items.quantity * COALESCE(invoice_items.cost, 0)) as pnl_sum')
            ->value('pnl_sum') ?? 0);

        $serviceCostWorkOrders = (float) ($serviceCostBase->clone()
            ->whereNotNull('invoices.work_order_id')
            ->selectRaw('SUM(invoice_items.quantity * COALESCE(invoice_items.cost, 0)) as pnl_sum')
            ->value('pnl_sum') ?? 0);

        $warrantyDealershipCost = (float) ($this->pnlWarrantyInvoiceItemsBase($from, $to, $subsidiaryId, $locationId)
            ->where(function ($q) use ($dealershipWarranty) {
                $q->where(function ($q2) use ($dealershipWarranty) {
                    $q2->where('invoice_items.is_warranty', 1)
                        ->where('invoice_items.warranty_type', $dealershipWarranty);
                })->orWhere(function ($q2) {
                    $q2->where('invoice_items.is_warranty', 1)
                        ->whereNull('invoice_items.warranty_type')
                        ->where('invoice_items.billable_to', 'internal');
                });
            })
            ->selectRaw('SUM(invoice_items.quantity * COALESCE(invoice_items.cost, 0)) as pnl_sum')
            ->value('pnl_sum') ?? 0);

        $warrantyManufacturerCost = (float) ($this->pnlWarrantyInvoiceItemsBase($from, $to, $subsidiaryId, $locationId)
            ->where(function ($q) use ($manufacturerWarranty) {
                $q->where(function ($q2) use ($manufacturerWarranty) {
                    $q2->where('invoice_items.is_warranty', 1)
                        ->where('invoice_items.warranty_type', $manufacturerWarranty);
                })->orWhere('invoice_items.billable_to', 'manufacturer');
            })
            ->selectRaw('SUM(invoice_items.quantity * COALESCE(invoice_items.cost, 0)) as pnl_sum')
            ->value('pnl_sum') ?? 0);

        $pendingDealershipBase = $this->pnlPendingWarrantyWorkOrderServiceItemsBase($from, $to, $subsidiaryId, $locationId)
            ->where(function ($q) use ($dealershipWarranty) {
                $q->where(function ($q2) use ($dealershipWarranty) {
                    $q2->where('wosi.warranty', true)
                        ->where('wosi.warranty_type', $dealershipWarranty);
                })->orWhere(function ($q2) {
                    $q2->where('wosi.warranty', true)
                        ->whereNull('wosi.warranty_type')
                        ->where('wosi.billable_to', 'internal');
                });
            });
        $pendingManufacturerBase = $this->pnlPendingWarrantyWorkOrderServiceItemsBase($from, $to, $subsidiaryId, $locationId)
            ->where(function ($q) use ($manufacturerWarranty) {
                $q->where(function ($q2) use ($manufacturerWarranty) {
                    $q2->where('wosi.warranty', true)
                        ->where('wosi.warranty_type', $manufacturerWarranty);
                })->orWhere('wosi.billable_to', 'manufacturer');
            });

        $warrantyDealershipPendingCost = (float) ($pendingDealershipBase->clone()
            ->selectRaw(
                'SUM(COALESCE(wosi.total_cost, wosi.quantity * COALESCE(wosi.unit_cost, 0), 0)) as pnl_sum'
            )
            ->value('pnl_sum') ?? 0);
        $warrantyManufacturerPendingCost = (float) ($pendingManufacturerBase->clone()
            ->selectRaw(
                'SUM(COALESCE(wosi.total_cost, wosi.quantity * COALESCE(wosi.unit_cost, 0), 0)) as pnl_sum'
            )
            ->value('pnl_sum') ?? 0);

        $income = [
            'boat_sales' => $boatSales,
            'service_revenue' => $serviceRevenue,
            'service_from_work_orders' => $serviceFromWorkOrders,
            'parts_accessories' => $partsSales,
        ];
        // service_from_work_orders is a subset of service_revenue (do not add twice).
        $totalIncome = $boatSales + $serviceRevenue + $partsSales;

        $cogs = [
            'boat_cost' => $boatCost,
            'service_cost' => $serviceCost,
            'service_cost_work_orders' => $serviceCostWorkOrders,
            'parts_cost' => $partsCost,
        ];
        $totalCogs = array_sum([
            'boat_cost' => $boatCost,
            'service_cost' => $serviceCost,
            'parts_cost' => $partsCost,
        ]);
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

        $view = strtolower(trim((string) $request->query('view', '')));
        $tableView = $view === 'table';

        return Inertia::render('Tenant/Reports/Pnl', [
            'recordTitle' => 'Profit & Loss',
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'subsidiary_id' => $subsidiaryId,
                'location_id' => $locationId,
                'view' => $tableView ? 'table' : 'summary',
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
                'warranty' => [
                    'dealership' => [
                        'cost' => $warrantyDealershipCost,
                        'pending_invoice' => [
                            'cost' => $warrantyDealershipPendingCost,
                        ],
                    ],
                    'manufacturer' => [
                        'cost' => $warrantyManufacturerCost,
                        'pending_invoice' => [
                            'cost' => $warrantyManufacturerPendingCost,
                        ],
                    ],
                ],
            ],
            'itemization' => $tableView
                ? $this->pnlBuildItemization(
                    $from,
                    $to,
                    $subsidiaryId,
                    $locationId,
                    $dealershipWarranty,
                    $manufacturerWarranty
                )
                : null,
        ]);
    }

    public function balanceSheet(Request $request)
    {
        return Inertia::render('Tenant/Reports/BalanceSheet');
    }

    public function cashFlow(Request $request)
    {
        $defaultFrom = now()->subDays(29)->toDateString();
        $defaultTo = now()->toDateString();
        [$from, $to, $dateFrom, $dateTo] = $this->resolveDateRange($request, $defaultFrom, $defaultTo);
        $subsidiaryId = $request->integer('subsidiary_id') ?: null;
        $locationId = $request->integer('location_id') ?: null;

        $paymentRows = $this->paymentsWithInvoicesBase($from, $to, $subsidiaryId, $locationId)
            ->whereIn('payments.status', ['completed', 'partially_refunded'])
            ->whereNull('payments.deleted_at')
            ->whereNull('invoices.deleted_at')
            ->get([
                'payments.net_amount',
                'payments.payment_method_code',
                'payments.paid_at',
                'payments.created_at',
            ]);

        $refundRows = $this->refundsWithInvoicesBase($from, $to, $subsidiaryId, $locationId)
            ->whereNull('payments.deleted_at')
            ->whereNull('invoices.deleted_at')
            ->get([
                'payment_refunds.amount',
                'payment_refunds.created_at',
                'payment_refunds.updated_at',
            ]);

        $cashIn = (float) $paymentRows->sum(fn ($r) => (float) ($r->net_amount ?? 0));
        $cashOutRefunds = (float) $refundRows->sum(fn ($r) => (float) ($r->amount ?? 0));
        $netCash = $cashIn - $cashOutRefunds;

        $byMethod = $paymentRows
            ->groupBy('payment_method_code')
            ->map(fn (Collection $rows, string $code) => [
                'code' => $code,
                'label' => $this->paymentMethodLabel($code),
                'amount' => (float) $rows->sum(fn ($r) => (float) ($r->net_amount ?? 0)),
            ])
            ->values()
            ->sortByDesc('amount')
            ->values()
            ->all();

        $methodPie = [
            'labels' => array_map(fn ($r) => $r['label'], $byMethod),
            'series' => array_map(fn ($r) => $r['amount'], $byMethod),
        ];

        $dailyIn = [];
        foreach ($paymentRows as $r) {
            $d = Carbon::parse($r->paid_at ?? $r->created_at)->toDateString();
            $dailyIn[$d] = ($dailyIn[$d] ?? 0) + (float) ($r->net_amount ?? 0);
        }
        $dailyOut = [];
        foreach ($refundRows as $r) {
            $d = Carbon::parse($r->updated_at ?? $r->created_at)->toDateString();
            $dailyOut[$d] = ($dailyOut[$d] ?? 0) + (float) ($r->amount ?? 0);
        }

        $chartCategories = [];
        $chartIn = [];
        $chartOut = [];
        $cursor = $from->copy()->startOfDay();
        $endDay = $to->copy()->startOfDay();
        while ($cursor->lte($endDay)) {
            $key = $cursor->toDateString();
            $chartCategories[] = $cursor->format('M j');
            $chartIn[] = round($dailyIn[$key] ?? 0.0, 2);
            $chartOut[] = round($dailyOut[$key] ?? 0.0, 2);
            $cursor->addDay();
        }

        $openArRow = $this->openInvoicesReceivableBase($subsidiaryId, $locationId)
            ->whereNull('invoices.deleted_at')
            ->whereNotIn('invoices.status', ['draft', 'void', 'paid'])
            ->where('invoices.amount_due', '>', 0)
            ->selectRaw('COUNT(*) as invoice_count, SUM(invoices.amount_due) as amount_due_sum')
            ->first();

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

        return Inertia::render('Tenant/Reports/CashFlow', [
            'recordTitle' => 'Cash Flow',
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
                'cash_in' => $cashIn,
                'cash_out_refunds' => $cashOutRefunds,
                'net_cash' => $netCash,
                'payment_count' => $paymentRows->count(),
                'refund_count' => $refundRows->count(),
                'by_method' => $byMethod,
                'method_pie' => $methodPie,
                'chart' => [
                    'categories' => $chartCategories,
                    'series' => [
                        ['name' => 'Cash in (payments)', 'data' => $chartIn],
                        ['name' => 'Cash out (refunds)', 'data' => $chartOut],
                    ],
                ],
                'open_ar' => [
                    'amount_due' => (float) ($openArRow?->amount_due_sum ?? 0),
                    'invoice_count' => (int) ($openArRow?->invoice_count ?? 0),
                ],
            ],
        ]);
    }

    public function salesTaxLiability(Request $request)
    {
        return Inertia::render('Tenant/Reports/SalesTaxLiability', $this->buildSalesTaxReportPayload($request, 'liability'));
    }

    public function salesTaxPayable(Request $request)
    {
        return Inertia::render('Tenant/Reports/SalesTaxPayable', $this->buildSalesTaxReportPayload($request, 'payable'));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSalesTaxReportPayload(Request $request, string $reportKind): array
    {
        $defaultFrom = now()->subDays(29)->toDateString();
        $defaultTo = now()->toDateString();
        [$from, $to, $dateFrom, $dateTo] = $this->resolveDateRange($request, $defaultFrom, $defaultTo);
        $subsidiaryId = $request->integer('subsidiary_id') ?: null;
        $locationId = $request->integer('location_id') ?: null;

        $view = strtolower(trim((string) $request->query('view', 'summary')));
        if (! in_array($view, ['summary', 'detail'], true)) {
            $view = 'summary';
        }

        $basis = strtolower(trim((string) $request->query('basis', 'accrual')));
        if (! in_array($basis, ['accrual', 'cash'], true)) {
            $basis = 'accrual';
        }

        $basisConst = $basis === 'cash' ? CollectSalesTaxReportRows::BASIS_CASH : CollectSalesTaxReportRows::BASIS_ACCRUAL;
        $data = CollectSalesTaxReportRows::collect($from, $to, $subsidiaryId, $locationId, $basisConst);
        $groupsLiability = CollectSalesTaxReportRows::groupForLiability($data['rows']);
        $groupsPayable = CollectSalesTaxReportRows::groupForPayable($data['rows']);

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

        $title = $reportKind === 'liability' ? 'Sales Tax Liability' : 'Sales Tax Payable';

        return [
            'recordTitle' => $title,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'subsidiary_id' => $subsidiaryId,
                'location_id' => $locationId,
                'view' => $view,
                'basis' => $basis,
            ],
            'dateRange' => sprintf('%s - %s', $from->toDateString(), $to->toDateString()),
            'options' => [
                'subsidiaries' => $subsidiaries,
                'locations' => $locations,
            ],
            'viewMode' => $view,
            'basis' => $basis,
            'report' => [
                'summary' => $data['summary'],
                'groups' => $reportKind === 'liability' ? $groupsLiability : $groupsPayable,
                'rows' => $data['rows'],
            ],
        ];
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
                SUM(subtotal) as total_subtotal,
                SUM(tax_total) as total_tax,
                SUM(amount_paid) as total_paid,
                SUM(amount_due) as total_due'
            )
            ->groupBy('contact_id')
            ->orderByDesc('total_subtotal')
            ->get();

        $revenueByContact = InvoiceItem::query()
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->whereNotIn('invoices.status', ['draft', 'void'])
            ->whereBetween('invoices.created_at', [$from, $to])
            ->where('invoice_items.billable_to', '=', 'customer')
            ->groupBy('invoices.contact_id')
            ->selectRaw('invoices.contact_id as contact_id, SUM(invoice_items.subtotal) as total_sales')
            ->pluck('total_sales', 'contact_id');

        $rows = $groups->map(function ($group) {
            $contactId = $group->contact_id ? (int) $group->contact_id : null;

            return [
                'contact_id' => $contactId,
                'customer_name' => trim((string) ($group->customer_name ?? '')) ?: 'Unknown Customer',
                'invoice_count' => (int) $group->invoice_count,
                'total_sales' => (float) ($contactId ? ($revenueByContact[$contactId] ?? 0) : 0),
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
                SUM(CASE WHEN invoice_items.billable_to = 'internal' THEN 0 ELSE invoice_items.subtotal END) as total_sales"
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
                    'billable_to',
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
                'total_sales' => (float) (($item->billable_to ?? 'customer') === 'internal' ? 0 : $item->subtotal),
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

    /**
     * @return array{
     *     invoices: list<array<string, mixed>>,
     *     warranty_invoiced_invoices: list<array<string, mixed>>,
     *     warranty_pending_work_orders: list<array<string, mixed>>,
     * }
     */
    private function pnlBuildItemization(
        Carbon $from,
        Carbon $to,
        ?int $subsidiaryId,
        ?int $locationId,
        string $dealershipWarranty,
        string $manufacturerWarranty,
    ): array {
        $specCogsByTli = DB::table('transaction_line_item_selected_options')
            ->selectRaw('transaction_line_item_id, SUM(COALESCE(cost, 0)) as spec_cogs_sum')
            ->groupBy('transaction_line_item_id');

        $boatLineCostExpr = trim(preg_replace('/\s+/', ' ', <<<'SQL'
            (invoice_items.quantity * (
                CASE
                    WHEN COALESCE(invoice_items.cost, 0) > 0 THEN invoice_items.cost
                    ELSE COALESCE(asset_units.cost, asset_variants.default_cost, assets.default_cost, 0)
                END
            ))
            + (
                CASE
                    WHEN COALESCE(invoice_items.cost, 0) > 0 THEN 0
                    ELSE COALESCE(tli_spec_cogs.spec_cogs_sum, 0)
                END
            )
            SQL));

        $revenueById = $this->pnlInvoiceItemsBase($from, $to, $subsidiaryId, $locationId)
            ->whereNull('invoices.deleted_at')
            ->where('invoice_items.billable_to', 'customer')
            ->where(function ($w) {
                $w->where('invoice_items.itemable_type', Asset::class)
                    ->orWhere('invoice_items.itemable_type', InventoryItem::class)
                    ->orWhere(function ($inner) {
                        $inner->whereNull('invoice_items.itemable_type')
                            ->orWhereNotIn('invoice_items.itemable_type', [Asset::class, InventoryItem::class]);
                    });
            })
            ->groupBy('invoices.id')
            ->selectRaw('invoices.id as invoice_id, SUM(invoice_items.subtotal) as amount')
            ->pluck('amount', 'invoice_id');

        $boatCogsById = $this->pnlInvoiceItemsBase($from, $to, $subsidiaryId, $locationId)
            ->whereNull('invoices.deleted_at')
            ->leftJoin('assets', function ($join) {
                $join->on('assets.id', '=', 'invoice_items.itemable_id')
                    ->where('invoice_items.itemable_type', '=', Asset::class);
            })
            ->leftJoin('asset_variants', 'asset_variants.id', '=', 'invoice_items.asset_variant_id')
            ->leftJoin('asset_units', 'asset_units.id', '=', 'invoice_items.asset_unit_id')
            ->leftJoinSub($specCogsByTli, 'tli_spec_cogs', function ($join) {
                $join->on('tli_spec_cogs.transaction_line_item_id', '=', 'invoice_items.transaction_line_item_id');
            })
            ->where('invoice_items.itemable_type', Asset::class)
            ->groupBy('invoices.id')
            ->selectRaw('invoices.id as invoice_id, SUM('.$boatLineCostExpr.') as amount')
            ->pluck('amount', 'invoice_id');

        $partsCogsById = $this->pnlInvoiceItemsBase($from, $to, $subsidiaryId, $locationId)
            ->whereNull('invoices.deleted_at')
            ->leftJoin('inventory_items', function ($join) {
                $join->on('inventory_items.id', '=', 'invoice_items.itemable_id')
                    ->where('invoice_items.itemable_type', '=', InventoryItem::class);
            })
            ->where('invoice_items.itemable_type', InventoryItem::class)
            ->groupBy('invoices.id')
            ->selectRaw('invoices.id as invoice_id, SUM(invoice_items.quantity * COALESCE(invoice_items.cost, inventory_items.default_cost, 0)) as amount')
            ->pluck('amount', 'invoice_id');

        $serviceMorph = function ($q): void {
            $q->where(function ($inner) {
                $inner->whereNull('invoice_items.itemable_type')
                    ->orWhereNotIn('invoice_items.itemable_type', [Asset::class, InventoryItem::class]);
            });
        };

        $serviceCogsById = $this->pnlInvoiceItemsBase($from, $to, $subsidiaryId, $locationId)
            ->whereNull('invoices.deleted_at')
            ->where($serviceMorph)
            ->groupBy('invoices.id')
            ->selectRaw('invoices.id as invoice_id, SUM(invoice_items.quantity * COALESCE(invoice_items.cost, 0)) as amount')
            ->pluck('amount', 'invoice_id');

        $allInvoiceIds = $revenueById->keys()
            ->merge($boatCogsById->keys())
            ->merge($partsCogsById->keys())
            ->merge($serviceCogsById->keys())
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sort()
            ->values();
        if ($allInvoiceIds->count() > 500) {
            $allInvoiceIds = $allInvoiceIds->take(500);
        }

        $invoicesTable = [];
        if ($allInvoiceIds->isNotEmpty()) {
            $invoiceModels = Invoice::query()
                ->whereIn('id', $allInvoiceIds->all())
                ->whereNull('deleted_at')
                ->orderByDesc('created_at')
                ->get(['id', 'sequence', 'created_at', 'status', 'total', 'work_order_id']);

            foreach ($invoiceModels as $inv) {
                $iid = (int) $inv->id;
                $boat = (float) ($boatCogsById[$iid] ?? $boatCogsById[(string) $iid] ?? 0);
                $parts = (float) ($partsCogsById[$iid] ?? $partsCogsById[(string) $iid] ?? 0);
                $serv = (float) ($serviceCogsById[$iid] ?? $serviceCogsById[(string) $iid] ?? 0);
                $rev = (float) ($revenueById[$iid] ?? $revenueById[(string) $iid] ?? 0);
                $cogsTotal = $boat + $parts + $serv;

                $invoicesTable[] = [
                    'id' => $iid,
                    'label' => trim((string) ($inv->display_name ?? '')) !== ''
                        ? (string) $inv->display_name
                        : ('INV-'.(string) ($inv->sequence ?? $iid)),
                    'created_at' => $inv->created_at?->toIso8601String(),
                    'status' => (string) ($inv->status ?? ''),
                    'invoice_total' => round((float) ($inv->total ?? 0), 2),
                    'customer_revenue' => round($rev, 2),
                    'extended_cogs' => round($cogsTotal, 2),
                    'work_order_id' => $inv->work_order_id !== null ? (int) $inv->work_order_id : null,
                ];
            }
        }

        $warrantyBase = $this->pnlWarrantyInvoiceItemsBase($from, $to, $subsidiaryId, $locationId)
            ->whereNull('invoices.deleted_at');

        $dealershipWarrantyById = $warrantyBase->clone()
            ->where(function ($q) use ($dealershipWarranty) {
                $q->where(function ($q2) use ($dealershipWarranty) {
                    $q2->where('invoice_items.is_warranty', true)
                        ->where('invoice_items.warranty_type', $dealershipWarranty);
                })->orWhere(function ($q2) {
                    $q2->where('invoice_items.is_warranty', true)
                        ->whereNull('invoice_items.warranty_type')
                        ->where('invoice_items.billable_to', 'internal');
                });
            })
            ->groupBy('invoices.id')
            ->selectRaw('invoices.id as invoice_id, SUM(invoice_items.quantity * COALESCE(invoice_items.cost, 0)) as amount')
            ->pluck('amount', 'invoice_id');

        $manufacturerWarrantyById = $warrantyBase->clone()
            ->where(function ($q) use ($manufacturerWarranty) {
                $q->where(function ($q2) use ($manufacturerWarranty) {
                    $q2->where('invoice_items.is_warranty', true)
                        ->where('invoice_items.warranty_type', $manufacturerWarranty);
                })->orWhere('invoice_items.billable_to', 'manufacturer');
            })
            ->groupBy('invoices.id')
            ->selectRaw('invoices.id as invoice_id, SUM(invoice_items.quantity * COALESCE(invoice_items.cost, 0)) as amount')
            ->pluck('amount', 'invoice_id');

        $warrantyInvoiceIds = $dealershipWarrantyById->keys()
            ->merge($manufacturerWarrantyById->keys())
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sort()
            ->values();
        if ($warrantyInvoiceIds->count() > 500) {
            $warrantyInvoiceIds = $warrantyInvoiceIds->take(500);
        }

        $warrantyInvoicedTable = [];
        if ($warrantyInvoiceIds->isNotEmpty()) {
            $warrantyInvoiceModels = Invoice::query()
                ->whereIn('id', $warrantyInvoiceIds->all())
                ->whereNull('deleted_at')
                ->orderByDesc('created_at')
                ->get(['id', 'sequence', 'created_at', 'status', 'total', 'work_order_id']);

            foreach ($warrantyInvoiceModels as $inv) {
                $iid = (int) $inv->id;
                $dCost = (float) ($dealershipWarrantyById[$iid] ?? $dealershipWarrantyById[(string) $iid] ?? 0);
                $mCost = (float) ($manufacturerWarrantyById[$iid] ?? $manufacturerWarrantyById[(string) $iid] ?? 0);
                $warrantyInvoicedTable[] = [
                    'id' => $iid,
                    'label' => trim((string) ($inv->display_name ?? '')) !== ''
                        ? (string) $inv->display_name
                        : ('INV-'.(string) ($inv->sequence ?? $iid)),
                    'created_at' => $inv->created_at?->toIso8601String(),
                    'status' => (string) ($inv->status ?? ''),
                    'invoice_total' => round((float) ($inv->total ?? 0), 2),
                    'dealership_warranty_cost' => round($dCost, 2),
                    'manufacturer_warranty_cost' => round($mCost, 2),
                    'work_order_id' => $inv->work_order_id !== null ? (int) $inv->work_order_id : null,
                ];
            }
        }

        $pendingBase = $this->pnlPendingWarrantyWorkOrderServiceItemsBase($from, $to, $subsidiaryId, $locationId);

        $dealershipPendingByWo = $pendingBase->clone()
            ->where(function ($q) use ($dealershipWarranty) {
                $q->where(function ($q2) use ($dealershipWarranty) {
                    $q2->where('wosi.warranty', true)
                        ->where('wosi.warranty_type', $dealershipWarranty);
                })->orWhere(function ($q2) {
                    $q2->where('wosi.warranty', true)
                        ->whereNull('wosi.warranty_type')
                        ->where('wosi.billable_to', 'internal');
                });
            })
            ->groupBy('wo.id')
            ->selectRaw('wo.id as work_order_id, SUM(COALESCE(wosi.total_cost, wosi.quantity * COALESCE(wosi.unit_cost, 0), 0)) as amount')
            ->pluck('amount', 'work_order_id');

        $manufacturerPendingByWo = $pendingBase->clone()
            ->where(function ($q) use ($manufacturerWarranty) {
                $q->where(function ($q2) use ($manufacturerWarranty) {
                    $q2->where('wosi.warranty', true)
                        ->where('wosi.warranty_type', $manufacturerWarranty);
                })->orWhere('wosi.billable_to', 'manufacturer');
            })
            ->groupBy('wo.id')
            ->selectRaw('wo.id as work_order_id, SUM(COALESCE(wosi.total_cost, wosi.quantity * COALESCE(wosi.unit_cost, 0), 0)) as amount')
            ->pluck('amount', 'work_order_id');

        $woIds = $dealershipPendingByWo->keys()
            ->merge($manufacturerPendingByWo->keys())
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sort()
            ->values();
        if ($woIds->count() > 500) {
            $woIds = $woIds->take(500);
        }

        $warrantyPendingTable = [];
        if ($woIds->isNotEmpty()) {
            $workOrders = WorkOrder::query()
                ->whereIn('id', $woIds->all())
                ->whereNull('deleted_at')
                ->orderByDesc('completed_at')
                ->get(['id', 'work_order_number', 'completed_at', 'status']);

            foreach ($workOrders as $wo) {
                $wid = (int) $wo->id;
                $d = (float) ($dealershipPendingByWo[$wid] ?? $dealershipPendingByWo[(string) $wid] ?? 0);
                $m = (float) ($manufacturerPendingByWo[$wid] ?? $manufacturerPendingByWo[(string) $wid] ?? 0);
                $warrantyPendingTable[] = [
                    'id' => $wid,
                    'label' => trim((string) ($wo->display_name ?? '')),
                    'completed_at' => $wo->completed_at?->toIso8601String(),
                    'status' => (int) ($wo->status ?? 0),
                    'dealership_pending_cost' => round($d, 2),
                    'manufacturer_pending_cost' => round($m, 2),
                ];
            }
        }

        return [
            'invoices' => $invoicesTable,
            'warranty_invoiced_invoices' => $warrantyInvoicedTable,
            'warranty_pending_work_orders' => $warrantyPendingTable,
        ];
    }

    /**
     * Invoice line query scoped by date and status for P&L. Joins transactions when present
     * (deal invoices) so subsidiary/location filters apply; work-order-only invoices use columns on invoices.
     */
    private function pnlInvoiceItemsBase(Carbon $from, Carbon $to, ?int $subsidiaryId, ?int $locationId): QueryBuilder
    {
        $query = DB::table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->leftJoin('transactions', 'transactions.id', '=', 'invoices.transaction_id')
            ->whereNotIn('invoices.status', ['draft', 'void'])
            ->whereBetween('invoices.created_at', [$from, $to]);

        $this->applyPnlSubsidiaryLocationFilters($query, $subsidiaryId, $locationId);

        return $query;
    }

    /**
     * Warranty lines on the P&L are attributed to the period when work was completed
     * (work_orders.completed_at when the invoice is tied to a work order), falling back
     * to the invoice created_at when there is no work order or no completion timestamp yet.
     */
    private function pnlWarrantyInvoiceItemsBase(Carbon $from, Carbon $to, ?int $subsidiaryId, ?int $locationId): QueryBuilder
    {
        $query = DB::table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->leftJoin('transactions', 'transactions.id', '=', 'invoices.transaction_id')
            ->leftJoin('work_orders', 'work_orders.id', '=', 'invoices.work_order_id')
            ->whereNotIn('invoices.status', ['draft', 'void'])
            ->whereBetween(
                DB::raw('COALESCE(work_orders.completed_at, invoices.created_at)'),
                [$from, $to]
            );

        $this->applyPnlSubsidiaryLocationFilters($query, $subsidiaryId, $locationId);

        return $query;
    }

    /**
     * Warranty-style work order service lines for completed work orders in the period that do not yet
     * have a non-draft, non-void invoice linked to the work order. Used alongside {@see pnlWarrantyInvoiceItemsBase}.
     */
    private function pnlPendingWarrantyWorkOrderServiceItemsBase(
        Carbon $from,
        Carbon $to,
        ?int $subsidiaryId,
        ?int $locationId
    ): QueryBuilder {
        $query = DB::table('work_order_service_items as wosi')
            ->join('work_orders as wo', 'wo.id', '=', 'wosi.work_order_id')
            ->whereNull('wo.deleted_at')
            ->where('wo.draft', false)
            ->whereNotNull('wo.completed_at')
            ->whereBetween('wo.completed_at', [$from, $to])
            ->where('wosi.billable', true)
            ->where('wosi.inactive', false)
            ->whereNotExists(function ($sub): void {
                $sub->select(DB::raw('1'))
                    ->from('invoices as inv')
                    ->whereColumn('inv.work_order_id', 'wo.id')
                    ->whereNotIn('inv.status', ['draft', 'void'])
                    ->whereNull('inv.deleted_at');
            });

        if ($subsidiaryId !== null) {
            $query->where('wo.subsidiary_id', $subsidiaryId);
        }
        if ($locationId !== null) {
            $query->where('wo.location_id', $locationId);
        }

        return $query;
    }

    private function applyPnlSubsidiaryLocationFilters(QueryBuilder $query, ?int $subsidiaryId, ?int $locationId): void
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

    // ─── Financing Report ─────────────────────────────────────────────────────

    public function financingReport(Request $request)
    {
        $statusFilter   = $request->input('status');
        $vendorId       = $request->integer('vendor_id') ?: null;
        $linkedFilter   = $request->input('linked');   // 'yes' | 'no' | null
        $dateFrom       = $request->input('date_from');
        $dateTo         = $request->input('date_to');
        $agingMin       = $request->integer('aging_min') ?: null;
        $agingMax       = $request->integer('aging_max') ?: null;
        $search         = trim((string) $request->input('search', ''));
        $sortBy         = $request->input('sort_by', 'aging_days');
        $sortDir        = $request->input('sort_dir', 'desc') === 'asc' ? 'asc' : 'desc';

        $query = Financing::query()
            ->with([
                'vendor:id,display_name',
                'assetUnit:id,serial_number,hin',
            ])
            ->when($statusFilter, fn ($q) => $q->where('status', $statusFilter))
            ->when($vendorId,     fn ($q) => $q->where('vendor_id', $vendorId))
            ->when($linkedFilter === 'yes', fn ($q) => $q->whereNotNull('asset_unit_id'))
            ->when($linkedFilter === 'no',  fn ($q) => $q->whereNull('asset_unit_id'))
            ->when($dateFrom, fn ($q) => $q->whereDate('financed_at', '>=', $dateFrom))
            ->when($dateTo,   fn ($q) => $q->whereDate('financed_at', '<=', $dateTo))
            ->when($agingMin !== null, fn ($q) => $q->where('aging_days', '>=', $agingMin))
            ->when($agingMax !== null, fn ($q) => $q->where('aging_days', '<=', $agingMax))
            ->when($search !== '', function ($q) use ($search) {
                $like = '%'.strtolower($search).'%';
                $q->where(function ($inner) use ($like) {
                    $inner->whereRaw('lower(serial_vin) like ?', [$like])
                        ->orWhereRaw('lower(model_number) like ?', [$like])
                        ->orWhereRaw('lower(dealer_name) like ?', [$like])
                        ->orWhereRaw('lower(supplier_name) like ?', [$like])
                        ->orWhereRaw('lower(lender_invoice_number) like ?', [$like]);
                });
            });

        $allowedSorts = ['aging_days', 'financed_at', 'current_balance', 'principal_amount', 'model_year', 'interest_start_date'];
        if (in_array($sortBy, $allowedSorts, true)) {
            $query->orderBy($sortBy, $sortDir);
        }

        $rows = $query->get()->map(function (Financing $f): array {
            $principal = (float) ($f->principal_amount ?? 0);
            $balance   = (float) ($f->current_balance ?? 0);
            $paidOff   = max(0.0, $principal - $balance);
            $paidPct   = $principal > 0 ? round(($paidOff / $principal) * 100, 1) : null;

            return [
                'id'                     => $f->id,
                'display_name'           => $f->display_name,
                'status'                 => $f->status instanceof FinancingStatus ? $f->status->value : $f->status,
                'lender_status'          => $f->lender_status,
                'dealer_name'            => $f->dealer_name,
                'supplier_name'          => $f->supplier_name,
                'vendor_name'            => $f->vendor?->display_name,
                'serial_vin'             => $f->serial_vin,
                'model_year'             => $f->model_year,
                'model_number'           => $f->model_number,
                'lender_invoice_number'  => $f->lender_invoice_number,
                'principal_amount'       => $principal,
                'current_balance'        => $balance,
                'paid_off_amount'        => round($paidOff, 2),
                'paid_off_pct'           => $paidPct,
                'aging_days'             => $f->aging_days,
                'financed_at'            => $f->financed_at?->toDateString(),
                'interest_start_date'    => $f->interest_start_date?->toDateString(),
                'next_payment_date'      => $f->next_payment_date?->toDateString(),
                'curtailment_current_due' => (float) ($f->curtailment_current_due ?? 0),
                'past_due_curtailment'   => (float) ($f->past_due_curtailment ?? 0),
                'asset_unit_id'          => $f->asset_unit_id,
                'asset_unit_serial'      => $f->assetUnit?->serial_number ?? $f->assetUnit?->hin,
                'last_imported_at'       => $f->last_imported_at?->toDateString(),
            ];
        })->values()->all();

        // Summary totals
        $totalPrincipal = round(array_sum(array_column($rows, 'principal_amount')), 2);
        $totalBalance   = round(array_sum(array_column($rows, 'current_balance')), 2);
        $totalPaidOff   = round(array_sum(array_column($rows, 'paid_off_amount')), 2);
        $overallPct     = $totalPrincipal > 0 ? round(($totalPaidOff / $totalPrincipal) * 100, 1) : null;
        $unlinkedCount  = count(array_filter($rows, fn ($r) => !$r['asset_unit_id']));

        // Lender options for filter dropdown
        $lenders = Vendor::query()
            ->whereIn('id', Financing::query()->select('vendor_id')->whereNotNull('vendor_id')->distinct())
            ->orderBy('display_name')
            ->get(['id', 'display_name'])
            ->map(fn ($v) => ['id' => $v->id, 'name' => $v->display_name])
            ->values()
            ->all();

        return Inertia::render('Tenant/Reports/FinancingReport', [
            'recordTitle' => 'Financing Report',
            'rows'        => $rows,
            'summary'     => [
                'total_rows'       => count($rows),
                'total_principal'  => $totalPrincipal,
                'total_balance'    => $totalBalance,
                'total_paid_off'   => $totalPaidOff,
                'overall_paid_pct' => $overallPct,
                'unlinked_count'   => $unlinkedCount,
            ],
            'filters' => [
                'status'     => $statusFilter,
                'vendor_id'  => $vendorId,
                'linked'     => $linkedFilter,
                'date_from'  => $dateFrom,
                'date_to'    => $dateTo,
                'aging_min'  => $agingMin,
                'aging_max'  => $agingMax,
                'search'     => $search,
                'sort_by'    => $sortBy,
                'sort_dir'   => $sortDir,
            ],
            'lenders'       => $lenders,
            'statusOptions' => array_map(fn ($s) => ['value' => $s->value, 'label' => ucfirst($s->value)], FinancingStatus::cases()),
        ]);
    }

    private function paymentsWithInvoicesBase(Carbon $from, Carbon $to, ?int $subsidiaryId, ?int $locationId): QueryBuilder
    {
        $query = DB::table('payments')
            ->join('invoices', 'invoices.id', '=', 'payments.invoice_id')
            ->leftJoin('transactions', 'transactions.id', '=', 'invoices.transaction_id')
            ->whereNull('payments.deleted_at')
            ->whereNotIn('invoices.status', ['draft', 'void'])
            ->whereBetween(DB::raw('COALESCE(payments.paid_at, payments.created_at)'), [$from, $to]);
        $this->applyPnlSubsidiaryLocationFilters($query, $subsidiaryId, $locationId);

        return $query;
    }

    private function refundsWithInvoicesBase(Carbon $from, Carbon $to, ?int $subsidiaryId, ?int $locationId): QueryBuilder
    {
        $query = DB::table('payment_refunds')
            ->join('payments', 'payments.id', '=', 'payment_refunds.payment_id')
            ->join('invoices', 'invoices.id', '=', 'payments.invoice_id')
            ->leftJoin('transactions', 'transactions.id', '=', 'invoices.transaction_id')
            ->whereNull('payments.deleted_at')
            ->whereNotIn('invoices.status', ['draft', 'void'])
            ->where('payment_refunds.status', 'completed')
            ->whereBetween(DB::raw('COALESCE(payment_refunds.updated_at, payment_refunds.created_at)'), [$from, $to]);
        $this->applyPnlSubsidiaryLocationFilters($query, $subsidiaryId, $locationId);

        return $query;
    }

    /**
     * Open receivables snapshot (not a period cash flow). Uses subsidiary/location scope only.
     */
    private function openInvoicesReceivableBase(?int $subsidiaryId, ?int $locationId): QueryBuilder
    {
        $query = DB::table('invoices')
            ->leftJoin('transactions', 'transactions.id', '=', 'invoices.transaction_id');
        $this->applyPnlSubsidiaryLocationFilters($query, $subsidiaryId, $locationId);

        return $query;
    }

    private function paymentMethodLabel(string $code): string
    {
        $code = trim($code);

        return match ($code) {
            '' => 'Other',
            'credit_card' => 'Credit / debit card',
            'ach' => 'ACH / bank transfer',
            'check' => 'Check',
            'cash' => 'Cash',
            'wire' => 'Wire transfer',
            'financing' => 'Financing',
            default => ucfirst(str_replace('_', ' ', $code)),
        };
    }
}
