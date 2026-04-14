<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';

const PAYMENT_METHOD_ENUM = 'App\\Enums\\Payments\\PaymentMethod';

const props = defineProps({
    records: { type: Object, required: true },
    schema: { type: Object, default: null },
    formSchema: { type: Object, default: null },
    fieldsSchema: { type: Object, default: () => ({}) },
    enumOptions: { type: Object, default: () => ({}) },
    recordType: { type: String, default: 'payments' },
    recordTitle: { type: String, default: 'Payment' },
    pluralTitle: { type: String, default: 'Payments' },
    paymentDashboard: { type: Object, required: true },
});

const hero = computed(() => props.paymentDashboard?.hero ?? {});
const context = computed(() => props.paymentDashboard?.context ?? {});
const periodStats = computed(() => props.paymentDashboard?.period ?? {});
const filterState = computed(() => props.paymentDashboard?.filters ?? {});

const page = usePage();

const period = ref(filterState.value.period ?? 'all');
const year = ref(Number(filterState.value.year) || new Date().getFullYear());
const dateFrom = ref(filterState.value.date_from ?? '');
const dateTo = ref(filterState.value.date_to ?? '');
const searchInput = ref('');

function parseUrlQuery() {
    const q = (page.url || '').split('?')[1] ?? '';
    const params = new URLSearchParams(q);
    const out = {};
    for (const [k, v] of params.entries()) {
        if (v !== '') {
            out[k] = v;
        }
    }
    return out;
}

function syncSearchFromUrl() {
    const q = (page.url || '').split('?')[1] ?? '';
    const params = new URLSearchParams(q);
    searchInput.value = params.get('search') ?? '';
}

onMounted(syncSearchFromUrl);
watch(() => page.url, syncSearchFromUrl);

watch(
    () => filterState.value,
    (f) => {
        period.value = f.period ?? 'all';
        year.value = Number(f.year) || new Date().getFullYear();
        dateFrom.value = f.date_from ?? '';
        dateTo.value = f.date_to ?? '';
    },
    { deep: true }
);

const methodOptions = computed(() => props.enumOptions?.[PAYMENT_METHOD_ENUM] ?? []);

function methodLabel(code) {
    if (!code) return '—';
    return methodOptions.value.find((o) => o.value === code)?.name ?? code;
}

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: props.pluralTitle },
]);

function formatCurrency(value) {
    if (value == null || value === '') return '—';
    const n = Number(value);
    if (Number.isNaN(n)) return '—';
    return n.toLocaleString('en-US', { style: 'currency', currency: 'USD' });
}

function formatDateTime(val) {
    if (!val) return '—';
    try {
        return new Date(val).toLocaleString('en-US', { dateStyle: 'medium', timeStyle: 'short' });
    } catch {
        return '—';
    }
}

function invoiceDisplayName(inv) {
    if (!inv) return '—';
    return inv.display_name ?? (inv.sequence != null ? `INV-${inv.sequence}` : `#${inv.id}`);
}

function momSubtext() {
    const p = hero.value.collected_mom_pct;
    if (p == null) return null;
    const sign = p > 0 ? '+' : '';
    return `${sign}${p}% vs last month`;
}

function topMethodSubtext() {
    const tm = periodStats.value.top_method;
    if (!tm?.code) return null;
    const pct = tm.pct != null ? `${tm.pct}% · ` : '';
    return `${pct}${methodLabel(tm.code)}`;
}

function applyPeriodFilters() {
    const q = parseUrlQuery();
    delete q.page;
    q.period = period.value;
    delete q.year;
    delete q.date_from;
    delete q.date_to;
    if (['q1', 'q2', 'q3', 'q4'].includes(period.value)) {
        q.year = year.value;
    }
    if (period.value === 'custom') {
        if (dateFrom.value) {
            q.date_from = dateFrom.value;
        }
        if (dateTo.value) {
            q.date_to = dateTo.value;
        }
    }
    router.get(route('payments.index'), q, { preserveScroll: true });
}

function applySearch() {
    const q = parseUrlQuery();
    delete q.page;
    const s = searchInput.value.trim();
    if (s) {
        q.search = s;
    } else {
        delete q.search;
    }
    router.get(route('payments.index'), q, { preserveScroll: true });
}

