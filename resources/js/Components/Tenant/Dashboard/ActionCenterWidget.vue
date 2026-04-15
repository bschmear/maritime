<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import DashboardWidgetShell from '@/Components/Tenant/Dashboard/DashboardWidgetShell.vue';

/**
 * Badge styles aligned with App\Enums\Tasks\Status::color() keys.
 * Keeps Tailwind classes in source for JIT.
 */
const TASK_STATUS_BADGE_CLASS = {
    blue: 'rounded-md px-2 py-0.5 text-xs font-medium bg-blue-200 text-blue-900 dark:bg-blue-900 dark:text-white',
    yellow: 'rounded-md px-2 py-0.5 text-xs font-medium bg-yellow-200 text-yellow-900 dark:bg-yellow-900 dark:text-white',
    gray: 'rounded-md px-2 py-0.5 text-xs font-medium bg-gray-200 text-gray-800 dark:bg-gray-800 dark:text-gray-200',
    red: 'rounded-md px-2 py-0.5 text-xs font-medium bg-red-200 text-red-900 dark:bg-red-900 dark:text-white',
    green: 'rounded-md px-2 py-0.5 text-xs font-medium bg-green-200 text-green-900 dark:bg-green-900 dark:text-white',
};

/**
 * Badge styles aligned with App\Enums\Tasks\Priority::color() keys.
 */
const TASK_PRIORITY_BADGE_CLASS = {
    green: 'rounded-md px-2 py-0.5 text-xs font-medium bg-green-200 text-green-900 dark:bg-green-900 dark:text-white',
    yellow: 'rounded-md px-2 py-0.5 text-xs font-medium bg-yellow-200 text-yellow-900 dark:bg-yellow-900 dark:text-white',
    orange: 'rounded-md px-2 py-0.5 text-xs font-medium bg-orange-200 text-orange-900 dark:bg-orange-900 dark:text-white',
    red: 'rounded-md px-2 py-0.5 text-xs font-medium bg-red-200 text-red-900 dark:bg-red-900 dark:text-white',
    gray: 'rounded-md px-2 py-0.5 text-xs font-medium bg-gray-200 text-gray-800 dark:bg-gray-800 dark:text-gray-200',
};

const FALLBACK_BADGE_CLASS =
    'rounded-md px-2 py-0.5 text-xs font-medium bg-gray-200 text-gray-800 dark:bg-gray-800 dark:text-gray-200';

const props = defineProps({
    actionCenter: {
        type: Object,
        default: () => ({}),
    },
});

const tasks = computed(() => props.actionCenter?.tasks ?? []);
const followUps = computed(() => props.actionCenter?.followUps ?? []);
const deliveriesToday = computed(() => props.actionCenter?.deliveriesToday ?? []);

const isEmpty = computed(
    () => tasks.value.length === 0 && followUps.value.length === 0 && deliveriesToday.value.length === 0
);

function formatDue(iso) {
    if (!iso) return '';
    try {
        const d = new Date(iso);
        return d.toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' });
    } catch {
        return iso;
    }
}

function statusBadgeClass(color) {
    if (!color || typeof color !== 'string') {
        return FALLBACK_BADGE_CLASS;
    }
    return TASK_STATUS_BADGE_CLASS[color] ?? FALLBACK_BADGE_CLASS;
}

function priorityBadgeClass(color) {
    if (!color || typeof color !== 'string') {
        return FALLBACK_BADGE_CLASS;
    }
    return TASK_PRIORITY_BADGE_CLASS[color] ?? FALLBACK_BADGE_CLASS;
}
</script>

<template>
    <DashboardWidgetShell
        title="Action center"
        :more-href="route('tasks.index')"
        more-label="Tasks"
        :empty="isEmpty"
        empty-message="You are caught up. No tasks, follow-ups, or deliveries due today."
    >
        <div class="space-y-5">
            <div v-if="tasks.length">
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Tasks due today / overdue
                </p>
                <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                    <li v-for="row in tasks" :key="'t-' + row.id" class="py-3 first:pt-0">
                        <component
                            :is="row.href ? Link : 'span'"
                            :href="row.href || undefined"
                            class="block text-sm font-semibold text-gray-900 hover:text-primary-600 dark:text-white dark:hover:text-primary-400"
                            :class="{ 'cursor-default hover:text-gray-900 dark:hover:text-white': !row.href }"
                        >
                            {{ row.label }}
                        </component>
                        <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">
                            <span class="font-medium text-gray-700 dark:text-gray-200">Due:</span>
                            {{ formatDue(row.due_at) || '—' }}
                            <template v-if="row.due_time">
                                <span class="mx-1 text-gray-400">·</span>
                                <span>{{ row.due_time }}</span>
                            </template>
                        </p>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <span class="text-xs text-gray-500 dark:text-gray-400">Status</span>
                            <span :class="statusBadgeClass(row.status_color)">{{ row.status || '—' }}</span>
                            <span class="text-xs text-gray-400">·</span>
                            <span class="text-xs text-gray-500 dark:text-gray-400">Priority</span>
                            <span :class="priorityBadgeClass(row.priority_color)">{{ row.priority || '—' }}</span>
                            <span class="text-xs text-gray-400">·</span>
                            <div
                                v-if="row.assigned"
                                class="flex items-center space-x-1 text-xs text-gray-500 dark:text-gray-400"
                            >
                                <svg class="h-3 w-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                                    />
                                </svg>
                                <span>{{ row.assigned.display_name }}</span>
                            </div>
                            <span v-else class="text-xs text-gray-400 dark:text-gray-500">Unassigned</span>
                        </div>
                    </li>
                </ul>
            </div>
            <div v-if="followUps.length">
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Follow-ups
                </p>
                <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                    <li v-for="row in followUps" :key="'l-' + row.id" class="py-2 first:pt-0">
                        <component
                            :is="row.href ? Link : 'span'"
                            :href="row.href || undefined"
                            class="block text-sm font-medium text-gray-900 hover:text-primary-600 dark:text-white dark:hover:text-primary-400"
                            :class="{ 'cursor-default hover:text-gray-900 dark:hover:text-white': !row.href }"
                        >
                            {{ row.label }}
                        </component>
                        <p v-if="row.due_at" class="text-xs text-gray-500 dark:text-gray-400">
                            Follow up {{ row.due_at }}
                        </p>
                    </li>
                </ul>
            </div>
            <div v-if="deliveriesToday.length">
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Deliveries today
                </p>
                <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                    <li v-for="row in deliveriesToday" :key="'d-' + row.id" class="py-2 first:pt-0">
                        <component
                            :is="row.href ? Link : 'span'"
                            :href="row.href || undefined"
                            class="block text-sm font-medium text-gray-900 hover:text-primary-600 dark:text-white dark:hover:text-primary-400"
                            :class="{ 'cursor-default hover:text-gray-900 dark:hover:text-white': !row.href }"
                        >
                            {{ row.label }}
                        </component>
                        <p v-if="row.scheduled_at" class="text-xs text-gray-500 dark:text-gray-400">
                            {{ formatDue(row.scheduled_at) }}
                        </p>
                    </li>
                </ul>
            </div>
        </div>
    </DashboardWidgetShell>
</template>
