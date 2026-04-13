<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    plans: Array,
    selectedPlanId: Number,
    billingCycle: String,
});

const billingCycle = ref(props.billingCycle || 'monthly');
const selectedPlan = ref(props.selectedPlanId || null);

const selectPlan = (planId) => {
    selectedPlan.value = planId;
};

const proceedToCart = () => {
    if (!selectedPlan.value) {
        alert('Please select a plan');
        return;
    }

    router.get(route('checkout.cart'), {
        plan_id: selectedPlan.value,
        billing_cycle: billingCycle.value,
    });
};
</script>

<template>
    <AppLayout>
        <Head title="Choose Your Plan" />

        <div class="min-h-screen bg-gray-50 dark:bg-gray-900">
            <!-- Hero (aligned with blog) -->
            <section class="relative border-b border-gray-200 dark:border-gray-800 bg-secondary-50 dark:bg-gray-900 py-16 sm:py-20">
                <div class="relative mx-auto max-w-7xl px-4 text-center sm:px-6 lg:px-8">
                    <div class="mb-6 inline-flex items-center gap-2 rounded-full border border-secondary-200/50 bg-secondary-100 px-4 py-2 text-sm font-medium text-secondary-700 backdrop-blur-sm dark:border-secondary-700/50 dark:bg-secondary-900/50 dark:text-secondary-300">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>Pricing</span>
                    </div>
                    <h1 class="mb-4 text-5xl font-bold tracking-tight text-gray-900 dark:text-white sm:text-6xl">
                        Choose your
                        <span class="text-secondary-600 dark:text-secondary-400">plan</span>
                    </h1>
                    <p class="mx-auto max-w-2xl text-xl text-gray-600 dark:text-gray-300">
                        Select the perfect plan for your needs. All plans include a 14-day free trial.
                    </p>
                </div>
            </section>

            <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
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
                            @click="billingCycle = 'annual'"
                            :class="[
                                'flex items-center gap-2 rounded-full px-6 py-2 font-semibold transition-all duration-200',
                                billingCycle === 'annual'
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
                        tabindex="0"
                        @click="selectPlan(plan.id)"
                        @keydown.enter.prevent="selectPlan(plan.id)"
                        @keydown.space.prevent="selectPlan(plan.id)"
                        :class="[
                            'relative cursor-pointer overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-lg transition-all duration-300 dark:border-gray-700 dark:bg-gray-800 dark:shadow-none',
                            selectedPlan === plan.id
                                ? 'ring-2 ring-secondary-500 ring-offset-2 ring-offset-gray-50 dark:ring-secondary-400 dark:ring-offset-gray-900 md:scale-[1.02]'
                                : 'hover:-translate-y-1 hover:shadow-xl dark:hover:border-gray-600',
                            plan.popular ? 'md:scale-[1.03]' : '',
                        ]"
                    >
                        <!-- Popular Badge -->
                        <div
                            v-if="plan.popular"
                            class="absolute right-0 top-0 rounded-bl-xl bg-secondary-600 px-4 py-1.5 text-xs font-bold text-white dark:bg-secondary-500"
                        >
                            MOST POPULAR
                        </div>

                        <!-- Selected Badge -->
                        <div
                            v-if="selectedPlan === plan.id"
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
                            </div>

                            <!-- Features List -->
                            <div class="space-y-4">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wide">What's included:</p>
                                <ul class="space-y-3">
                                    <li v-for="(feature, index) in plan.included" :key="index" class="flex items-start gap-3">
                                        <svg class="w-5 h-5 text-secondary-600 dark:text-secondary-400 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                        </svg>
                                        <span class="text-gray-600 dark:text-gray-400">{{ feature }}</span>
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
        </div>
    </AppLayout>
</template>
