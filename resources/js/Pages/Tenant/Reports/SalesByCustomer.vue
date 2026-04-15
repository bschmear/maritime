<script setup>
import axios from 'axios';
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import ReportSwitcher from '@/Components/Tenant/Reports/ReportSwitcher.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    recordTitle: {
        type: String,
        default: 'Sales By Customer',
    },
    rows: {
        type: Array,
        default: () => [],
    },
    detailRows: {
        type: Array,
        default: () => [],
    },
    summary: {
        type: Object,
        default: () => ({}),
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    dateRange: {
        type: String,
        default: '',
    },
    viewMode: {
        type: String,
        default: 'summary',
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
const searchQuery = ref('');
const viewMode = ref(props.viewMode === 'detail' ? 'detail' : 'summary');
const expandedContactId = ref(null);
const loadingContactId = ref(null);
const detailError = ref('');

const tableRows = computed(() => props.rows ?? []);
const totals = computed(() => props.summary ?? {});
const detailRows = computed(() => props.detailRows ?? []);

const detailCache = ref(new Map());

watch(
    () => props.viewMode,
    (mode) => {
        viewMode.value = mode === 'detail' ? 'detail' : 'summary';
    }
);

const normalizedSearch = computed(() => searchQuery.value.trim().toLowerCase());
const filteredSummaryRows = computed(() => {
    if (!normalizedSearch.value) return tableRows.value;
    return tableRows.value.filter((row) =>
        String(row.customer_name ?? '')
            .toLowerCase()
            .includes(normalizedSearch.value)
    );
});

function formatCurrency(value) {
    if (value == null || value === '') return '$0.00';
    const number = Number(value);
    if (Number.isNaN(number)) return '$0.00';
    return number.toLocaleString('en-US', { style: 'currency', currency: 'USD' });
}

function formatDate(value) {
    if (!value) return '—';
    try {
        return new Date(value).toLocaleDateString(undefined, {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    } catch {
        return value;
    }
}

function statusClass(status) {
    const normalized = String(status || '').toLowerCase();
    if (normalized === 'paid') {
        return 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200';
    }
    if (normalized === 'partial' || normalized === 'viewed' || normalized === 'sent') {
        return 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200';
    }
    return 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200';
}

function applyFilters() {
    router.get(
        route('reports.sales-by-customer'),
        {
            date_from: dateFrom.value || undefined,
            date_to: dateTo.value || undefined,
            view: viewMode.value,
        },
        { preserveScroll: true }
    );
}

function switchView(mode) {
    if (mode !== 'summary' && mode !== 'detail') return;
    if (mode === viewMode.value) return;
    viewMode.value = mode;
    expandedContactId.value = null;
    detailError.value = '';
    router.get(
        route('reports.sales-by-customer'),
        {
            date_from: dateFrom.value || undefined,
            date_to: dateTo.value || undefined,
            view: mode,
        },
        { preserveScroll: true }
    );
}

function customerDetail(contactId) {
    return detailCache.value.get(contactId) ?? null;
}

async function toggleCustomer(contactId) {
    if (!contactId) return;

    if (expandedContactId.value === contactId) {
        expandedContactId.value = null;
        return;
    }

    expandedContactId.value = contactId;
    detailError.value = '';

    if (detailCache.value.has(contactId)) {
        return;
    }

    loadingContactId.value = contactId;

    try {
        const { data } = await axios.get(route('reports.sales-by-customer.invoices', { contact: contactId }), {
            params: {
                date_from: dateFrom.value || undefined,
                date_to: dateTo.value || undefined,
            },
        });
        detailCache.value.set(contactId, {
            summary: data.summary ?? {},
            rows: Array.isArray(data.rows) ? data.rows : [],
        });
    } catch (_error) {
        detailError.value = 'Unable to load customer details right now.';
    } finally {
        if (loadingContactId.value === contactId) {
            loadingContactId.value = null;
        }
    }
}
</script>

<template>
    <Head :title="props.recordTitle" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full flex items-center justify-between">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="flex items-center space-x-4">
                    <ReportSwitcher current-route-name="reports.sales-by-customer" />
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
                        <button
                            type="button"
                            class="rounded-lg bg-primary-600 px-4 py-2 text-md font-medium text-white hover:bg-primary-700"
                            @click="applyFilters"
                        >
                            Apply
                        </button>
                    </div>
                    <div class="inline-flex rounded-lg border border-gray-200 bg-gray-50 p-1 dark:border-gray-700 dark:bg-gray-900/40">
                        <button
                            type="button"
                            class="rounded-md px-3 py-1.5 text-sm font-medium transition"
                            :class="viewMode === 'summary' ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-700 dark:text-white' : 'text-gray-600 dark:text-gray-300'"
                            @click="switchView('summary')"
                        >
                            Summary
                        </button>
                        <button
                            type="button"
                            class="rounded-md px-3 py-1.5 text-sm font-medium transition"
                            :class="viewMode === 'detail' ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-700 dark:text-white' : 'text-gray-600 dark:text-gray-300'"
                            @click="switchView('detail')"
                        >
                            Detail
                        </button>
                    </div>
                </div>
                <div class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                    Date range: <span class="font-semibold text-gray-700 dark:text-gray-300">{{ props.dateRange }}</span>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-center justify-between gap-2 border-b border-gray-100 px-5 py-3 dark:border-gray-700">
                    <h3 class="text-md font-semibold text-gray-900 dark:text-white">Sales by Customer</h3>
                    <div class="flex items-center gap-3">
                        <input
                            v-model.trim="searchQuery"
                            type="search"
                            class="input-style w-56 text-sm"
                            placeholder="Search customer..."
                        >
                        <span class="text-sm text-gray-500 dark:text-gray-400">
                            <template v-if="viewMode === 'summary'">{{ filteredSummaryRows.length }}</template>
                            <template v-else>{{ detailRows.length }}</template>
                            rows
                        </span>
                    </div>
                </div>
                <div v-if="viewMode === 'summary'" class="overflow-x-auto">
                    <table class="min-w-full text-md">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50/80 text-left text-sm font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-400">
                                <th class="w-10 px-4 py-3" />
                                <th class="px-4 py-3">Customer</th>
                                <th class="px-4 py-3 text-right">Invoices</th>
                                <th class="px-4 py-3 text-right">Sales</th>
                                <th class="px-4 py-3 text-right">Tax</th>
                                <th class="px-4 py-3 text-right">Paid</th>
                                <th class="px-4 py-3 text-right">Balance Due</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <template v-for="row in filteredSummaryRows" :key="`summary-${row.contact_id ?? 'unknown'}-${row.customer_name}`">
                                <tr
                                    class="cursor-pointer hover:bg-gray-50/80 dark:hover:bg-gray-700/30"
                                    @click="toggleCustomer(row.contact_id)"
                                >
                                    <td class="px-4 py-3 text-gray-400">
                                        <svg
                                            class="h-4 w-4 transition-transform"
                                            :class="expandedContactId === row.contact_id ? 'rotate-90' : ''"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                        >
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </td>
                                    <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">
                                        {{ row.customer_name }}
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums text-gray-600 dark:text-gray-300">
                                        {{ row.invoice_count }}
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums font-medium text-gray-900 dark:text-white">
                                        {{ formatCurrency(row.total_sales) }}
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums text-gray-600 dark:text-gray-300">
                                        {{ formatCurrency(row.total_tax) }}
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums text-gray-600 dark:text-gray-300">
                                        {{ formatCurrency(row.total_paid) }}
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums font-medium text-gray-900 dark:text-white">
                                        {{ formatCurrency(row.total_due) }}
                                    </td>
                                </tr>
                                <tr v-if="expandedContactId === row.contact_id">
                                    <td colspan="7" class="bg-gray-50/70 px-6 py-4 dark:bg-gray-900/30">
                                        <div v-if="loadingContactId === row.contact_id" class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                                            Loading customer details...
                                        </div>
                                        <div v-else-if="detailError" class="py-6 text-center text-sm text-red-600 dark:text-red-400">
                                            {{ detailError }}
                                        </div>
                                        <div v-else-if="customerDetail(row.contact_id)">
                                            <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                                <div class="rounded-lg border border-gray-200 bg-white px-4 py-3 dark:border-gray-700 dark:bg-gray-800">
                                                    <p class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Sales</p>
                                                    <p class="mt-1 text-lg font-bold text-gray-900 dark:text-white">
                                                        {{ formatCurrency(customerDetail(row.contact_id).summary.total_sales) }}
                                                    </p>
                                                </div>
                                                <div class="rounded-lg border border-gray-200 bg-white px-4 py-3 dark:border-gray-700 dark:bg-gray-800">
                                                    <p class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Invoices</p>
                                                    <p class="mt-1 text-lg font-bold text-gray-900 dark:text-white tabular-nums">
                                                        {{ customerDetail(row.contact_id).summary.invoice_count ?? 0 }}
                                                    </p>
                                                </div>
                                                <div class="rounded-lg border border-gray-200 bg-white px-4 py-3 dark:border-gray-700 dark:bg-gray-800">
                                                    <p class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Paid</p>
                                                    <p class="mt-1 text-lg font-bold text-gray-900 dark:text-white">
                                                        {{ formatCurrency(customerDetail(row.contact_id).summary.total_paid) }}
                                                    </p>
                                                </div>
                                                <div class="rounded-lg border border-gray-200 bg-white px-4 py-3 dark:border-gray-700 dark:bg-gray-800">
                                                    <p class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Balance Due</p>
                                                    <p class="mt-1 text-lg font-bold text-gray-900 dark:text-white">
                                                        {{ formatCurrency(customerDetail(row.contact_id).summary.total_due) }}
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                                                <table class="min-w-full text-sm">
                                                    <thead class="bg-gray-100/80 dark:bg-gray-900/40">
                                                        <tr class="text-left font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                                            <th class="px-3 py-2">Invoice</th>
                                                            <th class="px-3 py-2">Date</th>
                                                            <th class="px-3 py-2">Status</th>
                                                            <th class="px-3 py-2 text-right">Total</th>
                                                            <th class="px-3 py-2 text-right">Paid</th>
                                                            <th class="px-3 py-2 text-right">Due</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                                        <tr v-for="invoice in customerDetail(row.contact_id).rows" :key="`detail-${invoice.id}`">
                                                            <td class="px-3 py-2 font-medium text-gray-900 dark:text-white">
                                                                <Link
                                                                    :href="route('invoices.show', invoice.invoice_id)"
                                                                    class="text-primary-600 hover:underline dark:text-primary-400"
                                                                >
                                                                    {{ invoice.invoice_label }}
                                                                </Link>
                                                            </td>
                                                            <td class="px-3 py-2 text-gray-600 dark:text-gray-300">{{ formatDate(invoice.created_at) }}</td>
                                                            <td class="px-3 py-2">
                                                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium capitalize" :class="statusClass(invoice.status)">
                                                                    {{ invoice.status || '—' }}
                                                                </span>
                                                            </td>
                                                            <td class="px-3 py-2 text-right tabular-nums text-gray-900 dark:text-white">{{ formatCurrency(invoice.total) }}</td>
                                                            <td class="px-3 py-2 text-right tabular-nums text-gray-600 dark:text-gray-300">{{ formatCurrency(invoice.amount_paid) }}</td>
                                                            <td class="px-3 py-2 text-right tabular-nums font-medium text-gray-900 dark:text-white">{{ formatCurrency(invoice.amount_due) }}</td>
                                                        </tr>
                                                        <tr v-if="customerDetail(row.contact_id).rows.length === 0">
                                                            <td colspan="6" class="px-3 py-5 text-center text-sm text-gray-500 dark:text-gray-400">
                                                                No invoices found for this customer in the selected date range.
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                            <tr v-if="filteredSummaryRows.length === 0">
                                <td colspan="7" class="px-4 py-12 text-center text-md text-gray-500 dark:text-gray-400">
                                    No customers match your search for this date range.
                                </td>
                            </tr>
                        </tbody>
                        <tfoot v-if="filteredSummaryRows.length > 0" class="border-t border-gray-200 bg-gray-50/80 dark:border-gray-700 dark:bg-gray-900/40">
                            <tr class="font-semibold text-gray-900 dark:text-white">
                                <td class="px-4 py-3" />
                                <td class="px-4 py-3">Total</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ totals.invoice_count ?? 0 }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ formatCurrency(totals.total_sales) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ formatCurrency(totals.total_tax) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ formatCurrency(totals.total_paid) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ formatCurrency(totals.total_due) }}</td>
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
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3 text-right">Total</th>
                                <th class="px-4 py-3 text-right">Paid</th>
                                <th class="px-4 py-3 text-right">Due</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <tr
                                v-for="row in detailRows.filter((r) => String(r.customer_name ?? '').toLowerCase().includes(normalizedSearch))"
                                :key="`flat-${row.id}`"
                            >
                                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ row.customer_name }}</td>
                                <td class="px-4 py-3">
                                    <Link
                                        :href="route('invoices.show', row.invoice_id)"
                                        class="text-primary-600 hover:underline dark:text-primary-400"
                                    >
                                        {{ row.invoice_label }}
                                    </Link>
                                </td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ formatDate(row.created_at) }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium capitalize" :class="statusClass(row.status)">
                                        {{ row.status || '—' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums text-gray-900 dark:text-white">{{ formatCurrency(row.total) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-gray-600 dark:text-gray-300">{{ formatCurrency(row.amount_paid) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums font-medium text-gray-900 dark:text-white">{{ formatCurrency(row.amount_due) }}</td>
                            </tr>
                            <tr v-if="detailRows.filter((r) => String(r.customer_name ?? '').toLowerCase().includes(normalizedSearch)).length === 0">
                                <td colspan="7" class="px-4 py-12 text-center text-md text-gray-500 dark:text-gray-400">
                                    No invoice rows match your search for this date range.
                                </td>
                            </tr>
                        </tbody>
                        <tfoot v-if="detailRows.length > 0" class="border-t border-gray-200 bg-gray-50/80 dark:border-gray-700 dark:bg-gray-900/40">
                            <tr class="font-semibold text-gray-900 dark:text-white">
                                <td colspan="4" class="px-4 py-3">Grand total</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ formatCurrency(totals.total_sales) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ formatCurrency(totals.total_paid) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ formatCurrency(totals.total_due) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </TenantLayout>
</template>

