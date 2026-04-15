<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import ReportSwitcher from '@/Components/Tenant/Reports/ReportSwitcher.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    recordTitle: { type: String, default: 'Sales By Item Summary' },
    rows: { type: Array, default: () => [] },
    detailRows: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({}) },
    viewMode: { type: String, default: 'summary' },
    filters: { type: Object, default: () => ({}) },
    dateRange: { type: String, default: '' },
});

const dateFrom = ref(props.filters?.date_from ?? '');
const dateTo = ref(props.filters?.date_to ?? '');
const searchQuery = ref('');
const viewMode = ref(props.viewMode === 'detail' ? 'detail' : 'summary');

watch(
    () => props.viewMode,
    (value) => {
        viewMode.value = value === 'detail' ? 'detail' : 'summary';
    }
);

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Reports' },
    { label: props.recordTitle },
]);

const normalizedSearch = computed(() => searchQuery.value.trim().toLowerCase());
const filteredRows = computed(() =>
    (props.rows ?? []).filter((row) => String(row.item_name ?? '').toLowerCase().includes(normalizedSearch.value))
);
const filteredDetailRows = computed(() =>
    (props.detailRows ?? []).filter((row) => {
        const item = String(row.item_name ?? '').toLowerCase();
        const customer = String(row.customer_name ?? '').toLowerCase();
        return item.includes(normalizedSearch.value) || customer.includes(normalizedSearch.value);
    })
);

function formatCurrency(value) {
    if (value == null || value === '') return '$0.00';
    const n = Number(value);
    if (Number.isNaN(n)) return '$0.00';
    return n.toLocaleString('en-US', { style: 'currency', currency: 'USD' });
}

function formatNumber(value) {
    const n = Number(value ?? 0);
    if (Number.isNaN(n)) return '0';
    return n.toLocaleString('en-US', { maximumFractionDigits: 2 });
}

function formatDate(value) {
    if (!value) return '—';
    try {
        return new Date(value).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
    } catch {
        return value;
    }
}

function statusClass(status) {
    const normalized = String(status ?? '').toLowerCase();
    if (normalized === 'paid') return 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200';
    if (normalized === 'partial' || normalized === 'sent' || normalized === 'viewed') {
        return 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200';
    }
    return 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200';
}

function applyFilters() {
    router.get(
        viewMode.value === 'detail' ? route('reports.sales-by-item-detail') : route('reports.sales-by-item-summary'),
        { date_from: dateFrom.value || undefined, date_to: dateTo.value || undefined },
        { preserveScroll: true }
    );
}

function switchView(mode) {
    if (mode === viewMode.value) return;
    viewMode.value = mode;
    router.get(
        mode === 'detail' ? route('reports.sales-by-item-detail') : route('reports.sales-by-item-summary'),
        { date_from: dateFrom.value || undefined, date_to: dateTo.value || undefined },
        { preserveScroll: true }
    );
}
</script>

