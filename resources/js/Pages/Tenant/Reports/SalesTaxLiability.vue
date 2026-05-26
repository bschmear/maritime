<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import ReportSwitcher from '@/Components/Tenant/Reports/ReportSwitcher.vue';
import ApexPieChart from '@/Components/Charts/ApexPieChart.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const PIE_COLORS = ['#2563eb', '#16a34a', '#ca8a04', '#9333ea', '#db2777', '#0891b2', '#4f46e5'];

const props = defineProps({
    recordTitle: {
        type: String,
        default: 'Sales Tax Liability',
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
    viewMode: {
        type: String,
        default: 'summary',
    },
    basis: {
        type: String,
        default: 'accrual',
    },
});

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Reports' },
    { label: props.recordTitle },
]);

const dateFrom = ref(props.filters?.date_from ?? '');
const dateTo = ref(props.filters?.date_to ?? '');
const subsidiaryId = ref(props.filters?.subsidiary_id ?? null);
const locationId = ref(props.filters?.location_id ?? null);
const viewMode = ref(props.viewMode === 'detail' ? 'detail' : 'summary');
const basis = ref(props.basis === 'cash' ? 'cash' : 'accrual');

watch(
    () => props.viewMode,
    (v) => {
        viewMode.value = v === 'detail' ? 'detail' : 'summary';
    }
);
watch(
    () => props.basis,
    (b) => {
        basis.value = b === 'cash' ? 'cash' : 'accrual';
    }
);

const report = computed(() => props.report ?? {});
const summary = computed(() => report.value.summary ?? {});
const groups = computed(() => report.value.groups ?? []);
const rows = computed(() => report.value.rows ?? []);
const options = computed(() => props.options ?? {});
const dateRangeLabel = computed(() => props.dateRange ?? '');

const jurisdictionPie = computed(() => {
    const byJ = {};
    for (const g of groups.value) {
        const j = g.jurisdiction || 'Unknown';
        byJ[j] = (byJ[j] ?? 0) + Number(g.tax_amount ?? 0);
    }
    const labels = Object.keys(byJ);
    const series = labels.map((k) => byJ[k]);
    return {
        labels,
        series,
        colors: PIE_COLORS.slice(0, Math.max(labels.length, 1)),
    };
});

