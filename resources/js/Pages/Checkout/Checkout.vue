<script setup>
import { ref, onMounted } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    plan: Object,
    billingCycle: String,
    stripeKey: String,
    intent: Object,
    accountName: String,
});

const stripe = ref(null);
const cardElement = ref(null);
const processing = ref(false);
const cardError = ref('');

const form = useForm({
    plan_id: props.plan.id,
    billing_cycle: props.billingCycle,
    payment_method: '',
    account_name: props.accountName,
});

onMounted(async () => {
    // Load Stripe.js
    if (!window.Stripe) {
        const script = document.createElement('script');
        script.src = 'https://js.stripe.com/v3/';
        script.onload = initializeStripe;
        document.head.appendChild(script);
    } else {
        initializeStripe();
    }
});

const initializeStripe = () => {
    stripe.value = window.Stripe(props.stripeKey);

    // Detect if dark mode is active
    const isDarkMode = document.documentElement.classList.contains('dark');

    const elements = stripe.value.elements();

    cardElement.value = elements.create('card', {
        style: {
            base: {
                fontSize: '16px',
                color: isDarkMode ? '#f9fafb' : '#1f2937',
                fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
                '::placeholder': {
                    color: isDarkMode ? '#6b7280' : '#9ca3af',
                },
            },
            invalid: {
                color: '#ef4444',
                iconColor: '#ef4444',
            },
        },
    });

    cardElement.value.mount('#card-element');

    cardElement.value.on('change', (event) => {
        cardError.value = event.error ? event.error.message : '';
    });
};

const submit = async () => {
    if (processing.value) return;

    processing.value = true;
    cardError.value = '';

    try {
        // Create payment method
        const { paymentMethod, error } = await stripe.value.createPaymentMethod({
            type: 'card',
            card: cardElement.value,
        });

        if (error) {
            cardError.value = error.message;
            processing.value = false;
            return;
        }

        form.payment_method = paymentMethod.id;

        // Submit the form
        form.post(route('checkout.process'), {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
            },
        });
    } catch (error) {
        cardError.value = 'An error occurred. Please try again.';
        processing.value = false;
    }
};

const planPrice = props.billingCycle === 'yearly'
    ? parseFloat(props.plan.yearly_price)
    : parseFloat(props.plan.monthly_price);
</script>

