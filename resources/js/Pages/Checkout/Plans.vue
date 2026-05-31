<script setup>
import PlanAllTiersIncluded from '@/Components/Marketing/PlanAllTiersIncluded.vue';
import { planFeatureTitles } from '@/composables/usePlanFeatureTitles';
import { ref, computed, onMounted, nextTick } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    plans: Array,
    allTiers: {
        type: Object,
        default: () => ({ title: 'All tiers include', subtitle: '', features: [] }),
    },
    selectedPlanId: Number,
    billingCycle: String,
    prefilled_existing_account_id: {
        type: Number,
        default: null,
    },
});

const page = usePage();

const planCheckoutError = computed(() => page.props.errors?.plan);

const billingCycle = ref(
    props.billingCycle === 'yearly' || props.billingCycle === 'annual' ? 'yearly' : 'monthly',
);

const initialPlanId = props.selectedPlanId || null;
const initialPlan = initialPlanId ? props.plans.find((p) => p.id === initialPlanId) : null;
const selectedPlan = ref(initialPlan && !initialPlan.coming_soon ? initialPlanId : null);

const featureTitlesForPlan = (plan) => plan.feature_titles ?? planFeatureTitles(plan.features);

const scrollToPlanFeatures = () => {
    document.getElementById('plan-features')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
};

onMounted(() => {
    if (window.location.hash === '#plan-features') {
        nextTick(() => scrollToPlanFeatures());
    }
});

const selectPlan = (plan) => {
    if (plan.coming_soon) {
        return;
    }
    selectedPlan.value = plan.id;
};

const proceedToCart = () => {
    if (!selectedPlan.value) {
        alert('Please select a plan');
        return;
    }

    const chosen = props.plans.find((p) => p.id === selectedPlan.value);
    if (chosen?.coming_soon) {
        alert('This plan is not available for checkout yet.');
        return;
    }

    router.get(route('checkout.cart'), {
        plan_id: selectedPlan.value,
        billing_cycle: billingCycle.value,
        ...(props.prefilled_existing_account_id
            ? { existing_account_id: props.prefilled_existing_account_id }
            : {}),
    });
};
</script>

