<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import ReportSwitcher from '@/Components/Tenant/Reports/ReportSwitcher.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    recordTitle: {
        type: String,
        default: 'Profit & Loss',
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    dateRange: {
        type: String,
        default: '',
    },
    options: {
        type: Object,
        default: () => ({ subsidiaries: [], locations: [] }),
    },
    report: {
        type: Object,
        default: () => ({}),
    },
    itemization: {
        type: Object,
        default: null,
    },
});

const breadcrumbItems = computed(() => {
    return [
        { label: 'Home', href: route('dashboard') },
        { label: 'Reports' },
        { label: props.recordTitle },
    ];
});

const dateFrom = ref(props.filters?.date_from ?? '');
const dateTo = ref(props.filters?.date_to ?? '');
const subsidiaryId = ref(props.filters?.subsidiary_id ?? null);
const locationId = ref(props.filters?.location_id ?? null);

const viewMode = ref(props.filters?.view === 'table' ? 'table' : 'cards');

watch(
    () => props.filters?.view,
    (v) => {
        viewMode.value = v === 'table' ? 'table' : 'cards';
    }
);

watch(
    () => [props.filters?.date_from, props.filters?.date_to, props.filters?.subsidiary_id, props.filters?.location_id],
    () => {
        dateFrom.value = props.filters?.date_from ?? '';
        dateTo.value = props.filters?.date_to ?? '';
        subsidiaryId.value = props.filters?.subsidiary_id ?? null;
        locationId.value = props.filters?.location_id ?? null;
    }
);

const report = computed(() => props.report ?? {});

const itemizationRows = computed(() => ({
    invoices: props.itemization?.invoices ?? [],
    warranty_invoiced_invoices: props.itemization?.warranty_invoiced_invoices ?? [],
    warranty_pending_work_orders: props.itemization?.warranty_pending_work_orders ?? [],
}));