<template>
    <AppLayout>
        <Head title="Checkout" />

        <!-- Modern gradient background matching cart page -->
        <div class="min-h-screen bg-gradient-to-br from-primary-50 to-secondary-50 dark:from-navy-900 dark:to-navy-800 py-12 sm:py-16">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Modern Header -->
                <div class="text-center mb-12">
                    <div class="inline-flex items-center gap-2 px-4 py-2 bg-secondary-100 dark:bg-secondary-900/50 rounded-full text-secondary-700 dark:text-secondary-300 text-sm font-medium mb-4 backdrop-blur-sm border border-secondary-200/50 dark:border-secondary-700/50">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        <span>Secure checkout</span>
                    </div>
                    <h1 class="text-4xl sm:text-5xl font-bold text-gray-900 dark:text-white mb-3 tracking-tight">
                        Complete Your Purchase
                    </h1>
                    <p class="text-lg text-gray-600 dark:text-gray-400 max-w-2xl mx-auto">
                        Enter your payment details to start your 14-day free trial
                    </p>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Checkout Form -->
                    <div class="lg:col-span-2">
                        <form @submit.prevent="submit" class="space-y-6">
                            <!-- Account Details -->
                            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 p-6 sm:p-8 transition-all duration-300 hover:shadow-2xl">
                                <div class="flex items-center gap-3 mb-6">
                                    <div class="w-10 h-10 bg-primary-500 rounded-xl flex items-center justify-center">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                        </svg>
                                    </div>
                                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">
                                        Account Details
                                    </h2>
                                </div>
                                <div class="flex items-center gap-4 p-4 bg-gradient-to-r from-primary-50 to-secondary-50 dark:from-secondary-900/20 dark:to-primary-900/20 rounded-xl border border-secondary-100 dark:border-secondary-800">
                                    <div class="flex-shrink-0 w-12 h-12 bg-white dark:bg-gray-700 rounded-xl flex items-center justify-center shadow-sm">
                                        <svg class="w-6 h-6 text-secondary-600 dark:text-secondary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="font-semibold text-gray-900 dark:text-white">{{ accountName }}</p>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">Your workspace will be created after payment</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Payment Details -->
                            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 p-6 sm:p-8 transition-all duration-300 hover:shadow-2xl">
                                <div class="flex items-center gap-3 mb-6">
                                    <div class="w-10 h-10 bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl flex items-center justify-center">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                                        </svg>
                                    </div>
                                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">
                                        Payment Details
                                    </h2>
                                </div>

                                <div>
                                    <InputLabel for="card-element" value="Card Information" class="text-gray-900 dark:text-white font-semibold mb-2" />
                                    <div
                                        id="card-element"
                                        class="mt-1 block w-full px-4 py-4 border-2 border-gray-200 dark:border-gray-700 rounded-xl shadow-sm focus-within:ring-2 focus-within:ring-secondary-500 focus-within:border-secondary-500 dark:focus-within:ring-secondary-400 dark:focus-within:border-secondary-400 bg-white dark:bg-gray-900 transition-all duration-200 hover:border-gray-300 dark:hover:border-gray-600"
                                    ></div>
                                    <p v-if="cardError" class="mt-2 text-sm text-red-600 dark:text-red-400 flex items-center gap-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        {{ cardError }}
                                    </p>
                                    <InputError :message="form.errors.payment_method" class="mt-2" />
                                </div>

                                <!-- Security Notice -->
                                <div class="mt-6 p-4 bg-green-50 dark:bg-green-900/20 rounded-xl border border-green-200 dark:border-green-800">
                                    <div class="flex items-start gap-3">
                                        <div class="flex-shrink-0 w-8 h-8 bg-green-100 dark:bg-green-900/50 rounded-lg flex items-center justify-center">
                                            <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-green-900 dark:text-green-100 text-sm mb-1">Secure Payment</p>
                                            <p class="text-sm text-green-700 dark:text-green-300">Your payment information is encrypted and secure. We never store your card details.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Error Message -->
                            <div v-if="form.errors.error" class="bg-red-50 dark:bg-red-900/20 border-2 border-red-200 dark:border-red-800 rounded-xl p-4 sm:p-5">
                                <div class="flex items-start gap-3">
                                    <svg class="w-5 h-5 text-red-600 dark:text-red-400 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                    </svg>
                                    <p class="text-sm text-red-600 dark:text-red-400 font-medium">
                                        {{ form.errors.error }}
                                    </p>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4">
                                <Link
                                    :href="route('checkout.cart', { plan_id: plan.id, billing_cycle: billingCycle })"
                                    class="inline-flex items-center justify-center gap-2 px-6 py-3 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white font-medium rounded-xl border-2 border-gray-200 dark:border-gray-700 hover:border-secondary-300 dark:hover:border-secondary-600 transition-all duration-200"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                                    </svg>
                                    <span>Back to Cart</span>
                                </Link>

                                <button
                                    type="submit"
                                    :disabled="processing || form.processing"
                                    class="px-8 py-4 bg-primary-500 hover:bg-primary-700 text-white font-semibold rounded-xl transition-all duration-200 shadow-lg hover:shadow-xl hover:scale-105 flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:scale-100"
                                >
                                    <span v-if="processing || form.processing">
                                        <svg class="animate-spin h-5 w-5 text-white inline-block mr-2" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        Processing...
                                    </span>
                                    <span v-else class="flex items-center space-x-2">
                                        <span>Start Free Trial</span>
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                                        </svg>
                                    </span>
                                </button>
                            </div>
                        </form>
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
                                    <div>
                                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">Plan</p>
                                        <p class="font-semibold text-gray-900 dark:text-white">{{ plan.name }}</p>
                                    </div>

                                    <div>
                                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">Billing</p>
                                        <p class="font-semibold text-gray-900 dark:text-white capitalize">{{ billingCycle }}</p>
                                    </div>

                                    <div class="border-t border-gray-200 dark:border-gray-700 pt-4 space-y-3">
                                        <div class="flex justify-between items-center">
                                            <span class="text-gray-600 dark:text-gray-400">Subtotal</span>
                                            <span class="font-semibold text-gray-900 dark:text-white">${{ planPrice }}</span>
                                        </div>
                                        <div class="flex justify-between items-center p-3 bg-green-50 dark:bg-green-900/20 rounded-lg">
                                            <span class="font-medium text-green-700 dark:text-green-300">14-day trial</span>
                                            <span class="font-bold text-green-600 dark:text-green-400">FREE</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="border-t-2 border-gray-200 dark:border-gray-700 pt-6 mb-6">
                                    <div class="flex justify-between items-baseline mb-2">
                                        <span class="text-lg font-bold text-gray-900 dark:text-white">Due Today</span>
                                        <div class="text-right">
                                            <p class="text-3xl font-bold text-secondary-500">
                                                $0.00
                                            </p>
                                        </div>
                                    </div>
                                    <p class="text-xs text-gray-600 dark:text-gray-400 mt-2 p-3 bg-gray-50 dark:bg-gray-900/50 rounded-lg">
                                        You'll be charged ${{ planPrice }} {{ billingCycle === 'monthly' ? 'per month' : 'per year' }} after your trial ends
                                    </p>
                                </div>

                                <!-- Features -->
                                <!-- <div class="border-t border-gray-200 dark:border-gray-700 pt-6 mb-6">
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wide mb-4 flex items-center gap-2">
                                        <svg class="w-4 h-4 text-secondary-600 dark:text-secondary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        What's included
                                    </p>
                                    <ul class="space-y-2">
                                        <li v-for="(feature, index) in plan.included" :key="index" class="flex items-start gap-2 text-sm text-gray-600 dark:text-gray-400">
                                            <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                            </svg>
                                            {{ feature }}
                                        </li>
                                    </ul>
                                </div> -->

                                <!-- Trust Badges -->
                                <div class="border-t border-gray-200 dark:border-gray-700 pt-6 space-y-3">
                                    <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                                        <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                        </svg>
                                        <span>14-day free trial</span>
                                    </div>
                                    <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                                        <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                        </svg>
                                        <span>Cancel anytime</span>
                                    </div>
                                    <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                                        <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
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