<template>
    <AppLayout>
        <Head title="Choose Your Plan" />

        <div class="min-h-screen bg-gray-50 dark:bg-gray-900">
            <!-- Hero -->
            <section class="relative border-b border-gray-200 bg-primary-50 py-16 dark:border-gray-800 dark:bg-gray-900 sm:py-20">
                <div class="relative mx-auto max-w-7xl px-4 text-center sm:px-6 lg:px-8">
                    <div class="mb-6 inline-flex items-center gap-2 rounded-full border border-primary-200/50 bg-primary-100 px-4 py-2 text-sm font-medium text-primary-700 backdrop-blur-sm dark:border-primary-700/50 dark:bg-primary-900/50 dark:text-primary-300">
                        <span class="material-icons text-base leading-none">payments</span>
                        <span>Pricing</span>
                    </div>
                    <h1 class="mb-4 text-5xl font-bold tracking-tight text-gray-900 dark:text-white sm:text-6xl">
                        Choose your
                        <span class="text-primary-600 dark:text-primary-400">plan</span>
                    </h1>
                    <p class="mx-auto max-w-2xl text-xl text-gray-600 dark:text-gray-300">
                        Select the perfect plan for your needs. All plans include a 14-day free trial.
                    </p>
                </div>
            </section>

            <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                <div
                    v-if="planCheckoutError"
                    class="mb-8 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-center text-sm font-medium text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100"
                >
                    {{ planCheckoutError }}
                </div>
                <!-- Billing Toggle -->
                <div class="mb-12 flex justify-center">
                    <div class="inline-flex items-center rounded-full border border-gray-200 bg-white p-1 shadow-lg dark:border-gray-700 dark:bg-gray-800">
                        <button
                            type="button"
                            @click="billingCycle = 'monthly'"
                            :class="[
                                'rounded-full px-6 py-2 font-semibold transition-all duration-200',
                                billingCycle === 'monthly'
                                    ? 'bg-primary-600 text-white shadow-md'
                                    : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white',
                            ]"
                        >
                            Monthly
                        </button>
                        <button
                            type="button"
                            @click="billingCycle = 'yearly'"
                            :class="[
                                'flex items-center gap-2 rounded-full px-6 py-2 font-semibold transition-all duration-200',
                                billingCycle === 'yearly'
                                    ? 'bg-primary-600 text-white shadow-md'
                                    : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white',
                            ]"
                        >
                            Annual
                            <span class="rounded-full bg-secondary-600 px-2 py-0.5 text-xs font-bold text-white dark:bg-secondary-500">Save 20%</span>
                        </button>
                    </div>
                </div>

                <!-- Plans Grid -->
                <div class="mb-12 grid grid-cols-1 gap-8 md:grid-cols-3 lg:gap-6">
                    <div
                        v-for="plan in plans"
                        :key="plan.id"
                        role="button"
                        :tabindex="plan.coming_soon ? -1 : 0"
                        @click="selectPlan(plan)"
                        @keydown.enter.prevent="!plan.coming_soon && selectPlan(plan)"
                        @keydown.space.prevent="!plan.coming_soon && selectPlan(plan)"
                        :class="[
                            'relative overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-lg transition-all duration-300 dark:border-gray-700 dark:bg-gray-800 dark:shadow-none',
                            plan.coming_soon
                                ? 'cursor-not-allowed opacity-80'
                                : 'cursor-pointer hover:-translate-y-1 hover:shadow-xl dark:hover:border-gray-600',
                            !plan.coming_soon && selectedPlan === plan.id
                                ? 'ring-2 ring-secondary-500 ring-offset-2 ring-offset-gray-50 dark:ring-secondary-400 dark:ring-offset-gray-900 md:scale-[1.02]'
                                : '',
                            plan.popular ? 'md:scale-[1.03]' : '',
                        ]"
                    >
                        <div
                            v-if="plan.coming_soon"
                            class="absolute right-0 top-0 rounded-bl-xl bg-gray-600 px-4 py-1.5 text-xs font-bold text-white dark:bg-gray-500"
                        >
                            COMING SOON
                        </div>

                        <!-- Popular Badge -->
                        <div
                            v-else-if="plan.popular"
                            class="absolute right-0 top-0 rounded-bl-xl bg-secondary-600 px-4 py-1.5 text-xs font-bold text-white dark:bg-secondary-500"
                        >
                            MOST POPULAR
                        </div>

                        <!-- Selected Badge -->
                        <div
                            v-if="!plan.coming_soon && selectedPlan === plan.id"
                            class="absolute left-4 top-4 rounded-full bg-secondary-600 p-2 text-white shadow-md dark:bg-secondary-500"
                        >
                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                        </div>

                        <div class="p-8">
                            <!-- Plan Header -->
                            <div class="mb-6">
                                <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">{{ plan.name }}</h3>
                                <p class="text-gray-600 dark:text-gray-400 text-sm">{{ plan.description }}</p>
                            </div>

                            <!-- Price -->
                            <div class="mb-8">
                                <template v-if="plan.coming_soon">
                                    <p class="text-3xl font-bold tracking-tight text-gray-700 dark:text-gray-200">
                                        Coming soon
                                    </p>
                                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                        This plan is not available for purchase yet. Check back later.
                                    </p>
                                </template>
                                <template v-else>
                                    <div class="flex items-baseline gap-2">
                                        <span class="text-5xl font-bold text-gray-900 dark:text-white">
                                            ${{ billingCycle === 'monthly' ? plan.monthly_price : plan.yearly_price }}
                                        </span>
                                        <span class="text-gray-600 dark:text-gray-400">
                                            {{ billingCycle === 'monthly' ? '/month' : '/year' }}
                                        </span>
                                    </div>
                                    <p v-if="billingCycle === 'yearly' && plan.yearly_price > 0" class="text-sm text-green-600 dark:text-green-400 mt-2">
                                        Save ${{ (plan.monthly_price * 12) - plan.yearly_price }}/year
                                    </p>
                                </template>
                            </div>

                            <!-- Features List -->
                            <div class="space-y-4">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wide">What's included:</p>
                                <ul class="space-y-3">
                                    <li
                                        v-for="(title, index) in featureTitlesForPlan(plan)"
                                        :key="index"
                                        class="flex items-start gap-3"
                                    >
                                        <svg class="w-5 h-5 text-secondary-600 dark:text-secondary-400 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                        </svg>
                                        <span class="text-gray-600 dark:text-gray-400">{{ title }}</span>
                                    </li>
                                </ul>
                            </div>

                            <!-- Seat Limit -->
                            <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    <span class="font-semibold">{{ plan.seat_limit }}</span>
                                    {{ plan.seat_limit === 1 ? 'seat' : 'seats' }} included
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex flex-wrap justify-center gap-4">
                    <Link
                        :href="route('home')"
                        class="rounded-xl border-2 border-gray-300 bg-white px-8 py-3 font-semibold text-gray-700 shadow-md transition-all duration-200 hover:border-secondary-500 hover:bg-secondary-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:border-secondary-500 dark:hover:bg-gray-700"
                    >
                        Back to Home
                    </Link>
                    <button
                        type="button"
                        @click="proceedToCart"
                        :disabled="!selectedPlan"
                        :class="[
                            'rounded-xl px-8 py-3 font-semibold transition-all duration-200',
                            selectedPlan
                                ? 'bg-primary-600 text-white shadow-md hover:bg-primary-700 hover:shadow-lg'
                                : 'cursor-not-allowed bg-gray-200 text-gray-500 dark:bg-gray-700 dark:text-gray-500',
                        ]"
                    >
                        Continue to Cart
                    </button>
                </div>
            </div>

            <PlanAllTiersIncluded
                :title="allTiers.title"
                :subtitle="allTiers.subtitle"
                :features="allTiers.features"
            />
        </div>
    </AppLayout>
</template>
