<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Table from '@/Components/Tenant/Table.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import ApexLineChart from '@/Components/Charts/ApexLineChart.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    records: { type: Object, required: true },
    schema: { type: Object, default: null },
    formSchema: { type: Object, default: null },
    fieldsSchema: { type: Object, default: () => ({}) },
    enumOptions: { type: Object, default: () => ({}) },
    recordType: { type: String, default: 'financings' },
    recordTitle: { type: String, default: 'Financing' },
    pluralTitle: { type: String, default: 'Financings' },
    stats: { type: Object, default: () => ({}) },
    statContext: { type: Object, default: () => ({}) },
    charts: { type: Object, default: () => ({}) },
    activeFinancings: { type: Array, default: () => [] },
});

const currentView = ref(localStorage.getItem('financing-index-view') || 'dashboard');
const agingBasis = ref(localStorage.getItem('financing-aging-basis') || 'aging_days');
// Which row's financing-data tooltip is open (null = none)
const expandedRowId = ref(null);

watch(currentView, (v) => localStorage.setItem('financing-index-view', v));
watch(agingBasis, (v) => localStorage.setItem('financing-aging-basis', v));

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: props.pluralTitle },
]);

const statCardDefs = computed(() => {
    const raw = props.schema?.stats;
    return Array.isArray(raw) ? raw.filter((s) => s.hidden !== true) : [];
});

const statNumericValue = (key) => {
    const n = Number(props.stats?.[key]);
    return Number.isFinite(n) ? n : 0;
};

const statBadgeClass = (color) => {
    const c = (color || 'gray').toString().toLowerCase();
    const m = {
        green: 'rounded-sm bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900 dark:text-green-300',
        red: 'rounded-sm bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800 dark:bg-red-900 dark:text-red-300',
        amber: 'rounded-sm bg-yellow-100 px-2.5 py-0.5 text-xs font-medium text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
        gray: 'rounded-sm bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800 dark:bg-gray-700 dark:text-gray-300',
        blue: 'rounded-sm bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900 dark:text-blue-300',
        teal: 'rounded-sm bg-teal-100 px-2.5 py-0.5 text-xs font-medium text-teal-800 dark:bg-teal-900 dark:text-teal-300',
        purple: 'rounded-sm bg-purple-100 px-2.5 py-0.5 text-xs font-medium text-purple-800 dark:bg-purple-900 dark:text-purple-300',
    };
    return m[c] ?? m.gray;
};

const trendChart = computed(() => props.charts?.imported_trend ?? { categories: [], series: [], colors: [] });
const totalCurrentBalance = computed(() => props.charts?.total_current_balance ?? 0);
const hasTrendChart = computed(() => trendChart.value.categories?.length > 0);

// ── Aging calculation ──────────────────────────────────────────────────────────
const today = new Date();
today.setHours(0, 0, 0, 0);

function daysBetween(dateStr) {
    if (!dateStr) return null;
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return null;
    d.setHours(0, 0, 0, 0);
    return Math.max(0, Math.round((today - d) / 86400000));
}

function computedAgingDays(row) {
    if (agingBasis.value === 'financed_at') {
        return daysBetween(row.financed_at) ?? row.aging_days ?? null;
    }
    if (agingBasis.value === 'interest_start_date') {
        return daysBetween(row.interest_start_date) ?? row.aging_days ?? null;
    }
    // 'aging_days' = use lender-reported value
    return row.aging_days ?? null;
}

const agingBasisOptions = [
    { value: 'aging_days', label: 'Lender report' },
    { value: 'financed_at', label: 'Invoice date' },
    { value: 'interest_start_date', label: 'Interest start' },
];

