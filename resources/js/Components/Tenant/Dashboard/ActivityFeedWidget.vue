<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import DashboardWidgetShell from '@/Components/Tenant/Dashboard/DashboardWidgetShell.vue';

const props = defineProps({
    activity: {
        type: Object,
        default: () => ({}),
    },
});

const items = computed(() => props.activity?.items ?? []);
const links = computed(() => props.activity?.links ?? {});

const isEmpty = computed(() => items.value.length === 0);

function formatAt(iso) {
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

function relativeAt(iso) {
    if (!iso) return '';
    try {
        const d = new Date(iso);
        const diffMs = Date.now() - d.getTime();
        const diffMin = Math.floor(diffMs / 60000);
        if (diffMin < 1) return 'just now';
        if (diffMin < 60) return `${diffMin}m ago`;
        const diffHour = Math.floor(diffMin / 60);
        if (diffHour < 24) return `${diffHour}h ago`;
        const diffDay = Math.floor(diffHour / 24);
        if (diffDay === 1) return 'yesterday';
        if (diffDay < 30) return `${diffDay}d ago`;
        return '';
    } catch {
        return '';
    }
}
</script>

<template>
    <DashboardWidgetShell
        title="Activity"
        :empty="isEmpty"
        empty-message="No recent notifications or payments to show."
    >
        <ul class="divide-y divide-gray-100 dark:divide-gray-700">
            <li v-for="row in items" :key="row.type + '-' + row.id" class="py-3 first:pt-0">
                <component
                    :is="row.href ? Link : 'div'"
                    :href="row.href || undefined"
                    class="block"
                    :class="row.href ? 'hover:opacity-90' : ''"
                >
                    <p class="flex items-center gap-1.5 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        <svg
                            v-if="row.type === 'payment'"
                            class="h-3.5 w-3.5 text-green-600 dark:text-green-400"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                        >
                            <path d="M10 2a8 8 0 100 16 8 8 0 000-16zm.75 3.5a.75.75 0 10-1.5 0v.37a2.5 2.5 0 00.5 4.95h.5a1 1 0 110 2h-1.5a.75.75 0 000 1.5h.5v.18a.75.75 0 001.5 0v-.2a2.5 2.5 0 00-.5-4.95h-.5a1 1 0 010-2h1.5a.75.75 0 000-1.5h-.5V5.5z" />
                        </svg>
                        <svg
                            v-else
                            class="h-3.5 w-3.5 text-blue-600 dark:text-blue-400"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                        >
                            <path d="M10 2a6 6 0 00-6 6v2.586L3.293 11.293A1 1 0 004 13h12a1 1 0 00.707-1.707L16 10.586V8a6 6 0 00-6-6z" />
                            <path d="M8 14a2 2 0 104 0H8z" />
                        </svg>
                        {{ row.type === 'payment' ? 'Payment' : 'Notification' }}
                    </p>
                    <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ row.title }}</p>
                    <p v-if="row.subtitle" class="text-sm text-gray-600 dark:text-gray-300">{{ row.subtitle }}</p>
                    <p v-if="row.at" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ formatAt(row.at) }}
                        <span v-if="relativeAt(row.at)"> · {{ relativeAt(row.at) }}</span>
                    </p>
                </component>
            </li>
        </ul>
        <div v-if="links.notifications || links.payments" class="mt-4 flex flex-wrap gap-4 border-t border-gray-100 pt-3 dark:border-gray-700">
            <Link
                v-if="links.notifications"
                :href="links.notifications"
                class="text-sm font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400"
            >
                Notifications
            </Link>
            <Link
                v-if="links.payments"
                :href="links.payments"
                class="text-sm font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400"
            >
                All payments
            </Link>
        </div>
    </DashboardWidgetShell>
</template>
