<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import ActionCenterWidget from '@/Components/Tenant/Dashboard/ActionCenterWidget.vue';
import RevenueSnapshotWidget from '@/Components/Tenant/Dashboard/RevenueSnapshotWidget.vue';
import RiskPanelWidget from '@/Components/Tenant/Dashboard/RiskPanelWidget.vue';
import OperationsWidget from '@/Components/Tenant/Dashboard/OperationsWidget.vue';
import ActivityFeedWidget from '@/Components/Tenant/Dashboard/ActivityFeedWidget.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    account: {
        type: Object,
        default: null,
    },
    dashboard: {
        type: Object,
        required: true,
    },
});

const page = usePage();

const userName = computed(() => page.props.auth?.user?.name ?? 'there');
const now = new Date();
const currentHour = now.getHours();
const greeting = computed(() => {
    if (currentHour < 12) return 'Good morning';
    if (currentHour < 18) return 'Good afternoon';
    return 'Good evening';
});
const todayLabel = computed(() =>
    now.toLocaleDateString(undefined, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    })
);

const hero = computed(() => props.dashboard?.revenue?.paymentDashboard?.hero ?? {});
const pipelineValue = computed(() => props.dashboard?.revenue?.pipeline_value ?? 0);

function formatCurrency(value) {
    if (value == null || value === '') return '—';
    const n = Number(value);
    if (Number.isNaN(n)) return '—';
    return new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD' }).format(n);
}
</script>

<template>
    <Head title="Dashboard" />

    <TenantLayout>
        <template #header>
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        {{ greeting }}, {{ userName }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Here is what needs attention today.</p>
                </div>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ todayLabel }}</p>
            </div>
        </template>

        <div class="mx-auto space-y-6 px-4 py-6 w-full">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Collected (MTD)
                    </div>
                    <div class="mt-2 text-2xl font-bold text-gray-900 dark:text-white tabular-nums">
                        {{ formatCurrency(hero.collected_this_month) }}
                    </div>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        <span v-if="hero.collected_mom_pct != null">
                            {{ hero.collected_mom_pct > 0 ? '+' : '' }}{{ hero.collected_mom_pct }}% vs last month
                        </span>
                        <span v-else>Month-over-month unavailable</span>
                    </p>
                </div>
                <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Outstanding
                    </div>
                    <div class="mt-2 text-2xl font-bold text-gray-900 dark:text-white tabular-nums">
                        {{ formatCurrency(hero.outstanding_balance) }}
                    </div>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        {{ hero.open_receivable_count ?? 0 }} open invoice{{ (hero.open_receivable_count ?? 0) === 1 ? '' : 's' }}
                    </p>
                </div>
                <div class="rounded-xl border border-amber-200/80 bg-amber-50/50 p-5 shadow-sm dark:border-amber-900/40 dark:bg-amber-950/20">
                    <div class="text-sm font-semibold uppercase tracking-wide text-amber-800/90 dark:text-amber-200/80">
                        Past due
                    </div>
                    <div class="mt-2 text-2xl font-bold text-gray-900 dark:text-white tabular-nums">
                        {{ formatCurrency(hero.overdue_amount) }}
                    </div>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                        {{ hero.overdue_invoice_count ?? 0 }} overdue invoice{{ (hero.overdue_invoice_count ?? 0) === 1 ? '' : 's' }}
                    </p>
                </div>
                <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Open pipeline
                    </div>
                    <div class="mt-2 text-2xl font-bold text-gray-900 dark:text-white tabular-nums">
                        {{ formatCurrency(pipelineValue) }}
                    </div>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Estimated active opportunity value</p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="lg:col-span-2">
                    <ActionCenterWidget :action-center="dashboard.actionCenter" />
                </div>
                <div class="lg:col-span-1">
                    <RevenueSnapshotWidget :revenue="dashboard.revenue" />
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <RiskPanelWidget :risk="dashboard.risk" />
                <OperationsWidget :operations="dashboard.operations" />
            </div>

            <ActivityFeedWidget :activity="dashboard.activity" />
        </div>
    </TenantLayout>
</template>