function formatCurrency(value) {
    const n = Number(value ?? 0);
    if (Number.isNaN(n)) return '$0.00';
    return n.toLocaleString('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 2 });
}

function formatDate(value) {
    if (!value) return '—';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return '—';
    return d.toLocaleDateString('en-US', { dateStyle: 'medium' });
}

function setView(mode) {
    router.get(
        route('reports.pnl'),
        {
            date_from: dateFrom.value || undefined,
            date_to: dateTo.value || undefined,
            subsidiary_id: subsidiaryId.value || undefined,
            location_id: locationId.value || undefined,
            view: mode === 'table' ? 'table' : undefined,
        },
        { preserveScroll: true }
    );
}

function applyFilters() {
    router.get(
        route('reports.pnl'),
        {
            date_from: dateFrom.value || undefined,
            date_to: dateTo.value || undefined,
            subsidiary_id: subsidiaryId.value || undefined,
            location_id: locationId.value || undefined,
            view: viewMode.value === 'table' ? 'table' : undefined,
        },
        { preserveScroll: true }
    );
}
</script>

<template>
    <Head :title="props.recordTitle" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full flex items-center justify-between">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="flex items-center space-x-4">
                    <ReportSwitcher current-route-name="reports.pnl" />
                </div>
            </div>
        </template>

        <div class="mx-auto w-full space-y-6 p-4 pb-16">
            <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex flex-col gap-4 lg:flex-row lg:flex-wrap lg:items-end lg:justify-between">
                    <div class="flex flex-wrap items-end gap-4">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-500 dark:text-gray-400">From</label>
                            <input v-model="dateFrom" type="date" class="input-style text-md">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-500 dark:text-gray-400">To</label>
                            <input v-model="dateTo" type="date" class="input-style text-md">
                        </div>
                        <div class="min-w-[14rem]">
                            <label class="mb-1 block text-sm font-medium text-gray-500 dark:text-gray-400">Subsidiary</label>
                            <select v-model="subsidiaryId" class="input-style w-full text-md">
                                <option :value="null">All subsidiaries</option>
                                <option v-for="opt in (props.options?.subsidiaries ?? [])" :key="`sub-${opt.id}`" :value="opt.id">
                                    {{ opt.label }}
                                </option>
                            </select>
                        </div>
                        <div class="min-w-[14rem]">
                            <label class="mb-1 block text-sm font-medium text-gray-500 dark:text-gray-400">Location</label>
                            <select v-model="locationId" class="input-style w-full text-md">
                                <option :value="null">All locations</option>
                                <option v-for="opt in (props.options?.locations ?? [])" :key="`loc-${opt.id}`" :value="opt.id">
                                    {{ opt.label }}
                                </option>
                            </select>
                        </div>
                        <button
                            type="button"
                            class="rounded-lg bg-primary-600 px-4 py-2 text-md font-medium text-white hover:bg-primary-700"
                            @click="applyFilters"
                        >
                            Apply
                        </button>
                    </div>
                    <div class="flex shrink-0 items-center gap-1 rounded-lg border border-gray-200 p-0.5 dark:border-gray-600">
                        <button
                            type="button"
                            class="rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
                            :class="viewMode === 'cards'
                                ? 'bg-primary-600 text-white'
                                : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700'"
                            @click="setView('cards')"
                        >
                            Cards
                        </button>
                        <button
                            type="button"
                            class="rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
                            :class="viewMode === 'table'
                                ? 'bg-primary-600 text-white'
                                : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700'"
                            @click="setView('table')"
                        >
                            Table
                        </button>
                    </div>
                </div>
                <div class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                    Date range: <span class="font-semibold text-gray-700 dark:text-gray-300">{{ props.dateRange }}</span>
                </div>
            </div>

            <template v-if="viewMode === 'cards'">
                <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Income</h3>
                    <p class="mb-3 text-sm text-gray-500 dark:text-gray-400">
                        Customer-billable subtotals only. Warranty lines billed internal or to the manufacturer are shown under Warranty below.
                    </p>
                    <dl class="space-y-2">
                        <div class="flex items-center justify-between text-md text-gray-700 dark:text-gray-300">
                            <dt>Boat Sales</dt>
                            <dd class="tabular-nums">{{ formatCurrency(report.income?.boat_sales) }}</dd>
                        </div>
                        <div class="flex items-center justify-between text-md text-gray-700 dark:text-gray-300">
                            <dt>Service Revenue</dt>
                            <dd class="tabular-nums">{{ formatCurrency(report.income?.service_revenue) }}</dd>
                        </div>
                        <div class="flex items-center justify-between pl-4 text-sm text-gray-600 dark:text-gray-400">
                            <dt>— from service tickets (work orders)</dt>
                            <dd class="tabular-nums">{{ formatCurrency(report.income?.service_from_work_orders) }}</dd>
                        </div>
                        <div class="flex items-center justify-between text-md text-gray-700 dark:text-gray-300">
                            <dt>Parts & Accessories</dt>
                            <dd class="tabular-nums">{{ formatCurrency(report.income?.parts_accessories) }}</dd>
                        </div>
                    </dl>
                    <div class="mt-4 border-t border-gray-200 pt-3 dark:border-gray-700">
                        <div class="flex items-center justify-between text-lg font-semibold text-gray-900 dark:text-white">
                            <span>Total Income</span>
                            <span class="tabular-nums">{{ formatCurrency(report.total_income) }}</span>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Warranty (cost)</h3>
                    <p class="mb-3 text-sm text-gray-500 dark:text-gray-400">
                        Warranty customer price is treated as zero; this section is <strong class="font-medium text-gray-700 dark:text-gray-300">extended cost only</strong> (quantity × line cost). <strong class="font-medium text-gray-700 dark:text-gray-300">Invoiced</strong> uses finalized invoice lines (not draft or void) flagged as warranty or billed to the manufacturer. When the invoice is linked to a work order, lines fall in the date range using the work order’s date completed; otherwise the invoice’s created date is used.
                    </p>
                    <p class="mb-3 text-sm text-gray-500 dark:text-gray-400">
                        <strong class="font-medium text-gray-700 dark:text-gray-300">Pending invoice</strong> is the same warranty classification on completed work orders in the range that do not yet have a finalized invoice on that work order; cost uses each service line’s cost on the work order.
                    </p>
                    <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">Invoiced</h4>
                    <dl class="space-y-2">
                        <div class="flex items-center justify-between text-md text-gray-700 dark:text-gray-300">
                            <dt>Dealership warranty — cost</dt>
                            <dd class="tabular-nums">{{ formatCurrency(report.warranty?.dealership?.cost) }}</dd>
                        </div>
                        <div class="flex items-center justify-between text-md text-gray-700 dark:text-gray-300">
                            <dt>Manufacturer warranty — cost</dt>
                            <dd class="tabular-nums">{{ formatCurrency(report.warranty?.manufacturer?.cost) }}</dd>
                        </div>
                    </dl>
                    <h4 class="mb-2 mt-5 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">Pending invoice</h4>
                    <dl class="space-y-2">
                        <div class="flex items-center justify-between text-md text-gray-700 dark:text-gray-300">
                            <dt>Dealership warranty — cost</dt>
                            <dd class="tabular-nums">{{ formatCurrency(report.warranty?.dealership?.pending_invoice?.cost) }}</dd>
                        </div>
                        <div class="flex items-center justify-between text-md text-gray-700 dark:text-gray-300">
                            <dt>Manufacturer warranty — cost</dt>
                            <dd class="tabular-nums">{{ formatCurrency(report.warranty?.manufacturer?.pending_invoice?.cost) }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Cost of Goods Sold</h3>
                    <p class="mb-3 text-sm text-gray-500 dark:text-gray-400">
                        All non-draft invoice lines in each category, including internal and warranty work (labor/parts cost).
                    </p>
                    <dl class="space-y-2">
                        <div class="flex items-center justify-between text-md text-gray-700 dark:text-gray-300">
                            <dt>Boat Cost</dt>
                            <dd class="tabular-nums">{{ formatCurrency(report.cogs?.boat_cost) }}</dd>
                        </div>
                        <div class="flex items-center justify-between text-md text-gray-700 dark:text-gray-300">
                            <dt>Service Cost</dt>
                            <dd class="tabular-nums">{{ formatCurrency(report.cogs?.service_cost) }}</dd>
                        </div>
                        <div class="flex items-center justify-between pl-4 text-sm text-gray-600 dark:text-gray-400">
                            <dt>— from service tickets (work orders)</dt>
                            <dd class="tabular-nums">{{ formatCurrency(report.cogs?.service_cost_work_orders) }}</dd>
                        </div>
                        <div class="flex items-center justify-between text-md text-gray-700 dark:text-gray-300">
                            <dt>Parts Cost</dt>
                            <dd class="tabular-nums">{{ formatCurrency(report.cogs?.parts_cost) }}</dd>
                        </div>
                    </dl>
                    <div class="mt-4 border-t border-gray-200 pt-3 dark:border-gray-700">
                        <div class="flex items-center justify-between text-lg font-semibold text-gray-900 dark:text-white">
                            <span>Total COGS</span>
                            <span class="tabular-nums">{{ formatCurrency(report.total_cogs) }}</span>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-green-200 bg-green-50/40 p-5 shadow-sm dark:border-green-900/50 dark:bg-green-950/20">
                    <div class="flex items-center justify-between text-xl font-bold text-gray-900 dark:text-white">
                        <span>Gross Profit</span>
                        <span class="tabular-nums">{{ formatCurrency(report.gross_profit) }}</span>
                    </div>
                </div>

                <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="flex items-center justify-between text-lg font-semibold text-gray-900 dark:text-white">
                        <span>Total Expenses</span>
                        <span class="tabular-nums">{{ formatCurrency(report.total_expenses) }}</span>
                    </div>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        Expense-account integration is pending; this section currently shows a placeholder total.
                    </p>
                </div>

                <div class="rounded-xl border border-primary-200 bg-primary-50/40 p-5 shadow-sm dark:border-primary-900/50 dark:bg-primary-950/20">
                    <div class="flex items-center justify-between text-xl font-bold text-gray-900 dark:text-white">
                        <span>Net Profit</span>
                        <span class="tabular-nums">{{ formatCurrency(report.net_profit) }}</span>
                    </div>
                </div>
            </template>

            <div
                v-else
                class="space-y-8"
            >
                <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Income &amp; COGS — invoices
                    </h3>
                    <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                        Invoices with at least one line in this P&amp;L period (non-draft, non-void). Customer revenue is customer-billable boat, parts, and service subtotals. Extended COGS matches the P&amp;L boat, parts, and service cost logic. Invoice total is the full invoice. Up to 500 rows.
                    </p>
                    <div class="-mx-5 overflow-x-auto sm:mx-0">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900/40">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Invoice</th>
                                    <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Created</th>
                                    <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</th>
                                    <th class="px-4 py-2 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Invoice total</th>
                                    <th class="px-4 py-2 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Customer revenue</th>
                                    <th class="px-4 py-2 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Extended COGS</th>
                                    <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Links</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                <template v-if="!itemizationRows.invoices.length">
                                    <tr>
                                        <td colspan="7" class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">No matching invoices in this range.</td>
                                    </tr>
                                </template>
                                <template v-else>
                                    <tr v-for="row in itemizationRows.invoices" :key="`inv-${row.id}`">
                                        <td class="px-4 py-2 text-sm">
                                            <Link :href="route('invoices.show', row.id)" class="font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400">
                                                {{ row.label }}
                                            </Link>
                                        </td>
                                        <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300">{{ formatDate(row.created_at) }}</td>
                                        <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300">{{ row.status }}</td>
                                        <td class="px-4 py-2 text-right text-sm tabular-nums text-gray-900 dark:text-white">{{ formatCurrency(row.invoice_total) }}</td>
                                        <td class="px-4 py-2 text-right text-sm tabular-nums text-gray-900 dark:text-white">{{ formatCurrency(row.customer_revenue) }}</td>
                                        <td class="px-4 py-2 text-right text-sm tabular-nums text-gray-900 dark:text-white">{{ formatCurrency(row.extended_cogs) }}</td>
                                        <td class="px-4 py-2 text-sm">
                                            <Link v-if="row.work_order_id" :href="route('workorders.show', row.work_order_id)" class="text-primary-600 hover:text-primary-700 dark:text-primary-400">
                                                Work order
                                            </Link>
                                            <span v-else class="text-gray-400 dark:text-gray-500">—</span>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Warranty (invoiced) — invoices
                    </h3>
                    <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                        Invoices with warranty cost in the P&amp;L warranty window (work order completed date when linked, otherwise invoice created date). Up to 500 rows.
                    </p>
                    <div class="-mx-5 overflow-x-auto sm:mx-0">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900/40">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Invoice</th>
                                    <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Created</th>
                                    <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</th>
                                    <th class="px-4 py-2 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Invoice total</th>
                                    <th class="px-4 py-2 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Dealership warranty cost</th>
                                    <th class="px-4 py-2 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Manufacturer warranty cost</th>
                                    <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Work order</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                <template v-if="!itemizationRows.warranty_invoiced_invoices.length">
                                    <tr>
                                        <td colspan="7" class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">No matching invoices.</td>
                                    </tr>
                                </template>
                                <template v-else>
                                    <tr v-for="row in itemizationRows.warranty_invoiced_invoices" :key="`w-inv-${row.id}`">
                                        <td class="px-4 py-2 text-sm">
                                            <Link :href="route('invoices.show', row.id)" class="font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400">
                                                {{ row.label }}
                                            </Link>
                                        </td>
                                        <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300">{{ formatDate(row.created_at) }}</td>
                                        <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300">{{ row.status }}</td>
                                        <td class="px-4 py-2 text-right text-sm tabular-nums text-gray-900 dark:text-white">{{ formatCurrency(row.invoice_total) }}</td>
                                        <td class="px-4 py-2 text-right text-sm tabular-nums text-gray-900 dark:text-white">{{ formatCurrency(row.dealership_warranty_cost) }}</td>
                                        <td class="px-4 py-2 text-right text-sm tabular-nums text-gray-900 dark:text-white">{{ formatCurrency(row.manufacturer_warranty_cost) }}</td>
                                        <td class="px-4 py-2 text-sm">
                                            <Link v-if="row.work_order_id" :href="route('workorders.show', row.work_order_id)" class="text-primary-600 hover:text-primary-700 dark:text-primary-400">
                                                Open
                                            </Link>
                                            <span v-else class="text-gray-400 dark:text-gray-500">—</span>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Warranty (pending invoice) — work orders
                    </h3>
                    <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                        Completed work orders in range with warranty cost and no finalized invoice on that work order. Up to 500 rows.
                    </p>
                    <div class="-mx-5 overflow-x-auto sm:mx-0">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900/40">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Work order</th>
                                    <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Completed</th>
                                    <th class="px-4 py-2 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Dealership pending cost</th>
                                    <th class="px-4 py-2 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Manufacturer pending cost</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                <template v-if="!itemizationRows.warranty_pending_work_orders.length">
                                    <tr>
                                        <td colspan="4" class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">No matching work orders.</td>
                                    </tr>
                                </template>
                                <template v-else>
                                    <tr v-for="row in itemizationRows.warranty_pending_work_orders" :key="`wo-${row.id}`">
                                        <td class="px-4 py-2 text-sm">
                                            <Link :href="route('workorders.show', row.id)" class="font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400">
                                                {{ row.label || '—' }}
                                            </Link>
                                        </td>
                                        <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300">{{ formatDate(row.completed_at) }}</td>
                                        <td class="px-4 py-2 text-right text-sm tabular-nums text-gray-900 dark:text-white">{{ formatCurrency(row.dealership_pending_cost) }}</td>
                                        <td class="px-4 py-2 text-right text-sm tabular-nums text-gray-900 dark:text-white">{{ formatCurrency(row.manufacturer_pending_cost) }}</td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Summary (same as cards)</h3>
                    <dl class="grid max-w-lg gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <div class="flex justify-between"><dt>Total income</dt><dd class="tabular-nums">{{ formatCurrency(report.total_income) }}</dd></div>
                        <div class="flex justify-between"><dt>Total COGS</dt><dd class="tabular-nums">{{ formatCurrency(report.total_cogs) }}</dd></div>
                        <div class="flex justify-between font-semibold"><dt>Gross profit</dt><dd class="tabular-nums">{{ formatCurrency(report.gross_profit) }}</dd></div>
                        <div class="flex justify-between"><dt>Net profit</dt><dd class="tabular-nums">{{ formatCurrency(report.net_profit) }}</dd></div>
                    </dl>
                </div>
            </div>
        </div>
    </TenantLayout>
</template>