function sortUrl(column) {
    const q = parseUrlQuery();
    const cur = q.sort;
    const dir = q.direction === 'asc' ? 'asc' : 'desc';
    const nextDir = cur === column && dir === 'desc' ? 'asc' : 'desc';
    q.sort = column;
    q.direction = nextDir;
    delete q.page;
    return route('payments.index', q);
}

const periodOptions = [
    { value: 'all', label: 'All time' },
    { value: 'mtd', label: 'Month to date' },
    { value: 'last_30', label: 'Last 30 days' },
    { value: 'last_90', label: 'Last 90 days' },
    { value: 'q1', label: 'Q1' },
    { value: 'q2', label: 'Q2' },
    { value: 'q3', label: 'Q3' },
    { value: 'q4', label: 'Q4' },
    { value: 'custom', label: 'Custom range' },
];

const showQuarterYear = computed(() => ['q1', 'q2', 'q3', 'q4'].includes(period.value));
const showCustomDates = computed(() => period.value === 'custom');

const tableRows = computed(() => props.records?.data ?? []);
</script>

<template>
    <Head :title="pluralTitle" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="mt-4 flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                            {{ pluralTitle }}
                        </h2>
                        <p class="mt-1 text-md text-gray-500 dark:text-gray-400 max-w-2xl">
                            Receivables, collections, and risk at a glance. Filter the table by when payments were recorded; hero metrics stay calendar-based for consistent KPIs.
                        </p>
                    </div>
                    <Link
                        :href="route('payments.create')"
                        class="inline-flex items-center gap-2 px-4 py-2.5 text-md font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors shrink-0"
                    >
                        <span class="material-icons text-[18px]">add</span>
                        Log payment
                    </Link>
                </div>
            </div>
        </template>

        <div class="w-full m mx-auto p-4 pb-16 space-y-8">
            <!-- Hero KPIs -->
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                <div class="rounded-xl border border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm p-5">
                    <div class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Total collected
                    </div>
                    <div class="mt-2 text-2xl font-bold text-gray-900 dark:text-white tabular-nums">
                        {{ formatCurrency(hero.total_collected_all_time) }}
                    </div>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        All time · completed payments
                    </p>
                </div>
                <div class="rounded-xl border border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm p-5">
                    <div class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Collected this month
                    </div>
                    <div class="mt-2 text-2xl font-bold text-gray-900 dark:text-white tabular-nums">
                        {{ formatCurrency(hero.collected_this_month) }}
                    </div>
                    <p v-if="momSubtext()" class="mt-2 text-sm font-medium text-primary-600 dark:text-primary-400">
                        {{ momSubtext() }}
                    </p>
                    <p v-else class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        vs {{ formatCurrency(hero.collected_last_month) }} last month
                    </p>
                </div>
                <div class="rounded-xl border border-amber-200/80 dark:border-amber-900/40 bg-amber-50/50 dark:bg-amber-950/20 shadow-sm p-5">
                    <div class="text-sm font-semibold uppercase tracking-wide text-amber-800/90 dark:text-amber-200/80">
                        Outstanding balance
                    </div>
                    <div class="mt-2 text-2xl font-bold text-gray-900 dark:text-white tabular-nums">
                        {{ formatCurrency(hero.outstanding_balance) }}
                    </div>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                        {{ hero.open_receivable_count }} open invoice{{ hero.open_receivable_count === 1 ? '' : 's' }} (sent, viewed, partial)
                    </p>
                </div>
                <div class="rounded-xl border border-red-200/80 dark:border-red-900/40 bg-red-50/40 dark:bg-red-950/20 shadow-sm p-5">
                    <div class="text-sm font-semibold uppercase tracking-wide text-red-800/90 dark:text-red-200/80">
                        Past due balance
                    </div>
                    <div class="mt-2 text-2xl font-bold text-gray-900 dark:text-white tabular-nums">
                        {{ formatCurrency(hero.overdue_amount) }}
                    </div>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                        {{ hero.overdue_invoice_count }} invoice{{ hero.overdue_invoice_count === 1 ? '' : 's' }} past due
                    </p>
                </div>
            </div>

            <!-- Filters -->
            <div class="rounded-xl border border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm p-5 space-y-4">
                <div class="flex flex-wrap items-end gap-4">
                    <div class="min-w-[10rem]">
                        <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Period</label>
                        <select v-model="period" class="input-style w-full text-md">
                            <option v-for="opt in periodOptions" :key="opt.value" :value="opt.value">
                                {{ opt.label }}
                            </option>
                        </select>
                    </div>
                    <div v-if="showQuarterYear" class="w-28">
                        <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Year</label>
                        <input
                            v-model.number="year"
                            type="number"
                            min="2000"
                            max="2100"
                            class="input-style w-full text-md"
                        >
                    </div>
                    <template v-if="showCustomDates">
                        <div>
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">From</label>
                            <input v-model="dateFrom" type="date" class="input-style text-md">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">To</label>
                            <input v-model="dateTo" type="date" class="input-style text-md">
                        </div>
                    </template>
                    <button
                        type="button"
                        class="px-4 py-2 text-md font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg"
                        @click="applyPeriodFilters"
                    >
                        Apply
                    </button>
                </div>
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    Table range: <span class="font-semibold text-gray-700 dark:text-gray-300">{{ periodStats.label }}</span>
                </div>
                <div class="flex flex-wrap items-end gap-3 pt-2 border-t border-gray-100 dark:border-gray-700">
                    <div class="flex-1 min-w-[12rem] max-w-md">
                        <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Search</label>
                        <input
                            v-model="searchInput"
                            type="search"
                            class="input-style w-full text-md"
                            placeholder="Payment #, reference, invoice, customer…"
                            @keydown.enter.prevent="applySearch"
                        >
                    </div>
                    <button
                        type="button"
                        class="px-4 py-2 text-md font-medium text-gray-700 dark:text-gray-200 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg"
                        @click="applySearch"
                    >
                        Search
                    </button>
                </div>
            </div>

            <!-- Period insights -->
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                <div class="rounded-lg border border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-800/80 px-4 py-3">
                    <div class="text-[10px] font-semibold uppercase text-gray-500 dark:text-gray-400">Collected in range</div>
                    <div class="text-xl font-semibold text-gray-900 dark:text-white tabular-nums mt-1">
                        {{ formatCurrency(periodStats.collected) }}
                    </div>
                </div>
                <div class="rounded-lg border border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-800/80 px-4 py-3">
                    <div class="text-[10px] font-semibold uppercase text-gray-500 dark:text-gray-400">Payments in range</div>
                    <div class="text-xl font-semibold text-gray-900 dark:text-white tabular-nums mt-1">
                        {{ periodStats.payment_count }}
                    </div>
                </div>
                <div class="rounded-lg border border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-800/80 px-4 py-3">
                    <div class="text-[10px] font-semibold uppercase text-gray-500 dark:text-gray-400">Avg (completed)</div>
                    <div class="text-xl font-semibold text-gray-900 dark:text-white tabular-nums mt-1">
                        {{ formatCurrency(periodStats.avg_payment) }}
                    </div>
                </div>
                <div class="rounded-lg border border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-800/80 px-4 py-3">
                    <div class="text-[10px] font-semibold uppercase text-gray-500 dark:text-gray-400">Largest in range</div>
                    <div class="text-xl font-semibold text-gray-900 dark:text-white tabular-nums mt-1">
                        {{ formatCurrency(periodStats.largest_payment) }}
                    </div>
                </div>
                <div class="rounded-lg border border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-800/80 px-4 py-3">
                    <div class="text-[10px] font-semibold uppercase text-gray-500 dark:text-gray-400">Largest (30d)</div>
                    <div class="text-xl font-semibold text-gray-900 dark:text-white tabular-nums mt-1">
                        {{ formatCurrency(context.largest_payment_last_30) }}
                    </div>
                </div>
                <div class="rounded-lg border border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-800/80 px-4 py-3">
                    <div class="text-[10px] font-semibold uppercase text-gray-500 dark:text-gray-400">Partially paid</div>
                    <div class="text-xl font-semibold text-gray-900 dark:text-white tabular-nums mt-1">
                        {{ context.partial_invoice_count }}
                    </div>
                    <div class="text-[10px] text-gray-500 dark:text-gray-400 mt-0.5 truncate">
                        {{ formatCurrency(context.partial_outstanding) }} due
                    </div>
                </div>
            </div>

            <div
                v-if="topMethodSubtext()"
                class="text-md text-gray-600 dark:text-gray-400"
            >
                <span class="font-medium text-gray-800 dark:text-gray-200">Top method in range:</span>
                {{ topMethodSubtext() }}
            </div>

            <!-- Table -->
            <div class="rounded-xl border border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between gap-2">
                    <h3 class="text-md font-semibold text-gray-900 dark:text-white">Payments</h3>
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ records.total }} total</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-md">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-700 text-left text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 bg-gray-50/80 dark:bg-gray-900/40">
                                <th class="px-4 py-3">
                                    <Link :href="sortUrl('sequence')" class="hover:text-primary-600">Payment</Link>
                                </th>
                                <th class="px-4 py-3">Invoice</th>
                                <th class="px-4 py-3">
                                    <Link :href="sortUrl('paid_at')" class="hover:text-primary-600">Date</Link>
                                </th>
                                <th class="px-4 py-3">
                                    <Link :href="sortUrl('payment_method_code')" class="hover:text-primary-600">Method</Link>
                                </th>
                                <th class="px-4 py-3">
                                    <Link :href="sortUrl('processor')" class="hover:text-primary-600">Processor</Link>
                                </th>
                                <th class="px-4 py-3 text-right">
                                    <Link :href="sortUrl('amount')" class="hover:text-primary-600">Amount</Link>
                                </th>
                                <th class="px-4 py-3">
                                    <Link :href="sortUrl('status')" class="hover:text-primary-600">Status</Link>
                                </th>
                                <th class="px-4 py-3 w-24" />
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <tr
                                v-for="row in tableRows"
                                :key="row.id"
                                class="hover:bg-gray-50/80 dark:hover:bg-gray-700/30"
                            >
                                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">
                                    {{ row.display_name ?? `PMT-${row.sequence}` }}
                                </td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                    <Link
                                        v-if="row.invoice?.id"
                                        :href="route('invoices.show', row.invoice.id)"
                                        class="text-primary-600 dark:text-primary-400 hover:underline"
                                    >
                                        {{ invoiceDisplayName(row.invoice) }}
                                    </Link>
                                    <span v-else>—</span>
                                </td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300 whitespace-nowrap">
                                    {{ formatDateTime(row.paid_at || row.created_at) }}
                                </td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                    {{ methodLabel(row.payment_method_code) }}
                                </td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300 capitalize">
                                    {{ row.processor || '—' }}
                                </td>
                                <td class="px-4 py-3 text-right font-medium text-gray-900 dark:text-white tabular-nums">
                                    {{ formatCurrency(row.amount) }}
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex px-2 py-0.5 rounded text-sm font-medium capitalize bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200">
                                        {{ row.status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <Link
                                        :href="route('payments.show', row.id)"
                                        class="text-sm font-medium text-primary-600 dark:text-primary-400 hover:underline"
                                    >
                                        View
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="tableRows.length === 0">
                                <td colspan="8" class="px-4 py-12 text-center text-md text-gray-500 dark:text-gray-400">
                                    No payments match your filters.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <nav
                    v-if="records.links?.length > 3"
                    class="px-5 py-3 border-t border-gray-100 dark:border-gray-700 flex flex-col sm:flex-row items-center justify-between gap-3"
                >
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        Showing <span class="font-semibold text-gray-900 dark:text-white">{{ records.from }}</span>
                        to <span class="font-semibold text-gray-900 dark:text-white">{{ records.to }}</span>
                        of <span class="font-semibold text-gray-900 dark:text-white">{{ records.total }}</span>
                    </span>
                    <div class="flex flex-wrap gap-1 justify-center">
                        <template v-for="(link, i) in records.links" :key="i">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                class="flex items-center justify-center px-3 py-1 text-sm rounded-lg border transition-colors"
                                :class="link.active
                                    ? 'bg-primary-600 text-white border-primary-600'
                                    : 'border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700'"
                                v-html="link.label"
                            />
                            <span
                                v-else
                                class="flex items-center justify-center px-3 py-1 text-sm border border-gray-200 dark:border-gray-600 text-gray-400 rounded-lg"
                                v-html="link.label"
                            />
                        </template>
                    </div>
                </nav>
            </div>
        </div>
    </TenantLayout>
</template>
