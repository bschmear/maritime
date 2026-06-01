<script setup>
import { computed } from 'vue';

const props = defineProps({
    title: { type: String, default: 'All tiers include' },
    subtitle: { type: String, default: '' },
    features: { type: Array, default: () => [] },
    sectionId: { type: String, default: 'plan-features' },
    /** When true, omit full-bleed band styles (for use inside an existing pricing section). */
    embedded: { type: Boolean, default: false },
});

const normalizedFeatures = computed(() =>
    props.features
        .map((item) => {
            if (typeof item === 'string') {
                return { title: item, description: '' };
            }

            return {
                title: item.title ?? '',
                description: item.description ?? '',
            };
        })
        .filter((f) => f.title.trim() !== ''),
);
</script>

<template>
    <section
        v-if="normalizedFeatures.length > 0"
        :id="sectionId"
        :class="[
            'w-full scroll-mt-24',
            embedded
                ? 'py-10 sm:py-12'
                : 'border-t border-gray-200 bg-gray-50 py-16 dark:border-gray-800 dark:bg-gray-900/50',
        ]"
    >
        <div :class="embedded ? 'w-full' : 'mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8'">
            <div class="text-center">
                <h2
                    :class="[
                        'font-bold tracking-tight text-gray-900 dark:text-white',
                        embedded ? 'text-2xl sm:text-3xl' : 'text-3xl sm:text-4xl',
                    ]"
                >
                    {{ title }}
                </h2>
                <p v-if="subtitle" class="mt-3 text-lg text-gray-600 dark:text-gray-400">
                    {{ subtitle }}
                </p>
            </div>

            <ul
                :class="[
                    'grid w-full grid-cols-1 gap-x-10 gap-y-8 sm:grid-cols-2 lg:grid-cols-3',
                    embedded ? 'mt-8' : 'mt-12',
                ]"
            >
                <li
                    v-for="feature in normalizedFeatures"
                    :key="feature.title"
                    class="flex items-start gap-3"
                >
                    <svg
                        class="mt-0.5 h-5 w-5 shrink-0 text-green-600 dark:text-green-500"
                        fill="currentColor"
                        viewBox="0 0 20 20"
                        aria-hidden="true"
                    >
                        <path
                            fill-rule="evenodd"
                            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                            clip-rule="evenodd"
                        />
                    </svg>
                    <div class="min-w-0">
                        <p class="font-semibold text-gray-900 dark:text-white">
                            {{ feature.title }}
                        </p>
                        <p
                            v-if="feature.description"
                            class="mt-1.5 text-sm leading-relaxed text-gray-600 dark:text-gray-400"
                        >
                            {{ feature.description }}
                        </p>
                    </div>
                </li>
            </ul>
        </div>
    </section>
</template>
