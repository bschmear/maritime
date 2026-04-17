<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import ReportSwitcher from '@/Components/Tenant/Reports/ReportSwitcher.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

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

function formatCurrency(value) {
    const n = Number(value ?? 0);
    if (Number.isNaN(n)) return '$0.00';
    return n.toLocaleString('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 2 });
}

function applyFilters() {
    router.get(
        route('reports.pnl'),
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
                    <ReportSwitcher current-route-name="reports.pnl" />
                </div>
            </div>
        </template>

        <div class="mx-auto w-full space-y-6 p-4 pb-16">
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

            <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Income</h3>
                <dl class="space-y-2">
                    <div class="flex items-center justify-between text-md text-gray-700 dark:text-gray-300">
                        <dt>Boat Sales</dt>
                        <dd class="tabular-nums">{{ formatCurrency(report.income?.boat_sales) }}</dd>
                    </div>
                    <div class="flex items-center justify-between text-md text-gray-700 dark:text-gray-300">
                        <dt>Service Revenue</dt>
                        <dd class="tabular-nums">{{ formatCurrency(report.income?.service_revenue) }}</dd>
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
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Cost of Goods Sold</h3>
                <dl class="space-y-2">
                    <div class="flex items-center justify-between text-md text-gray-700 dark:text-gray-300">
                        <dt>Boat Cost</dt>
                        <dd class="tabular-nums">{{ formatCurrency(report.cogs?.boat_cost) }}</dd>
                    </div>
                    <div class="flex items-center justify-between text-md text-gray-700 dark:text-gray-300">
                        <dt>Service Cost</dt>
                        <dd class="tabular-nums">{{ formatCurrency(report.cogs?.service_cost) }}</dd>
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
        </div>
    </TenantLayout>
</template>