<template>
    <Head :title="recordTitle" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full flex items-center justify-between">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="flex items-center space-x-4">
                    <ReportSwitcher :current-route-name="viewMode === 'detail' ? 'reports.sales-by-item-detail' : 'reports.sales-by-item-summary'" />
                </div>
            </div>
        </template>

        <div class="mx-auto w-full space-y-8 p-4 pb-16">
            <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div class="flex flex-wrap items-end gap-4">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-500 dark:text-gray-400">From</label>
                            <input v-model="dateFrom" type="date" class="input-style text-md">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-500 dark:text-gray-400">To</label>
                            <input v-model="dateTo" type="date" class="input-style text-md">
                        </div>
                        <button type="button" class="rounded-lg bg-primary-600 px-4 py-2 text-md font-medium text-white hover:bg-primary-700" @click="applyFilters">
                            Apply
                        </button>
                    </div>
                    <div class="inline-flex rounded-lg border border-gray-200 bg-gray-50 p-1 dark:border-gray-700 dark:bg-gray-900/40">
                        <button type="button" class="rounded-md px-3 py-1.5 text-sm font-medium transition" :class="viewMode === 'summary' ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-700 dark:text-white' : 'text-gray-600 dark:text-gray-300'" @click="switchView('summary')">Summary</button>
                        <button type="button" class="rounded-md px-3 py-1.5 text-sm font-medium transition" :class="viewMode === 'detail' ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-700 dark:text-white' : 'text-gray-600 dark:text-gray-300'" @click="switchView('detail')">Detail</button>
                    </div>
                </div>
                <div class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                    Date range: <span class="font-semibold text-gray-700 dark:text-gray-300">{{ dateRange }}</span>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-center justify-between gap-2 border-b border-gray-100 px-5 py-3 dark:border-gray-700">
                    <h3 class="text-md font-semibold text-gray-900 dark:text-white">Sales by Item</h3>
                    <div class="flex items-center gap-3">
                        <input v-model.trim="searchQuery" type="search" class="input-style w-56 text-sm" placeholder="Search item or customer...">
                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ viewMode === 'detail' ? filteredDetailRows.length : filteredRows.length }} rows</span>
                    </div>
                </div>

                <div v-if="viewMode === 'summary'" class="overflow-x-auto">
                    <table class="min-w-full text-md">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50/80 text-left text-sm font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-400">
                                <th class="px-4 py-3">Item</th>
                                <th class="px-4 py-3 text-right">Lines</th>
                                <th class="px-4 py-3 text-right">Qty</th>
                                <th class="px-4 py-3 text-right">Subtotal</th>
                                <th class="px-4 py-3 text-right">Tax</th>
                                <th class="px-4 py-3 text-right">Sales</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <tr v-for="row in filteredRows" :key="`sum-${row.item_name}`" class="hover:bg-gray-50/80 dark:hover:bg-gray-700/30">
                                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ row.item_name }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-gray-600 dark:text-gray-300">{{ row.line_count }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-gray-600 dark:text-gray-300">{{ formatNumber(row.quantity_total) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-gray-600 dark:text-gray-300">{{ formatCurrency(row.subtotal_total) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-gray-600 dark:text-gray-300">{{ formatCurrency(row.tax_total) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums font-medium text-gray-900 dark:text-white">{{ formatCurrency(row.total_sales) }}</td>
                            </tr>
                            <tr v-if="filteredRows.length === 0">
                                <td colspan="6" class="px-4 py-12 text-center text-md text-gray-500 dark:text-gray-400">No item rows found for the selected range.</td>
                            </tr>
                        </tbody>
                        <tfoot v-if="filteredRows.length" class="border-t border-gray-200 bg-gray-50/80 dark:border-gray-700 dark:bg-gray-900/40">
                            <tr class="font-semibold text-gray-900 dark:text-white">
                                <td class="px-4 py-3">Total</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ summary.line_count ?? 0 }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ formatNumber(summary.quantity_total) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ formatCurrency(summary.subtotal_total) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ formatCurrency(summary.tax_total) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ formatCurrency(summary.total_sales) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="min-w-full text-md">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50/80 text-left text-sm font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-400">
                                <th class="px-4 py-3">Customer</th>
                                <th class="px-4 py-3">Invoice</th>
                                <th class="px-4 py-3">Date</th>
                                <th class="px-4 py-3">Item</th>
                                <th class="px-4 py-3 text-right">Qty</th>
                                <th class="px-4 py-3 text-right">Sales</th>
                                <th class="px-4 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <tr v-for="row in filteredDetailRows" :key="`det-${row.id}`" class="hover:bg-gray-50/80 dark:hover:bg-gray-700/30">
                                <td class="px-4 py-3 text-gray-900 dark:text-white">{{ row.customer_name }}</td>
                                <td class="px-4 py-3">
                                    <Link :href="route('invoices.show', row.invoice_id)" class="text-primary-600 hover:underline dark:text-primary-400">
                                        {{ row.invoice_label }}
                                    </Link>
                                </td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ formatDate(row.invoice_date) }}</td>
                                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ row.item_name }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-gray-600 dark:text-gray-300">{{ formatNumber(row.quantity) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums font-medium text-gray-900 dark:text-white">{{ formatCurrency(row.total_sales) }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium capitalize" :class="statusClass(row.invoice_status)">
                                        {{ row.invoice_status || '—' }}
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="filteredDetailRows.length === 0">
                                <td colspan="7" class="px-4 py-12 text-center text-md text-gray-500 dark:text-gray-400">No item detail rows match your filters.</td>
                            </tr>
                        </tbody>
                        <tfoot v-if="filteredDetailRows.length" class="border-t border-gray-200 bg-gray-50/80 dark:border-gray-700 dark:bg-gray-900/40">
                            <tr class="font-semibold text-gray-900 dark:text-white">
                                <td colspan="5" class="px-4 py-3">Grand total</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ formatCurrency(summary.total_sales) }}</td>
                                <td class="px-4 py-3" />
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </TenantLayout>
</template>
