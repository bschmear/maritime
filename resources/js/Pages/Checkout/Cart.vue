<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    plan: Object,
    billingCycle: String,
    addOns: Array,
    defaultAccountName: String,
    hasExistingAccount: Boolean,
});

const selectedAddOns = ref([]);
const accountName = ref(props.defaultAccountName);

const toggleAddOn = (addOnId) => {
    const index = selectedAddOns.value.indexOf(addOnId);
    if (index > -1) {
        selectedAddOns.value.splice(index, 1);
    } else {
        selectedAddOns.value.push(addOnId);
    }
};

const planPrice = computed(() => {
    return props.billingCycle === 'yearly' 
        ? parseFloat(props.plan.yearly_price) 
        : parseFloat(props.plan.monthly_price);
});

const addOnsTotal = computed(() => {
    return selectedAddOns.value.reduce((total, addOnId) => {
        const addOn = props.addOns.find(a => a.id === addOnId);
        return total + (addOn ? parseFloat(addOn.price) : 0);
    }, 0);
});

const totalPrice = computed(() => {
    return planPrice.value + addOnsTotal.value;
});

const proceedToCheckout = () => {
    router.get(route('checkout.show'), {
        plan_id: props.plan.id,
        billing_cycle: props.billingCycle,
        add_ons: selectedAddOns.value,
        account_name: accountName.value,
    });
};
</script>

