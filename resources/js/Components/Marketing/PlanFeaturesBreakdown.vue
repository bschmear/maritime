<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
    plans: { type: Array, required: true },
    selectedPlanId: { type: [Number, String, null], default: null },
    title: { type: String, default: 'Everything included' },
    subtitle: { type: String, default: 'Full details for each capability in your plan.' },
    sectionId: { type: String, default: 'plan-features' },
});

const activePlanId = ref(null);

const plansWithFeatures = computed(() =>
    props.plans.filter((plan) => {
        const details = plan.feature_details ?? plan.features ?? [];
        return Array.isArray(details) && details.length > 0;
    }),
);

const resolvedPlanId = computed(() => {
    if (props.selectedPlanId != null) {
        return props.selectedPlanId;
    }
    return activePlanId.value ?? plansWithFeatures.value[0]?.id ?? null;
});

const activePlan = computed(() =>
    plansWithFeatures.value.find((p) => String(p.id) === String(resolvedPlanId.value)),
);

const activeFeatures = computed(() => {
    const plan = activePlan.value;
    if (!plan) {
        return [];
    }

    const raw = plan.feature_details ?? plan.features ?? [];

    return raw
        .map((item) => {
            if (typeof item === 'string') {
                return { title: item, description: '' };
            }

            return {
                title: item.title ?? '',
                description: item.description ?? '',
            };
        })
        .filter((f) => f.title.trim() !== '');
});

const showPlanTabs = computed(
    () => props.selectedPlanId == null && plansWithFeatures.value.length > 1,
);

watch(
    plansWithFeatures,
    (list) => {
        if (list.length && activePlanId.value == null) {
            const popular = list.find((p) => p.popular);
            activePlanId.value = popular?.id ?? list[0].id;
        }
    },
    { immediate: true },
);

const selectPlanTab = (planId) => {
    activePlanId.value = planId;
};
</script>

<template>
    <section
        v-if="activeFeatures.length > 0"
        :id="sectionId"
        class="w-full scroll-mt-24 border-t border-gray-200 bg-gray-50 py-16 dark:border-gray-800 dark:bg-gray-900/50"
    >
        <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <h2 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white sm:text-4xl">
                    {{ title }}
                </h2>
                <p class="mt-3 text-lg text-gray-600 dark:text-gray-400">
                    {{ subtitle }}
                </p>
                <p
                    v-if="activePlan && selectedPlanId != null"
                    class="mt-2 text-sm font-semibold text-primary-600 dark:text-primary-400"
                >
                    {{ activePlan.name }}
                </p>
            </div>

            <div v-if="showPlanTabs" class="mt-10 flex flex-wrap justify-center gap-2">
                <button
                    v-for="plan in plansWithFeatures"
                    :key="plan.id"
                    type="button"
                    class="rounded-full px-4 py-2 text-sm font-semibold transition"
                    :class="
                        String(resolvedPlanId) === String(plan.id)
                            ? 'bg-primary-600 text-white shadow-md'
                            : 'bg-white text-gray-700 ring-1 ring-gray-200 hover:bg-gray-100 dark:bg-gray-800 dark:text-gray-300 dark:ring-gray-700 dark:hover:bg-gray-700'
                    "
                    @click="selectPlanTab(plan.id)"
                >
                    {{ plan.name }}
                </button>
            </div>

            <ul class="mt-12 grid w-full grid-cols-1 gap-x-10 gap-y-8 sm:grid-cols-2 lg:grid-cols-3">
                <li
                    v-for="feature in activeFeatures"
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
