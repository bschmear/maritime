<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    recordTitle: { type: String, default: 'Financing Report' },
    rows: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
    lenders: { type: Array, default: () => [] },
    statusOptions: { type: Array, default: () => [] },
});

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Reports' },
    { label: props.recordTitle },
]);

// ── Filter state ──────────────────────────────────────────────────────────────
const search   = ref(props.filters?.search ?? '');
const status   = ref(props.filters?.status ?? '');
const vendorId = ref(props.filters?.vendor_id ?? '');
const linked   = ref(props.filters?.linked ?? '');
const dateFrom = ref(props.filters?.date_from ?? '');
const dateTo   = ref(props.filters?.date_to ?? '');
const agingMin = ref(props.filters?.aging_min ?? '');
const agingMax = ref(props.filters?.aging_max ?? '');
const sortBy   = ref(props.filters?.sort_by ?? 'aging_days');
const sortDir  = ref(props.filters?.sort_dir ?? 'desc');

watch(() => props.filters, (f) => {
    search.value   = f?.search ?? '';
    status.value   = f?.status ?? '';
    vendorId.value = f?.vendor_id ?? '';
    linked.value   = f?.linked ?? '';
    dateFrom.value = f?.date_from ?? '';
    dateTo.value   = f?.date_to ?? '';
    agingMin.value = f?.aging_min ?? '';
    agingMax.value = f?.aging_max ?? '';
    sortBy.value   = f?.sort_by ?? 'aging_days';
    sortDir.value  = f?.sort_dir ?? 'desc';
});

function applyFilters() {
    router.get(
        route('reports.financing'),
        {
            search:    search.value || undefined,
            status:    status.value || undefined,
            vendor_id: vendorId.value || undefined,
            linked:    linked.value || undefined,
            date_from: dateFrom.value || undefined,
            date_to:   dateTo.value || undefined,
            aging_min: agingMin.value || undefined,
            aging_max: agingMax.value || undefined,
            sort_by:   sortBy.value,
            sort_dir:  sortDir.value,
        },
        { preserveScroll: true },
    );
}

function resetFilters() {
    search.value = status.value = vendorId.value = linked.value =
        dateFrom.value = dateTo.value = agingMin.value = agingMax.value = '';
    sortBy.value  = 'aging_days';
    sortDir.value = 'desc';
    applyFilters();
}

function setSort(col) {
    if (sortBy.value === col) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value  = col;
        sortDir.value = 'desc';
    }
    applyFilters();
}

// ── Formatting ────────────────────────────────────────────────────────────────
function fmt$(v) {
    const n = Number(v ?? 0);
    if (!Number.isFinite(n)) return '—';
    return n.toLocaleString('en-US', { style: 'currency', currency: 'USD' });
}

function fmtDate(v) {
    if (!v) return '—';
    try { return new Date(v + 'T00:00:00').toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }); }
    catch { return v; }
}

function statusClass(s) {
    const v = String(s || '').toLowerCase();
    if (v === 'active')  return 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200';
    if (v === 'paid_off' || v === 'paid off') return 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200';
    if (v === 'cancelled') return 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300';
    return 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300';
}

const hasActiveFilters = computed(() =>
    search.value || status.value || vendorId.value || linked.value ||
    dateFrom.value || dateTo.value || agingMin.value || agingMax.value,
);

// Column header helper
function thClass(col) {
    const active = sortBy.value === col;
    return [
        'cursor-pointer select-none whitespace-nowrap px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200',
        active ? 'text-gray-900 dark:text-white' : '',
    ].join(' ').trim();
}

function sortIcon(col) {
    if (sortBy.value !== col) return '↕';
    return sortDir.value === 'asc' ? '↑' : '↓';
}
</script>