// ── Balance helpers ────────────────────────────────────────────────────────────
const formatCurrency = (value) => {
    const n = Number(value);
    if (!Number.isFinite(n)) return '—';
    return `$${n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
};

function paidOffPercent(row) {
    const principal = Number(row.principal_amount);
    const balance = Number(row.current_balance);
    if (!principal || principal <= 0) return null;
    const pct = Math.max(0, Math.min(100, ((principal - balance) / principal) * 100));
    return Math.round(pct);
}

function paidOffAmount(row) {
    const principal = Number(row.principal_amount);
    const balance = Number(row.current_balance);
    if (!Number.isFinite(principal) || !Number.isFinite(balance)) return null;
    return principal - balance;
}

// ── Nav helpers ────────────────────────────────────────────────────────────────
function financingShowHref(id) {
    return route(`${props.recordType}.show`, id);
}

function switchToTable() {
    currentView.value = 'table';
}

function createAssetUnitHref(row) {
    const params = new URLSearchParams();
    if (row.serial_vin) params.set('serial_number', row.serial_vin);
    params.set('return_url', route('financings.index'));
    params.set('link_financing_id', String(row.id));
    return `${route('assetunits.create')}?${params.toString()}`;
}

function toggleDataPanel(id) {
    expandedRowId.value = expandedRowId.value === id ? null : id;
}

// ── Stat card helpers ──────────────────────────────────────────────────────────
function statDescription(st) {
    const ctx = props.statContext ?? {};
    if (st.key === 'paid_off') {
        const days = ctx.paid_off_window_days ?? 30;
        return `Paid off in the last ${days} days`;
    }
    if (st.key === 'high_aging') {
        if (ctx.has_days_alert_threshold && ctx.days_alert_threshold != null) {
            return `Aging at or above ${ctx.days_alert_threshold} days`;
        }
        return 'Set a days threshold in settings';
    }
    return st.description ?? '';
}
</script>

<template>
    <Head :title="pluralTitle" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex flex-wrap items-center gap-3">
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">{{ pluralTitle }}</h2>
                        <div class="flex overflow-hidden rounded-lg border border-gray-300 dark:border-gray-600">
                            <button
                                type="button"
                                :class="['px-3 py-1.5 text-sm font-medium transition-colors', currentView === 'dashboard' ? 'bg-primary-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600']"
                                @click="currentView = 'dashboard'"
                            >
                                Dashboard
                            </button>
                            <button
                                type="button"
                                :class="['border-l border-gray-300 px-3 py-1.5 text-sm font-medium transition-colors dark:border-gray-600', currentView === 'table' ? 'bg-primary-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600']"
                                @click="currentView = 'table'"
                            >
                                Table
                            </button>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <Link
                            :href="route('financings.import')"
                            class="inline-flex rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200"
                        >
                            Import data
                        </Link>
                        <button
                            type="button"
                            class="inline-flex rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700"
                            @click="router.visit(route('financings.create'))"
                        >
                            New financing
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <!-- ── Dashboard ──────────────────────────────────────────────────── -->
        <div v-if="currentView === 'dashboard'" class="space-y-6">

            <!-- Stat cards -->
            <div v-if="statCardDefs.length" class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <div
                    v-for="st in statCardDefs"
                    :key="st.key"
                    class="space-y-3 rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800"
                >
                    <span class="inline-block" :class="statBadgeClass(st.color)">
                        {{ st.badge_label ?? st.label ?? st.key }}
                    </span>
                    <h2 class="text-2xl font-bold leading-none text-gray-900 tabular-nums dark:text-white">
                        {{ statNumericValue(st.key) }}
                    </h2>
                    <p v-if="statDescription(st)" class="text-sm text-gray-500 dark:text-gray-400">
                        {{ statDescription(st) }}
                    </p>
                </div>
            </div>

            <!-- Total active balance + trend chart -->
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <div class="rounded-xl border border-primary-200 bg-primary-50 p-5 dark:border-primary-800 dark:bg-primary-950/30">
                    <p class="text-sm font-medium text-primary-800 dark:text-primary-200">Total active balance</p>
                    <p class="mt-1 text-3xl font-bold tabular-nums text-primary-950 dark:text-primary-100">
                        {{ formatCurrency(totalCurrentBalance) }}
                    </p>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:col-span-2">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        New financings — last 12 weeks
                    </h3>
                    <div class="mt-2">
                        <ApexLineChart
                            v-if="hasTrendChart"
                            :categories="trendChart.categories"
                            :series="trendChart.series"
                            :colors="trendChart.colors"
                            :height="120"
                            value-format="number"
                        />
                        <p v-else class="py-8 text-center text-sm text-gray-400 dark:text-gray-500">
                            No financings created in this period yet.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Active financings table -->
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-4 dark:border-gray-700">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Active financings</h3>
                        <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">Sorted by aging — highest first</p>
                    </div>

                    <!-- Aging basis toggle -->
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Aging from:</span>
                        <div class="flex overflow-hidden rounded-lg border border-gray-300 dark:border-gray-600">
                            <button
                                v-for="opt in agingBasisOptions"
                                :key="opt.value"
                                type="button"
                                :class="[
                                    'border-l border-gray-300 px-3 py-1.5 text-xs font-medium transition-colors first:border-l-0 dark:border-gray-600',
                                    agingBasis === opt.value
                                        ? 'bg-primary-600 text-white'
                                        : 'bg-white text-gray-600 hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600',
                                ]"
                                @click="agingBasis = opt.value"
                            >
                                {{ opt.label }}
                            </button>
                        </div>
                        <button
                            type="button"
                            class="text-sm font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400"
                            @click="switchToTable"
                        >
                            View all in table →
                        </button>
                    </div>
                </div>

                <div v-if="activeFinancings.length" class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/40">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Financing / Model</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Serial/VIN</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Lender</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Principal</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Balance</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Paid off</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Aging (days)</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-gray-700/60">
                            <template v-for="row in activeFinancings" :key="row.id">
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                    <!-- Financing name + invoice -->
                                    <td class="px-4 py-3">
                                        <Link
                                            :href="financingShowHref(row.id)"
                                            class="font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400"
                                        >
                                            {{ row.display_name }}
                                        </Link>
                                        <p v-if="row.model_year || row.model_number" class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ [row.model_year, row.model_number].filter(Boolean).join(' · ') }}
                                        </p>
                                        <p v-if="row.lender_invoice_number" class="text-xs text-gray-400 dark:text-gray-500">
                                            Inv {{ row.lender_invoice_number }}
                                        </p>
                                    </td>

                                    <!-- Serial/VIN — or asset unit link -->
                                    <td class="px-4 py-3">
                                        <template v-if="row.asset_unit">
                                            <Link
                                                :href="route('assetunits.show', row.asset_unit.id)"
                                                class="text-primary-600 hover:underline dark:text-primary-400"
                                            >
                                                {{ row.serial_vin || row.asset_unit.display_name }}
                                            </Link>
                                        </template>
                                        <template v-else>
                                            <span class="text-gray-500 dark:text-gray-400">{{ row.serial_vin || '—' }}</span>
                                        </template>
                                    </td>

                                    <!-- Lender -->
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                        {{ row.vendor?.display_name ?? '—' }}
                                    </td>

                                    <!-- Principal -->
                                    <td class="px-4 py-3 text-right tabular-nums text-gray-700 dark:text-gray-300">
                                        {{ formatCurrency(row.principal_amount) }}
                                    </td>

                                    <!-- Current balance -->
                                    <td class="px-4 py-3 text-right tabular-nums font-medium text-gray-900 dark:text-white">
                                        {{ formatCurrency(row.current_balance) }}
                                    </td>

                                    <!-- Paid-off progress -->
                                    <td class="px-4 py-3">
                                        <template v-if="paidOffPercent(row) !== null">
                                            <div class="flex items-center gap-2">
                                                <div class="h-1.5 w-20 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                                    <div
                                                        class="h-full rounded-full bg-green-500"
                                                        :style="{ width: `${paidOffPercent(row)}%` }"
                                                    />
                                                </div>
                                                <span class="tabular-nums text-xs text-gray-500 dark:text-gray-400">
                                                    {{ paidOffPercent(row) }}%
                                                </span>
                                            </div>
                                            <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500 tabular-nums">
                                                {{ formatCurrency(paidOffAmount(row)) }} paid
                                            </p>
                                        </template>
                                        <span v-else class="text-gray-400">—</span>
                                    </td>

                                    <!-- Aging -->
                                    <td class="px-4 py-3 text-right tabular-nums text-gray-700 dark:text-gray-300">
                                        {{ computedAgingDays(row) ?? '—' }}
                                    </td>

                                    <!-- Lender status -->
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                        {{ row.lender_status || '—' }}
                                    </td>

                                    <!-- Actions -->
                                    <td class="px-4 py-3 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <!-- Create asset unit if no unit linked -->
                                            <template v-if="!row.asset_unit">
                                                <button
                                                    type="button"
                                                    class="rounded border border-amber-300 bg-amber-50 px-2 py-1 text-xs font-medium text-amber-800 hover:bg-amber-100 dark:border-amber-700 dark:bg-amber-900/30 dark:text-amber-200"
                                                    :title="'Show financing data to copy into a new asset unit'"
                                                    @click="toggleDataPanel(row.id)"
                                                >
                                                    {{ expandedRowId === row.id ? 'Hide data' : 'Show data' }}
                                                </button>
                                                <Link
                                                    :href="createAssetUnitHref(row)"
                                                    class="rounded border border-gray-300 bg-white px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                                                >
                                                    Create unit
                                                </Link>
                                            </template>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Expandable data panel for unlinked rows -->
                                <tr v-if="expandedRowId === row.id && !row.asset_unit" class="bg-amber-50 dark:bg-amber-900/10">
                                    <td colspan="9" class="px-4 py-3">
                                        <p class="mb-2 text-xs font-semibold text-amber-800 dark:text-amber-200">
                                            Financing data — copy into the asset unit form
                                        </p>
                                        <dl class="flex flex-wrap gap-x-6 gap-y-1 text-xs">
                                            <div v-if="row.serial_vin" class="flex gap-1">
                                                <dt class="font-medium text-gray-600 dark:text-gray-400">Serial/VIN:</dt>
                                                <dd class="select-all font-mono text-gray-900 dark:text-white">{{ row.serial_vin }}</dd>
                                            </div>
                                            <div v-if="row.model_year" class="flex gap-1">
                                                <dt class="font-medium text-gray-600 dark:text-gray-400">Year:</dt>
                                                <dd class="select-all text-gray-900 dark:text-white">{{ row.model_year }}</dd>
                                            </div>
                                            <div v-if="row.model_number" class="flex gap-1">
                                                <dt class="font-medium text-gray-600 dark:text-gray-400">Model:</dt>
                                                <dd class="select-all text-gray-900 dark:text-white">{{ row.model_number }}</dd>
                                            </div>
                                            <div v-if="row.supplier_name" class="flex gap-1">
                                                <dt class="font-medium text-gray-600 dark:text-gray-400">Supplier:</dt>
                                                <dd class="select-all text-gray-900 dark:text-white">{{ row.supplier_name }}</dd>
                                            </div>
                                            <div v-if="row.lender_invoice_number" class="flex gap-1">
                                                <dt class="font-medium text-gray-600 dark:text-gray-400">Invoice #:</dt>
                                                <dd class="select-all font-mono text-gray-900 dark:text-white">{{ row.lender_invoice_number }}</dd>
                                            </div>
                                        </dl>
                                        <div class="mt-3">
                                            <Link
                                                :href="createAssetUnitHref(row)"
                                                class="inline-flex rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-primary-700"
                                            >
                                                Create asset unit →
                                            </Link>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <p v-else class="px-5 py-12 text-center text-sm text-gray-400 dark:text-gray-500">
                    No active financings right now.
                </p>
            </div>
        </div>

        <!-- ── Table view ──────────────────────────────────────────────────── -->
        <Table
            v-else
            :records="records"
            :schema="schema"
            :form-schema="formSchema"
            :fields-schema="fieldsSchema"
            :enum-options="enumOptions"
            :record-type="recordType"
            :record-title="recordTitle"
            :plural-title="pluralTitle"
            :stats="stats"
            hide-header
        />
    </TenantLayout>
</template>
