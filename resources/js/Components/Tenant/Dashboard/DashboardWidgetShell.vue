<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    title: {
        type: String,
        required: true,
    },
    moreHref: {
        type: String,
        default: null,
    },
    moreLabel: {
        type: String,
        default: 'View all',
    },
    count: {
        type: Number,
        default: null,
    },
    empty: {
        type: Boolean,
        default: false,
    },
    emptyMessage: {
        type: String,
        default: 'Nothing to show right now.',
    },
});
</script>

<template>
    <section
        class="flex h-full min-h-[11rem] flex-col rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
    >
        <header
            class="flex items-start justify-between gap-2 border-b border-gray-100 px-4 py-3 dark:border-gray-700"
        >
            <div class="flex items-center gap-2">
                <h3 class="text-base font-semibold leading-tight text-gray-900 dark:text-white">
                    {{ title }}
                </h3>
                <span
                    v-if="typeof count === 'number'"
                    class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-700 dark:bg-gray-700 dark:text-gray-200"
                >
                    {{ count }}
                </span>
            </div>
            <Link
                v-if="moreHref"
                :href="moreHref"
                class="shrink-0 text-sm font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300"
            >
                {{ moreLabel }}
            </Link>
        </header>
        <div class="flex flex-1 flex-col p-4">
            <p v-if="empty" class="text-sm text-gray-500 dark:text-gray-400">
                {{ emptyMessage }}
            </p>
            <slot v-else />
        </div>
    </section>
</template>
