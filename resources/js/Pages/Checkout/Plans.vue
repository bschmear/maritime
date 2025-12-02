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

        <div class="min-h-screen bg-gradient-to-br from-primary-50 to-secondary-50 dark:from-navy-900 dark:to-navy-800 py-12">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="text-center mb-12">
                    <h1 class="text-4xl sm:text-5xl font-bold text-gray-900 dark:text-white mb-4">
                        Choose Your Plan
                    </h1>
                    <p class="text-xl text-gray-600 dark:text-gray-400 max-w-2xl mx-auto">
                        Select the perfect plan for your needs. All plans include a 14-day free trial.
                    </p>
                </div>

                <!-- Billing Toggle -->
                <div class="flex justify-center mb-12">
                    <div class="inline-flex items-center bg-white dark:bg-navy-800 rounded-full p-1 shadow-md">
                        <button
                            @click="billingCycle = 'monthly'"
                            :class="[
                                'px-6 py-2 rounded-full font-semibold transition-all duration-200',
                                billingCycle === 'monthly'
                                    ? 'bg-primary-600 text-white shadow-lg'
                                    : 'text-gray-700 dark:text-white-400 hover:text-gray-900 dark:hover:text-white-100'
                            ]"
                        >
                            Monthly
                        </button>
                        <button
                            @click="billingCycle = 'annual'"
                            :class="[
                                'px-6 py-2 rounded-full font-semibold transition-all duration-200 flex items-center gap-2',
                                billingCycle === 'annual'
                                    ? 'bg-primary-600 text-white shadow-lg'
                                    : 'text-gray-700 dark:text-white-400 hover:text-gray-900 dark:hover:text-white-100'
                            ]"
                        >
                            Annual
                            <span class="text-xs bg-green-500 text-white px-2 py-0.5 rounded-full">Save 20%</span>
                        </button>
                    </div>
                </div>

                <!-- Plans Grid -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8 lg:gap-6 mb-12">
                    <div
                        v-for="plan in plans"
                        :key="plan.id"
                        @click="selectPlan(plan.id)"
                        :class="[
                            'relative bg-white dark:bg-navy-800 rounded-3xl shadow-xl overflow-hidden transition-all duration-300 hover:shadow-2xl',
                            selectedPlan === plan.id
                                ? 'ring-4 ring-primary-500 scale-105'
                                : 'hover:scale-105 hover:shadow-2xl',
                            plan.popular ? 'md:scale-110' : ''
                        ]"
                    >
                        <!-- Popular Badge -->
                        <div v-if="plan.popular" class="absolute top-0 right-0 bg-primary-500 text-white text-xs font-bold px-4 py-1 rounded-bl-2xl">
                            MOST POPULAR
                        </div>

                        <!-- Selected Badge -->
                        <div v-if="selectedPlan === plan.id" class="absolute top-4 left-4 bg-green-500 text-white rounded-full p-2">
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
                <div class="flex justify-center gap-4">
                    <Link
                        :href="route('home')"
                        class="px-8 py-3 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl font-semibold hover:bg-gray-300 dark:hover:bg-gray-600 transition-all duration-200"
                    >
                        Back to Home
                    </Link>
                    <button
                        type="button"
                        @click="proceedToCart"
                        :disabled="!selectedPlan"
                        :class="[
                            'px-8 py-3 rounded-xl font-semibold transition-all duration-200',
                            selectedPlan
                                ? 'bg-primary-500 text-white hover:shadow-lg hover:scale-105'
                                : 'bg-gray-300 dark:bg-gray-700 text-gray-500 dark:text-gray-500 cursor-not-allowed'
                        ]"
                    >
                        Continue to Cart
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
