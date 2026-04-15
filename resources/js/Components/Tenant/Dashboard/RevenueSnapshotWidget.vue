<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import DashboardWidgetShell from '@/Components/Tenant/Dashboard/DashboardWidgetShell.vue';

const props = defineProps({
    revenue: {
        type: Object,
        default: () => ({}),
    },
});

const recent = computed(() => props.revenue?.recentPayments ?? []);

function formatCurrency(value) {
    if (value == null || value === '') return '—';
    const n = Number(value);
    if (Number.isNaN(n)) return '—';
    return new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD' }).format(n);
}
</script>

<template>
    <DashboardWidgetShell
        title="Recent payments"
        :count="recent.length"
        :more-href="route('payments.index')"
        more-label="Payments"
        :empty="recent.length === 0"
        empty-message="No recent completed payments to show."
    >
        <ul class="space-y-2">
            <li v-for="p in recent" :key="'p-' + p.id">
                <component
                    :is="p.href ? Link : 'span'"
                    :href="p.href || undefined"
                    class="flex justify-between gap-2 text-sm"
                    :class="p.href ? 'text-primary-600 hover:text-primary-700 dark:text-primary-400' : 'text-gray-700 dark:text-gray-300'"
                >
                    <span class="truncate font-medium text-gray-900 dark:text-white">{{ p.label }}</span>
                    <span class="shrink-0 text-gray-600 dark:text-gray-400">{{ formatCurrency(p.amount) }}</span>
                </component>
                <p v-if="p.customer" class="truncate text-xs text-gray-500 dark:text-gray-400">{{ p.customer }}</p>
            </li>
        </ul>
    </DashboardWidgetShell>
</template>
