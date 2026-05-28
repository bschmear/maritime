<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    badge: {
        type: String,
        default: null,
    },
    badgeIcon: {
        type: String,
        default: 'anchor',
    },
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        required: true,
    },
    primaryLabel: {
        type: String,
        default: 'Request a demo',
    },
    primaryRoute: {
        type: String,
        default: 'contact',
    },
    secondaryLabel: {
        type: String,
        default: null,
    },
    secondaryRoute: {
        type: String,
        default: null,
    },
});

const showPrimary = computed(() => props.primaryRoute && route().has(props.primaryRoute));
const showSecondary = computed(
    () => props.secondaryLabel && props.secondaryRoute && route().has(props.secondaryRoute),
);
</script>

<template>
    <section class="relative overflow-hidden bg-primary-50 px-6 py-20 dark:bg-primary-950/25 sm:px-12 lg:px-24">
<!--         <div
            class="pointer-events-none absolute left-1/2 top-0 h-64 w-96 -translate-x-1/2 rounded-full bg-primary-400/15 blur-[80px] dark:bg-primary-500/10"
        /> -->

        <div class="relative mx-auto flex max-w-7xl flex-col items-center gap-6 text-center">
            <div
                v-if="badge"
                class="inline-flex items-center gap-2 rounded-full border border-primary-200 bg-primary-100 px-4 py-1.5 dark:border-primary-800 dark:bg-primary-900/50"
            >
                <span class="material-icons text-sm leading-none text-primary-600 dark:text-primary-400">{{ badgeIcon }}</span>
                <span class="text-xs font-semibold uppercase tracking-widest text-primary-700 dark:text-primary-400">{{ badge }}</span>
            </div>

            <h2 class="max-w-2xl text-3xl font-bold tracking-tight text-gray-950 dark:text-white sm:text-4xl">
                {{ title }}
            </h2>

            <p class="max-w-xl text-lg leading-relaxed text-gray-600 dark:text-gray-300">
                {{ description }}
            </p>

            <div
                v-if="showPrimary || showSecondary"
                class="flex flex-wrap items-center justify-center gap-4 pt-2"
            >
                <Link
                    v-if="showPrimary"
                    :href="route(primaryRoute)"
                    class="inline-flex items-center gap-2 rounded-xl bg-primary-600 px-6 py-3 text-sm font-semibold text-white shadow-md transition hover:bg-primary-700"
                >
                    {{ primaryLabel }}
                    <span class="material-icons text-base leading-none">arrow_forward</span>
                </Link>
                <Link
                    v-if="showSecondary"
                    :href="route(secondaryRoute)"
                    class="inline-flex items-center gap-2 rounded-xl border border-primary-200 bg-white px-6 py-3 text-sm font-semibold text-primary-700 transition hover:border-primary-400 hover:bg-primary-50 dark:border-primary-700 dark:bg-gray-900 dark:text-primary-300 dark:hover:border-primary-500 dark:hover:bg-primary-950/40"
                >
                    {{ secondaryLabel }}
                </Link>
            </div>
        </div>
    </section>
</template>
