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
    <section class="relative overflow-hidden bg-secondary-50 px-6 py-20 dark:bg-secondary-950/25 sm:px-12 lg:px-24">
        <div class="pointer-events-none absolute inset-0 select-none text-secondary-500/90 dark:text-secondary-600/80" aria-hidden="true">
            <!-- Nautical chart: range rings + compass rose -->
            <svg
                class="absolute left-1/2 top-1/2 h-[min(140%,42rem)] w-[min(140%,42rem)] -translate-x-1/2 -translate-y-1/2 opacity-[0.14] dark:opacity-[0.1]"
                viewBox="0 0 400 400"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
            >
                <circle cx="200" cy="200" r="48" stroke="currentColor" stroke-width="0.75" />
                <circle cx="200" cy="200" r="96" stroke="currentColor" stroke-width="0.75" />
                <circle cx="200" cy="200" r="144" stroke="currentColor" stroke-width="0.75" />
                <circle cx="200" cy="200" r="192" stroke="currentColor" stroke-width="0.75" />
                <line x1="200" y1="8" x2="200" y2="392" stroke="currentColor" stroke-width="0.5" stroke-dasharray="4 6" />
                <line x1="8" y1="200" x2="392" y2="200" stroke="currentColor" stroke-width="0.5" stroke-dasharray="4 6" />
                <line x1="66" y1="66" x2="334" y2="334" stroke="currentColor" stroke-width="0.35" stroke-dasharray="2 8" opacity="0.7" />
                <line x1="334" y1="66" x2="66" y2="334" stroke="currentColor" stroke-width="0.35" stroke-dasharray="2 8" opacity="0.7" />
                <g transform="translate(200 200)" stroke="currentColor" stroke-linejoin="round">
                    <polygon points="0,-28 7,-8 0,-12 -7,-8" fill="currentColor" stroke="none" />
                    <polygon points="0,28 7,8 0,12 -7,8" fill="currentColor" stroke="none" opacity="0.55" />
                    <polygon points="28,0 8,7 12,0 8,-7" fill="currentColor" stroke="none" />
                    <polygon points="-28,0 -8,7 -12,0 -8,-7" fill="currentColor" stroke="none" opacity="0.55" />
                    <circle r="6" fill="currentColor" opacity="0.4" />
                </g>
            </svg>

            <!-- Repeating swell lines -->
            <svg class="absolute inset-0 h-full w-full opacity-[0.12] dark:opacity-[0.08]" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <pattern id="feature-cta-swell" width="160" height="48" patternUnits="userSpaceOnUse">
                        <path
                            d="M0 28 C40 16 80 40 120 28 S200 16 160 28"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1"
                        />
                        <path
                            d="M0 38 C50 26 90 44 160 36"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="0.75"
                            opacity="0.6"
                        />
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#feature-cta-swell)" />
            </svg>

            <!-- Sea-level waves (bottom) -->
            <div class="absolute bottom-0 left-0 right-0 h-36 opacity-30 dark:opacity-20">
                <svg class="h-full w-full" viewBox="0 0 1440 160" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
                    <path
                        class="fill-secondary-300/50 dark:fill-secondary-800/60"
                        d="M0,72 C240,32 480,112 720,72 C960,32 1200,112 1440,72 L1440,160 L0,160 Z"
                    />
                    <path
                        fill="none"
                        class="stroke-secondary-500/40 dark:stroke-secondary-400/30"
                        stroke-width="2"
                        d="M-40,58 C200,88 400,28 640,58 C880,88 1080,38 1480,58"
                    />
                    <path
                        fill="none"
                        class="stroke-secondary-600/35 dark:stroke-secondary-500/25"
                        stroke-width="1.5"
                        d="M-40,48 C180,18 420,78 660,48 C900,18 1140,68 1480,48"
                    />
                </svg>
            </div>

            <!-- Anchor watermark -->
            <span
                class="material-icons absolute -bottom-6 -right-4 text-[11rem] leading-none text-secondary-400/15 dark:text-secondary-600/10 sm:-right-2 sm:text-[14rem]"
            >
                anchor
            </span>

            <div
                class="absolute inset-0 bg-[radial-gradient(ellipse_85%_70%_at_50%_45%,transparent_30%,rgb(236_253_245)_100%)] dark:bg-[radial-gradient(ellipse_85%_70%_at_50%_45%,transparent_30%,rgb(2_44_34)_100%)]"
            />
        </div>

        <div class="relative z-10 mx-auto flex max-w-7xl flex-col items-center gap-6 text-center">
            <div
                v-if="badge"
                class="inline-flex items-center gap-2 rounded-full border border-secondary-200 bg-secondary-100 px-4 py-1.5 dark:border-secondary-800 dark:bg-secondary-900/50"
            >
                <span class="material-icons text-sm leading-none text-secondary-600 dark:text-secondary-400">{{ badgeIcon }}</span>
                <span class="text-xs font-semibold uppercase tracking-widest text-secondary-700 dark:text-secondary-400">{{ badge }}</span>
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
                    class="inline-flex items-center gap-2 rounded-xl bg-secondary-600 px-6 py-3 text-sm font-semibold text-white shadow-md transition hover:bg-secondary-700"
                >
                    {{ primaryLabel }}
                    <span class="material-icons text-base leading-none">arrow_forward</span>
                </Link>
                <Link
                    v-if="showSecondary"
                    :href="route(secondaryRoute)"
                    class="inline-flex items-center gap-2 rounded-xl border border-secondary-200 bg-white px-6 py-3 text-sm font-semibold text-secondary-700 transition hover:border-secondary-400 hover:bg-secondary-50 dark:border-secondary-700 dark:bg-gray-900 dark:text-secondary-300 dark:hover:border-secondary-500 dark:hover:bg-secondary-950/40"
                >
                    {{ secondaryLabel }}
                </Link>
            </div>
        </div>
    </section>
</template>
