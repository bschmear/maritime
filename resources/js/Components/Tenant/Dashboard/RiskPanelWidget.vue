<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import DashboardWidgetShell from '@/Components/Tenant/Dashboard/DashboardWidgetShell.vue';

const props = defineProps({
    risk: {
        type: Object,
        default: () => ({}),
    },
});

const overdueInvoices = computed(() => props.risk?.overdueInvoices ?? []);
const stalled = computed(() => props.risk?.stalledOpportunities ?? []);
const estimates = computed(() => props.risk?.expiringEstimates ?? []);
const staleTickets = computed(() => props.risk?.staleServiceTickets ?? []);
const overdueWo = computed(() => props.risk?.overdueWorkOrders ?? []);
const counts = computed(() => props.risk?.counts ?? {});

const isEmpty = computed(
    () =>
        overdueInvoices.value.length === 0 &&
        stalled.value.length === 0 &&
        estimates.value.length === 0 &&
        staleTickets.value.length === 0 &&
        overdueWo.value.length === 0
);

function formatCurrency(value) {
    if (value == null || value === '') return '—';
    const n = Number(value);
    if (Number.isNaN(n)) return '—';
    return new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD' }).format(n);
}

function formatDate(iso) {
    if (!iso) return '';
    try {
        return new Date(iso).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
    } catch {
        return iso;
    }
}
</script>

<template>
    <DashboardWidgetShell
        title="Attention / risk"
        :count="overdueInvoices.length + stalled.length + estimates.length + staleTickets.length + overdueWo.length"
        :more-href="route('invoices.index')"
        more-label="Invoices"
        :empty="isEmpty"
        empty-message="No overdue receivables, stalled deals, or expiring quotes in the current windows."
    >
        <div class="space-y-4">
            <div v-if="overdueInvoices.length">
                <div class="mb-1 flex items-center gap-2">
                    <p class="text-xs font-semibold uppercase tracking-wide text-amber-800 dark:text-amber-200">
                        Overdue invoices
                    </p>
                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-800 dark:bg-amber-900/40 dark:text-amber-200">
                        {{ counts.overdue_invoices ?? overdueInvoices.length }}
                    </span>
                </div>
                <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                    <li v-for="row in overdueInvoices" :key="'i-' + row.id" class="py-2 first:pt-0">
                        <component
                            :is="row.href ? Link : 'span'"
                            :href="row.href || undefined"
                            class="text-sm font-medium text-gray-900 hover:text-primary-600 dark:text-white dark:hover:text-primary-400"
                        >
                            {{ row.label }} · {{ formatCurrency(row.amount_due) }}
                        </component>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Due {{ formatDate(row.due_at) }}
                            <span v-if="row.subtitle"> · {{ row.subtitle }}</span>
                        </p>
                    </li>
                </ul>
            </div>
            <div v-if="stalled.length">
                <div class="mb-1 flex items-center gap-2">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">
                        Stalled opportunities
                    </p>
                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-semibold text-gray-700 dark:bg-gray-700 dark:text-gray-200">
                        {{ counts.stalled_opportunities ?? stalled.length }}
                    </span>
                </div>
                <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                    <li v-for="row in stalled" :key="'o-' + row.id" class="py-2 first:pt-0">
                        <component
                            :is="row.href ? Link : 'span'"
                            :href="row.href || undefined"
                            class="text-sm font-medium text-gray-900 hover:text-primary-600 dark:text-white dark:hover:text-primary-400"
                        >
                            {{ row.label }}
                            <span v-if="row.estimated_value != null" class="text-gray-500">
                                · {{ formatCurrency(row.estimated_value) }}</span
                            >
                        </component>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ row.subtitle }}</p>
                    </li>
                </ul>
            </div>
            <div v-if="estimates.length">
                <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">
                    Expiring estimates
                </p>
                <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                    <li v-for="row in estimates" :key="'e-' + row.id" class="py-2 first:pt-0">
                        <component
                            :is="row.href ? Link : 'span'"
                            :href="row.href || undefined"
                            class="text-sm font-medium text-gray-900 hover:text-primary-600 dark:text-white dark:hover:text-primary-400"
                        >
                            {{ row.label }}
                        </component>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Expires {{ row.expiration_date }}<span v-if="row.subtitle"> · {{ row.subtitle }}</span>
                        </p>
                    </li>
                </ul>
            </div>
            <div v-if="staleTickets.length">
                <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">
                    Stale service tickets
                </p>
                <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                    <li v-for="row in staleTickets" :key="'s-' + row.id" class="py-2 first:pt-0">
                        <component
                            :is="row.href ? Link : 'span'"
                            :href="row.href || undefined"
                            class="text-sm font-medium text-gray-900 hover:text-primary-600 dark:text-white dark:hover:text-primary-400"
                        >
                            {{ row.label }}
                        </component>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Updated {{ formatDate(row.updated_at) }}</p>
                    </li>
                </ul>
            </div>
            <div v-if="overdueWo.length">
                <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">
                    Overdue work orders
                </p>
                <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                    <li v-for="row in overdueWo" :key="'w-' + row.id" class="py-2 first:pt-0">
                        <component
                            :is="row.href ? Link : 'span'"
                            :href="row.href || undefined"
                            class="text-sm font-medium text-gray-900 hover:text-primary-600 dark:text-white dark:hover:text-primary-400"
                        >
                            {{ row.label }}
                        </component>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Due {{ formatDate(row.due_at) }}</p>
                    </li>
                </ul>
            </div>
        </div>
    </DashboardWidgetShell>
</template>
