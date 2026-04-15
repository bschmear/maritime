<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import DashboardWidgetShell from '@/Components/Tenant/Dashboard/DashboardWidgetShell.vue';

const props = defineProps({
    operations: {
        type: Object,
        default: () => ({}),
    },
});

const openTickets = computed(() => props.operations?.open_service_ticket_count ?? 0);
const openWo = computed(() => props.operations?.open_work_order_count ?? 0);
const deliveries = computed(() => props.operations?.deliveriesThisWeek ?? []);
const links = computed(() => props.operations?.links ?? {});

function formatDate(iso) {
    if (!iso) return '';
    try {
        return new Date(iso).toLocaleString(undefined, {
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
        });
    } catch {
        return iso;
    }
}
</script>

<template>
    <DashboardWidgetShell title="Operations" :empty="false">
        <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
            <Link
                v-if="links.service_tickets"
                :href="links.service_tickets"
                class="rounded-lg border border-gray-100 bg-gray-50 px-4 py-3 hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-900/40 dark:text-white dark:hover:bg-gray-800"
            >
                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Open service tickets</p>
                <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white tabular-nums">{{ openTickets }}</p>
            </Link>
            <div
                v-else
                class="rounded-lg border border-gray-100 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-300"
            >
                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Open service tickets</p>
                <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white tabular-nums">{{ openTickets }}</p>
            </div>
            <Link
                v-if="links.work_orders"
                :href="links.work_orders"
                class="rounded-lg border border-gray-100 bg-gray-50 px-4 py-3 hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-900/40 dark:text-white dark:hover:bg-gray-800"
            >
                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Open work orders</p>
                <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white tabular-nums">{{ openWo }}</p>
            </Link>
            <div
                v-else
                class="rounded-lg border border-gray-100 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-300"
            >
                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Open work orders</p>
                <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white tabular-nums">{{ openWo }}</p>
            </div>
        </div>
        <div v-if="deliveries.length" class="grow">
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                Upcoming deliveries (next 7 days)
            </p>
            <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                <li v-for="row in deliveries" :key="'dw-' + row.id" class="py-2 first:pt-0">
                    <component
                        :is="row.href ? Link : 'span'"
                        :href="row.href || undefined"
                        class="text-md font-medium text-gray-900 hover:text-primary-600 dark:text-white dark:hover:text-primary-400"
                    >
                        {{ row.label }}
                    </component>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ formatDate(row.scheduled_at) }}</p>
                </li>
            </ul>
        </div>
        <div v-else class="flex flex-col items-center justify-center py-6 text-center grow">
            <svg class="mb-2 h-8 w-8 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            <p class="text-md text-gray-500 dark:text-gray-400">No deliveries scheduled in the next 7 days.</p>
        </div>
        <p v-if="links.deliveries" class="mt-3">
            <Link
                :href="links.deliveries"
                class="text-md font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400"
            >
                All deliveries
            </Link>
        </p>
    </DashboardWidgetShell>
</template>
