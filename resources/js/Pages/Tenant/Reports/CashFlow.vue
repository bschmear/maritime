<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import ReportSwitcher from '@/Components/Tenant/Reports/ReportSwitcher.vue';
import ApexPieChart from '@/Components/Charts/ApexPieChart.vue';
import ApexLineChart from '@/Components/Charts/ApexLineChart.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const METHOD_PIE_COLORS = ['#2563eb', '#16a34a', '#ca8a04', '#9333ea', '#db2777', '#0891b2', '#4f46e5'];

const props = defineProps({
    recordTitle: {
        type: String,
        default: 'Cash Flow',
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

const report = computed(() => props.report ?? {});

const methodPieLabels = computed(() => report.value.method_pie?.labels ?? []);
const methodPieSeries = computed(() => report.value.method_pie?.series ?? []);
const methodPieColors = computed(() =>
    METHOD_PIE_COLORS.slice(0, Math.max(methodPieLabels.value.length, methodPieSeries.value.length))
);

function formatCurrency(value) {
    const n = Number(value ?? 0);
    if (Number.isNaN(n)) return '$0.00';
    return n.toLocaleString('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 2 });
}

function applyFilters() {
    router.get(
        route('reports.cash-flow'),
        {
            date_from: dateFrom.value || undefined,
            date_to: dateTo.value || undefined,
            subsidiary_id: subsidiaryId.value || undefined,
            location_id: locationId.value || undefined,
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
                    <ReportSwitcher current-route-name="reports.cash-flow" />
                </div>
            </div>
        </template>

        <div class="mx-auto w-full max-w-6xl space-y-6 p-4 pb-16">
            <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
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
                <div class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                    Date range: <span class="font-semibold text-gray-700 dark:text-gray-300">{{ props.dateRange }}</span>
                </div>
            </div>

            <div
                class="rounded-lg border border-blue-100 bg-blue-50/60 p-4 text-sm text-blue-900 dark:border-blue-900/40 dark:bg-blue-950/30 dark:text-blue-100"
            >
                <p class="font-medium">Cash basis vs. P&L</p>
                <p class="mt-1 text-blue-800/90 dark:text-blue-200/90">
                    This report shows <strong>cash collected</strong> from payments and <strong>cash returned</strong> from refunds
                    in the selected period (by payment / refund timestamp). Profit & Loss uses invoice line recognition dates and
                    accrual-style buckets—use P&L for margin and warranty cost, and Cash Flow for liquidity.
                </p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Cash in</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums text-gray-900 dark:text-white">
                        {{ formatCurrency(report.cash_in) }}
                    </p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ report.payment_count ?? 0 }} payments</p>
                </div>
                <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Cash out (refunds)</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums text-red-700 dark:text-red-400">
                        {{ formatCurrency(report.cash_out_refunds) }}
                    </p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ report.refund_count ?? 0 }} refunds</p>
                </div>
                <div class="rounded-xl border border-green-200 bg-green-50/50 p-4 shadow-sm dark:border-green-900/40 dark:bg-green-950/25 sm:col-span-2 lg:col-span-2">
                    <p class="text-xs font-semibold uppercase tracking-wide text-green-800 dark:text-green-300">Net cash (period)</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums text-green-900 dark:text-green-100">
                        {{ formatCurrency(report.net_cash) }}
                    </p>
                </div>
            </div>

            <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h3 class="mb-1 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Daily cash movement</h3>
                <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">Payments and completed refunds allocated by day.</p>
                <ApexLineChart
                    :categories="report.chart?.categories ?? []"
                    :series="report.chart?.series ?? []"
                    :height="340"
                    :colors="['#16a34a', '#dc2626']"
                />
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="mb-1 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Cash in by method</h3>
                    <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">Share of collected cash in this period.</p>
                    <div class="flex flex-col items-center gap-4 sm:flex-row sm:items-start sm:justify-center">
                        <ApexPieChart
                            :series="methodPieSeries"
                            :labels="methodPieLabels"
                            :colors="methodPieColors"
                            :height="160"
                            currency-tooltip
                        />
                        <dl class="min-w-0 flex-1 space-y-2 text-sm">
                            <div
                                v-for="row in (report.by_method ?? [])"
                                :key="row.code"
                                class="flex justify-between gap-4 text-gray-700 dark:text-gray-300"
                            >
                                <dt class="truncate">{{ row.label }}</dt>
                                <dd class="shrink-0 tabular-nums font-medium">{{ formatCurrency(row.amount) }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <div class="rounded-xl border border-amber-100 bg-amber-50/40 p-5 shadow-sm dark:border-amber-900/40 dark:bg-amber-950/20">
                    <h3 class="mb-1 text-sm font-semibold uppercase tracking-wide text-amber-900 dark:text-amber-200">Open receivables</h3>
                    <p class="mb-3 text-sm text-amber-900/80 dark:text-amber-200/80">
                        Not a period cash flow: unpaid balances on open invoices matching your subsidiary / location filters (current
                        snapshot).
                    </p>
                    <p class="text-2xl font-bold tabular-nums text-amber-950 dark:text-amber-50">
                        {{ formatCurrency(report.open_ar?.amount_due) }}
                    </p>
                    <p class="mt-1 text-sm text-amber-900/80 dark:text-amber-200/80">
                        {{ report.open_ar?.invoice_count ?? 0 }} invoices with balance due
                    </p>
                </div>
            </div>
        </div>
    </TenantLayout>
</template>