<template>
    <Head :title="recordTitle" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white">{{ recordTitle }}</h2>
                    <Link
                        :href="route('financings.index')"
                        class="text-sm font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400"
                    >
                        ← Financing dashboard
                    </Link>
                </div>
            </div>
        </template>

        <!-- ── Summary cards ──────────────────────────────────────────────── -->
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Records</p>
                <p class="mt-1 text-2xl font-bold tabular-nums text-gray-900 dark:text-white">{{ summary.total_rows ?? 0 }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Total principal</p>
                <p class="mt-1 text-xl font-bold tabular-nums text-gray-900 dark:text-white">{{ fmt$(summary.total_principal) }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Total balance</p>
                <p class="mt-1 text-xl font-bold tabular-nums text-gray-900 dark:text-white">{{ fmt$(summary.total_balance) }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Total paid off</p>
                <p class="mt-1 text-xl font-bold tabular-nums text-green-700 dark:text-green-400">{{ fmt$(summary.total_paid_off) }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Overall % paid</p>
                <p class="mt-1 text-2xl font-bold tabular-nums text-gray-900 dark:text-white">
                    {{ summary.overall_paid_pct != null ? `${summary.overall_paid_pct}%` : '—' }}
                </p>
            </div>
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 shadow-sm dark:border-amber-700 dark:bg-amber-900/20">
                <p class="text-xs font-medium text-amber-700 dark:text-amber-400">Unlinked units</p>
                <p class="mt-1 text-2xl font-bold tabular-nums text-amber-800 dark:text-amber-200">{{ summary.unlinked_count ?? 0 }}</p>
            </div>
        </div>

        <!-- ── Filters ────────────────────────────────────────────────────── -->
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
                <!-- Search -->
                <div class="xl:col-span-2">
                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Search</label>
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Serial, model, dealer, invoice…"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        @keydown.enter="applyFilters"
                    />
                </div>

                <!-- Status -->
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Status</label>
                    <select
                        v-model="status"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    >
                        <option value="">All statuses</option>
                        <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                    </select>
                </div>

                <!-- Lender -->
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Lender</label>
                    <select
                        v-model="vendorId"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    >
                        <option value="">All lenders</option>
                        <option v-for="l in lenders" :key="l.id" :value="l.id">{{ l.name }}</option>
                    </select>
                </div>

                <!-- Asset unit linked -->
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Unit linked?</label>
                    <select
                        v-model="linked"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    >
                        <option value="">Any</option>
                        <option value="yes">Linked</option>
                        <option value="no">Unlinked</option>
                    </select>
                </div>

                <!-- Date from -->
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Financed from</label>
                    <input
                        v-model="dateFrom"
                        type="date"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    />
                </div>

                <!-- Date to -->
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Financed to</label>
                    <input
                        v-model="dateTo"
                        type="date"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    />
                </div>

                <!-- Aging min -->
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Aging ≥ (days)</label>
                    <input
                        v-model="agingMin"
                        type="number"
                        min="0"
                        placeholder="e.g. 90"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    />
                </div>

                <!-- Aging max -->
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Aging ≤ (days)</label>
                    <input
                        v-model="agingMax"
                        type="number"
                        min="0"
                        placeholder="e.g. 180"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    />
                </div>
            </div>

            <div class="mt-3 flex items-center gap-2">
                <button
                    type="button"
                    class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700"
                    @click="applyFilters"
                >
                    Apply filters
                </button>
                <button
                    v-if="hasActiveFilters"
                    type="button"
                    class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300"
                    @click="resetFilters"
                >
                    Clear
                </button>
                <span class="ml-auto text-xs text-gray-400 dark:text-gray-500">
                    {{ rows.length }} row{{ rows.length === 1 ? '' : 's' }}
                </span>
            </div>
        </div>

        <!-- ── Table ──────────────────────────────────────────────────────── -->
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div v-if="rows.length" class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900/40">
                        <tr>
                            <th :class="thClass('')" class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide">Financing</th>
                            <th :class="thClass('model_year')" @click="setSort('model_year')">
                                Model <span class="opacity-50">{{ sortIcon('model_year') }}</span>
                            </th>
                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Serial / VIN</th>
                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Dealer</th>
                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Lender</th>
                            <th :class="thClass('financed_at')" @click="setSort('financed_at')">
                                Financed <span class="opacity-50">{{ sortIcon('financed_at') }}</span>
                            </th>
                            <th :class="thClass('principal_amount')" class="text-right" @click="setSort('principal_amount')">
                                Principal <span class="opacity-50">{{ sortIcon('principal_amount') }}</span>
                            </th>
                            <th :class="thClass('current_balance')" class="text-right" @click="setSort('current_balance')">
                                Balance <span class="opacity-50">{{ sortIcon('current_balance') }}</span>
                            </th>
                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Paid off</th>
                            <th :class="thClass('aging_days')" class="text-right" @click="setSort('aging_days')">
                                Aging <span class="opacity-50">{{ sortIcon('aging_days') }}</span>
                            </th>
                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</th>
                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Unit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50 dark:divide-gray-700/60">
                        <tr
                            v-for="row in rows"
                            :key="row.id"
                            class="hover:bg-gray-50 dark:hover:bg-gray-700/30"
                        >
                            <!-- Financing link -->
                            <td class="px-3 py-2.5">
                                <Link
                                    :href="route('financings.show', row.id)"
                                    class="font-medium text-primary-600 hover:underline dark:text-primary-400"
                                >
                                    {{ row.display_name }}
                                </Link>
                                <p v-if="row.lender_invoice_number" class="text-xs text-gray-400 dark:text-gray-500">
                                    Inv {{ row.lender_invoice_number }}
                                </p>
                            </td>

                            <!-- Model year + number -->
                            <td class="px-3 py-2.5 text-gray-700 dark:text-gray-300">
                                <span v-if="row.model_year" class="font-medium">{{ row.model_year }}</span>
                                <span v-if="row.model_year && row.model_number"> · </span>
                                {{ row.model_number || (row.model_year ? '' : '—') }}
                            </td>

                            <!-- Serial/VIN -->
                            <td class="px-3 py-2.5 font-mono text-xs text-gray-700 dark:text-gray-300">
                                {{ row.serial_vin || '—' }}
                            </td>

                            <!-- Dealer -->
                            <td class="px-3 py-2.5 text-gray-700 dark:text-gray-300">{{ row.dealer_name || '—' }}</td>

                            <!-- Lender -->
                            <td class="px-3 py-2.5 text-gray-700 dark:text-gray-300">{{ row.vendor_name || '—' }}</td>

                            <!-- Financed at -->
                            <td class="px-3 py-2.5 tabular-nums text-gray-700 dark:text-gray-300">
                                {{ fmtDate(row.financed_at) }}
                            </td>

                            <!-- Principal -->
                            <td class="px-3 py-2.5 text-right tabular-nums text-gray-700 dark:text-gray-300">
                                {{ fmt$(row.principal_amount) }}
                            </td>

                            <!-- Balance -->
                            <td class="px-3 py-2.5 text-right tabular-nums font-medium text-gray-900 dark:text-white">
                                {{ fmt$(row.current_balance) }}
                            </td>

                            <!-- Paid off % + bar -->
                            <td class="px-3 py-2.5">
                                <template v-if="row.paid_off_pct != null">
                                    <div class="flex items-center gap-2">
                                        <div class="h-1.5 w-16 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                            <div class="h-full rounded-full bg-green-500" :style="{ width: `${row.paid_off_pct}%` }" />
                                        </div>
                                        <span class="tabular-nums text-xs text-gray-500">{{ row.paid_off_pct }}%</span>
                                    </div>
                                </template>
                                <span v-else class="text-gray-400">—</span>
                            </td>

                            <!-- Aging -->
                            <td class="px-3 py-2.5 text-right tabular-nums">
                                <span
                                    :class="[
                                        'font-medium',
                                        (row.aging_days ?? 0) >= 180 ? 'text-red-600 dark:text-red-400'
                                            : (row.aging_days ?? 0) >= 90 ? 'text-amber-600 dark:text-amber-400'
                                            : 'text-gray-700 dark:text-gray-300',
                                    ]"
                                >
                                    {{ row.aging_days ?? '—' }}
                                </span>
                            </td>

                            <!-- Status badges -->
                            <td class="px-3 py-2.5">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold" :class="statusClass(row.status)">
                                    {{ row.status || '—' }}
                                </span>
                                <span v-if="row.lender_status" class="mt-0.5 block text-xs text-gray-400 dark:text-gray-500">
                                    {{ row.lender_status }}
                                </span>
                            </td>

                            <!-- Unit linked -->
                            <td class="px-3 py-2.5">
                                <template v-if="row.asset_unit_id">
                                    <Link
                                        :href="route('assetunits.show', row.asset_unit_id)"
                                        class="text-xs text-primary-600 hover:underline dark:text-primary-400"
                                    >
                                        View unit
                                    </Link>
                                </template>
                                <template v-else>
                                    <Link
                                        :href="`${route('assetunits.create')}?link_financing_id=${row.id}&return_url=${encodeURIComponent(route('reports.financing'))}&serial_number=${encodeURIComponent(row.serial_vin || '')}`"
                                        class="text-xs font-medium text-amber-600 hover:underline dark:text-amber-400"
                                    >
                                        Create unit
                                    </Link>
                                </template>
                            </td>
                        </tr>
                    </tbody>
                    <!-- Totals footer -->
                    <tfoot class="border-t-2 border-gray-200 bg-gray-50 dark:border-gray-600 dark:bg-gray-900/40">
                        <tr>
                            <td colspan="6" class="px-3 py-2.5 text-xs font-semibold text-gray-500 dark:text-gray-400">
                                Totals ({{ rows.length }} records)
                            </td>
                            <td class="px-3 py-2.5 text-right text-sm font-bold tabular-nums text-gray-900 dark:text-white">
                                {{ fmt$(summary.total_principal) }}
                            </td>
                            <td class="px-3 py-2.5 text-right text-sm font-bold tabular-nums text-gray-900 dark:text-white">
                                {{ fmt$(summary.total_balance) }}
                            </td>
                            <td class="px-3 py-2.5 text-sm font-bold text-green-700 dark:text-green-400">
                                {{ fmt$(summary.total_paid_off) }}
                                <span v-if="summary.overall_paid_pct != null" class="ml-1 font-normal text-gray-400">({{ summary.overall_paid_pct }}%)</span>
                            </td>
                            <td colspan="3" />
                        </tr>
                    </tfoot>
                </table>
            </div>
            <p v-else class="px-5 py-16 text-center text-sm text-gray-400 dark:text-gray-500">
                No financing records match the current filters.
            </p>
        </div>
    </TenantLayout>
</template>