<template>
    <AppLayout>
        <Head title="Review Your Order" />

        <!-- Modern gradient background matching homepage -->
        <div class="min-h-screen bg-gradient-to-br from-primary-50 to-secondary-50 dark:from-navy-900 dark:to-navy-800 py-12 sm:py-16">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Modern Header -->
                <div class="text-center mb-12">
                    <div class="inline-flex items-center gap-2 px-4 py-2 bg-secondary-100 dark:bg-secondary-900/50 rounded-full text-secondary-700 dark:text-secondary-300 text-sm font-medium mb-4 backdrop-blur-sm border border-secondary-200/50 dark:border-secondary-700/50">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>Almost there!</span>
                    </div>
                    <h1 class="text-4xl sm:text-5xl font-bold text-gray-900 dark:text-white mb-3 tracking-tight">
                        Review Your Order
                    </h1>
                    <p class="text-lg text-gray-600 dark:text-gray-400 max-w-2xl mx-auto">
                        Review your plan selection and add any extras before checkout
                    </p>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Main Content -->
                    <div class="lg:col-span-2 space-y-6">
                        <!-- Account Name Card -->
                        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 p-6 sm:p-8 transition-all duration-300 hover:shadow-2xl">
                            <div class="flex items-center gap-3 mb-6">
                                <div class="w-10 h-10 bg-primary-500 rounded-xl flex items-center justify-center">
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                </div>
                                <h2 class="text-xl font-bold text-gray-900 dark:text-white">
                                    Account Details
                                </h2>
                            </div>
                            <div>
                                <InputLabel for="account_name" value="Workspace Name" class="text-gray-900 dark:text-white font-semibold mb-2" />
                                <TextInput
                                    id="account_name"
                                    v-model="accountName"
                                    type="text"
                                    class="mt-1 block w-full rounded-xl border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-secondary-500 dark:focus:border-secondary-400 focus:ring-secondary-500 dark:focus:ring-secondary-400"
                                    required
                                />
                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400 flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    This will be the name of your workspace
                                </p>
                            </div>
                        </div>

                        <!-- Selected Plan Card -->
                        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden transition-all duration-300 hover:shadow-2xl">
                            <!-- Plan Header with gradient -->
                            <div class="bg-primary-500 p-6 sm:p-8">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <div class="inline-flex items-center gap-2 px-3 py-1 bg-white/20 backdrop-blur-sm rounded-full text-white text-xs font-semibold mb-3">
                                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                            </svg>
                                            Selected Plan
                                        </div>
                                        <h2 class="text-3xl font-bold text-white mb-2">
                                            {{ plan.name }}
                                        </h2>
                                        <p class="text-secondary-100">
                                            {{ plan.description }}
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-4xl font-bold text-white">
                                            ${{ planPrice }}
                                        </p>
                                        <p class="text-sm text-secondary-100">
                                            {{ billingCycle === 'monthly' ? '/month' : '/year' }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Plan Content -->
                            <div class="p-6 sm:p-8">
                                <!-- Features -->
                                <div class="mb-6">
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wide mb-4 flex items-center gap-2">
                                        <svg class="w-4 h-4 text-secondary-600 dark:text-secondary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        Included features
                                    </p>
                                    <ul class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        <li v-for="(feature, index) in plan.included" :key="index" class="flex items-start gap-3 text-sm text-gray-600 dark:text-gray-400">
                                            <svg class="w-5 h-5 text-secondary-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                            </svg>
                                            {{ feature }}
                                        </li>
                                    </ul>
                                </div>

                                <!-- Seat Limit -->
                                <div class="pt-6 border-t border-gray-200 dark:border-gray-700">
                                    <div class="flex items-center gap-3 p-4 bg-secondary-50 dark:bg-secondary-900/20 rounded-xl">
                                        <div class="w-10 h-10 bg-secondary-100 dark:bg-secondary-900/50 rounded-lg flex items-center justify-center">
                                            <svg class="w-5 h-5 text-secondary-600 dark:text-secondary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-gray-900 dark:text-white">
                                                {{ plan.seat_limit }} {{ plan.seat_limit === 1 ? 'Seat' : 'Seats' }} Included
                                            </p>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                                Perfect for {{ plan.seat_limit === 1 ? 'individual use' : 'team collaboration' }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Add-ons Section -->
                        <div v-if="addOns && addOns.length > 0" class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 p-6 sm:p-8 transition-all duration-300 hover:shadow-2xl">
                            <div class="flex items-center gap-3 mb-6">
                                <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-pink-600 rounded-xl flex items-center justify-center">
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                    </svg>
                                </div>
                                <div>
                                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">
                                        Enhance Your Plan
                                    </h2>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        Optional add-ons to supercharge your experience
                                    </p>
                                </div>
                            </div>
                            <div class="space-y-3">
                                <div
                                    v-for="addOn in addOns"
                                    :key="addOn.id"
                                    @click="toggleAddOn(addOn.id)"
                                    :class="[
                                        'flex items-center justify-between p-5 rounded-xl border-2 cursor-pointer transition-all duration-200',
                                        selectedAddOns.includes(addOn.id)
                                            ? 'border-secondary-600 bg-gradient-to-r from-secondary-50 to-purple-50 dark:from-secondary-900/20 dark:to-purple-900/20 shadow-lg scale-[1.02]'
                                            : 'border-gray-200 dark:border-gray-700 hover:border-secondary-300 dark:hover:border-secondary-600 hover:shadow-md'
                                    ]"
                                >
                                    <div class="flex items-center gap-4">
                                        <div :class="[
                                            'w-6 h-6 rounded-lg border-2 flex items-center justify-center transition-all duration-200',
                                            selectedAddOns.includes(addOn.id)
                                                ? 'bg-gradient-to-br from-secondary-600 to-purple-600 border-secondary-600'
                                                : 'border-gray-300 dark:border-gray-600'
                                        ]">
                                            <svg v-if="selectedAddOns.includes(addOn.id)" class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-gray-900 dark:text-white">{{ addOn.name }}</p>
                                        </div>
                                    </div>
                                    <p class="font-bold text-lg text-gray-900 dark:text-white">
                                        +${{ addOn.price }}<span class="text-sm text-gray-500 dark:text-gray-400">/mo</span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Order Summary Sidebar -->
                    <div class="lg:col-span-1">
                        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden sticky top-8">
                            <!-- Summary Header -->
                            <div class="bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-700 dark:to-gray-800 p-6 border-b border-gray-200 dark:border-gray-700">
                                <h2 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                    <svg class="w-5 h-5 text-secondary-600 dark:text-secondary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                    </svg>
                                    Order Summary
                                </h2>
                            </div>

                            <div class="p-6">
                                <div class="space-y-4 mb-6">
                                    <div class="flex justify-between items-center">
                                        <span class="text-gray-600 dark:text-gray-400">{{ plan.name }} Plan</span>
                                        <span class="font-semibold text-gray-900 dark:text-white">${{ planPrice }}</span>
                                    </div>

                                    <div v-if="selectedAddOns.length > 0" class="border-t border-gray-200 dark:border-gray-700 pt-4 space-y-3">
                                        <div
                                            v-for="addOnId in selectedAddOns"
                                            :key="addOnId"
                                            class="flex justify-between items-center text-sm"
                                        >
                                            <span class="text-gray-600 dark:text-gray-400 flex items-center gap-2">
                                                <svg class="w-4 h-4 text-secondary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                                </svg>
                                                {{ addOns.find(a => a.id === addOnId)?.name }}
                                            </span>
                                            <span class="font-semibold text-gray-900 dark:text-white">
                                                ${{ addOns.find(a => a.id === addOnId)?.price }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div class="border-t-2 border-gray-200 dark:border-gray-700 pt-6 mb-6">
                                    <div class="flex justify-between items-baseline mb-2">
                                        <span class="text-lg font-bold text-gray-900 dark:text-white">Total</span>
                                        <div class="text-right">
                                            <p class="text-3xl font-bold text-secondary-500">
                                                ${{ totalPrice.toFixed(2) }}
                                            </p>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                                {{ billingCycle === 'monthly' ? 'per month' : 'per year' }}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-3">
                                    <button
                                        @click="proceedToCheckout"
                                        class="w-full px-6 py-4 bg-primary-500 hover:from-primary-700 hover:to-primary-700 text-white font-semibold rounded-xl transition-all duration-200 shadow-lg hover:shadow-xl hover:scale-105 flex items-center justify-center gap-2"
                                    >
                                        <span>Proceed to Checkout</span>
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                                        </svg>
                                    </button>

                                    <Link
                                        :href="route('checkout.plans', { billing: billingCycle })"
                                        class="block w-full text-center px-4 py-3 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white font-medium rounded-xl border-2 border-gray-200 dark:border-gray-700 hover:border-secondary-300 dark:hover:border-secondary-600 transition-all duration-200"
                                    >
                                        Change Plan
                                    </Link>
                                </div>

                                <!-- Trust Badges -->
                                <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700 space-y-3">
                                    <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                                        <svg class="w-5 h-5 text-secondary-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                        </svg>
                                        <span>14-day free trial</span>
                                    </div>
                                    <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                                        <svg class="w-5 h-5 text-secondary-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                        </svg>
                                        <span>Cancel anytime</span>
                                    </div>
                                    <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                                        <svg class="w-5 h-5 text-secondary-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                        </svg>
                                        <span>No credit card required</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