function formatCurrency(value) {
    const n = Number(value ?? 0);
    if (Number.isNaN(n)) return '$0.00';
    return n.toLocaleString('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 2 });
}

function formatPercent(rate) {
    const n = Number(rate ?? 0);
    if (Number.isNaN(n)) return '0%';
    return `${n.toLocaleString('en-US', { maximumFractionDigits: 3 })}%`;
}

function formatDate(value) {
    if (!value) return '—';
    try {
        return new Date(value).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
    } catch {
        return value;
    }
}

function sourceHref(row) {
    if (row.source_type === 'invoice') return route('invoices.show', row.source_id);
    if (row.source_type === 'transaction') return route('transactions.show', row.source_id);
    if (row.source_type === 'service_ticket') return route('servicetickets.show', row.source_id);
    return '#';
}

function applyFilters() {
    router.get(
        route('reports.sales-tax-liability'),
        {
            date_from: dateFrom.value || undefined,
            date_to: dateTo.value || undefined,
            subsidiary_id: subsidiaryId.value || undefined,
            location_id: locationId.value || undefined,
            view: viewMode.value,
            basis: basis.value,
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
                    <ReportSwitcher current-route-name="reports.sales-tax-liability" />
                </div>
            </div>
        </template>

        <div class="mx-auto w-full space-y-6 p-4 pb-16">
            <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex flex-wrap items-end gap-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-500 dark:text-gray-400">From</label>
                        <input v-model="dateFrom" type="date" class="input-style text-md" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-500 dark:text-gray-400">To</label>
                        <input v-model="dateTo" type="date" class="input-style text-md" />
                    </div>
                    <div class="min-w-[14rem]">
                        <label class="mb-1 block text-sm font-medium text-gray-500 dark:text-gray-400">Subsidiary</label>
                        <select v-model="subsidiaryId" class="input-style w-full text-md">
                            <option :value="null">All subsidiaries</option>
                            <option v-for="opt in options.subsidiaries ?? []" :key="`sub-${opt.id}`" :value="opt.id">
                                {{ opt.label }}
                            </option>
                        </select>
                    </div>
                    <div class="min-w-[14rem]">
                        <label class="mb-1 block text-sm font-medium text-gray-500 dark:text-gray-400">Location</label>
                        <select v-model="locationId" class="input-style w-full text-md">
                            <option :value="null">All locations</option>
                            <option v-for="opt in options.locations ?? []" :key="`loc-${opt.id}`" :value="opt.id">
                                {{ opt.label }}
                            </option>
                        </select>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-500 dark:text-gray-400">View</label>
                            <div class="flex rounded-lg border border-gray-200 dark:border-gray-600">
                                <button
                                    type="button"
                                    class="px-3 py-2 text-sm"
                                    :class="
                                        viewMode === 'summary'
                                            ? 'bg-primary-600 text-white'
                                            : 'text-gray-700 dark:text-gray-300'
                                    "
                                    @click="viewMode = 'summary'"
                                >
                                    Summary
                                </button>
                                <button
                                    type="button"
                                    class="px-3 py-2 text-sm"
                                    :class="
                                        viewMode === 'detail'
                                            ? 'bg-primary-600 text-white'
                                            : 'text-gray-700 dark:text-gray-300'
                                    "
                                    @click="viewMode = 'detail'"
                                >
                                    Detail
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-500 dark:text-gray-400">Basis</label>
                            <div class="flex rounded-lg border border-gray-200 dark:border-gray-600">
                                <button
                                    type="button"
                                    class="px-3 py-2 text-sm"
                                    :class="
                                        basis === 'accrual'
                                            ? 'bg-primary-600 text-white'
                                            : 'text-gray-700 dark:text-gray-300'
                                    "
                                    @click="basis = 'accrual'"
                                >
                                    Accrual
                                </button>
                                <button
                                    type="button"
                                    class="px-3 py-2 text-sm"
                                    :class="
                                        basis === 'cash' ? 'bg-primary-600 text-white' : 'text-gray-700 dark:text-gray-300'
                                    "
                                    @click="basis = 'cash'"
                                >
                                    Cash
                                </button>
                            </div>
                        </div>
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
                    Date range: <span class="font-semibold text-gray-700 dark:text-gray-300">{{ dateRangeLabel }}</span>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Taxable (customer)</div>
                    <div class="mt-1 text-xl font-semibold tabular-nums text-gray-900 dark:text-white">
                        {{ formatCurrency(summary.taxable_total) }}
                    </div>
                </div>
                <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Tax charged</div>
                    <div class="mt-1 text-xl font-semibold tabular-nums text-gray-900 dark:text-white">
                        {{ formatCurrency(summary.tax_total) }}
                    </div>
                </div>
                <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Tax from payments (cash)</div>
                    <div class="mt-1 text-xl font-semibold tabular-nums text-gray-900 dark:text-white">
                        {{ formatCurrency(summary.tax_collected_total) }}
                    </div>
                    <p v-if="basis === 'accrual'" class="mt-1 text-xs text-gray-500 dark:text-gray-400">Switch to Cash basis to allocate tax from payments in this period.</p>
                </div>
                <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Uninvoiced pipeline tax</div>
                    <div class="mt-1 text-xl font-semibold tabular-nums text-amber-700 dark:text-amber-300">
                        {{ formatCurrency(summary.uninvoiced_tax_total) }}
                    </div>
                </div>
            </div>

            <div
                v-if="viewMode === 'summary' && jurisdictionPie.labels.length"
                class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
            >
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Tax by jurisdiction</h3>
                <ApexPieChart
                    :labels="jurisdictionPie.labels"
                    :series="jurisdictionPie.series"
                    :colors="jurisdictionPie.colors"
                    :height="280"
                    currency-tooltip
                />
            </div>

            <div v-if="viewMode === 'summary'" class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900/40">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Jurisdiction</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Rate</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Taxable</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Tax</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Cash tax</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Rows</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr v-for="(g, idx) in groups" :key="`g-${idx}`" class="hover:bg-gray-50 dark:hover:bg-gray-900/30">
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ g.jurisdiction }}</td>
                            <td class="px-4 py-3 text-right text-sm tabular-nums text-gray-700 dark:text-gray-300">{{ formatPercent(g.tax_rate) }}</td>
                            <td class="px-4 py-3 text-right text-sm tabular-nums text-gray-700 dark:text-gray-300">{{ formatCurrency(g.taxable_amount) }}</td>
                            <td class="px-4 py-3 text-right text-sm font-medium tabular-nums text-gray-900 dark:text-white">{{ formatCurrency(g.tax_amount) }}</td>
                            <td class="px-4 py-3 text-right text-sm tabular-nums text-gray-700 dark:text-gray-300">{{ formatCurrency(g.tax_collected) }}</td>
                            <td class="px-4 py-3 text-right text-sm tabular-nums text-gray-500 dark:text-gray-400">{{ g.row_count }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-else class="overflow-x-auto rounded-xl border border-gray-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900/40">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Source</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Customer</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Jurisdiction</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Rate</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Taxable</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Tax</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Cash tax</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr v-for="(row, idx) in rows" :key="`r-${idx}`" class="hover:bg-gray-50 dark:hover:bg-gray-900/30">
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ formatDate(row.document_date) }}</td>
                            <td class="px-4 py-3 text-sm">
                                <Link :href="sourceHref(row)" class="font-medium text-primary-600 hover:text-primary-800 dark:text-primary-400">
                                    {{ row.source_label }}
                                </Link>
                                <span class="ml-2 rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                    {{ row.source_type }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ row.customer_name }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ row.jurisdiction }}</td>
                            <td class="px-4 py-3 text-right text-sm tabular-nums text-gray-700 dark:text-gray-300">{{ formatPercent(row.tax_rate) }}</td>
                            <td class="px-4 py-3 text-right text-sm tabular-nums text-gray-700 dark:text-gray-300">{{ formatCurrency(row.taxable_amount) }}</td>
                            <td class="px-4 py-3 text-right text-sm font-medium tabular-nums text-gray-900 dark:text-white">{{ formatCurrency(row.tax_amount) }}</td>
                            <td class="px-4 py-3 text-right text-sm tabular-nums text-gray-700 dark:text-gray-300">{{ formatCurrency(row.tax_collected) }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                                <span v-if="row.source_type === 'invoice'">{{ row.invoice_status }} / {{ row.payment_status }}</span>
                                <span v-else>{{ row.payment_status }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </TenantLayout>
</template>
