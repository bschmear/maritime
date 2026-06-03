<script setup>
import { onMounted, onUnmounted, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    show: Boolean,
    accountId: {
        type: Number,
        required: true,
    },
    stripeKey: {
        type: String,
        default: null,
    },
});

const emit = defineEmits(['close']);

const stripe = ref(null);
const cardElement = ref(null);
const cardError = ref('');
const processing = ref(false);
const loadingIntent = ref(false);
const setupError = ref('');

const loadStripeScript = () =>
    new Promise((resolve, reject) => {
        if (window.Stripe) {
            resolve();
            return;
        }
        const script = document.createElement('script');
        script.src = 'https://js.stripe.com/v3/';
        script.onload = () => resolve();
        script.onerror = () => reject(new Error('Failed to load Stripe.js'));
        document.head.appendChild(script);
    });

const mountCardElement = () => {
    if (!stripe.value || cardElement.value) {
        return;
    }

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

    cardElement.value.mount('#account-payment-card-element');
    cardElement.value.on('change', (event) => {
        cardError.value = event.error ? event.error.message : '';
    });
};

const destroyCardElement = () => {
    if (cardElement.value) {
        cardElement.value.destroy();
        cardElement.value = null;
    }
};

const initialize = async () => {
    if (!props.stripeKey) {
        setupError.value = 'Billing is not configured.';
        return;
    }

    loadingIntent.value = true;
    setupError.value = '';
    cardError.value = '';

    try {
        await loadStripeScript();
        stripe.value = window.Stripe(props.stripeKey);
        mountCardElement();
    } catch (error) {
        setupError.value = 'Unable to load the payment form. Please refresh and try again.';
    } finally {
        loadingIntent.value = false;
    }
};

watch(
    () => props.show,
    async (open) => {
        if (open) {
            await initialize();
        } else {
            destroyCardElement();
            stripe.value = null;
            cardError.value = '';
            setupError.value = '';
            processing.value = false;
        }
    },
);

onMounted(() => {
    if (props.show) {
        initialize();
    }
});

onUnmounted(() => {
    destroyCardElement();
});

const close = () => {
    if (processing.value) {
        return;
    }
    emit('close');
};

const submit = async () => {
    if (processing.value || !stripe.value || !cardElement.value) {
        return;
    }

    processing.value = true;
    cardError.value = '';

    try {
        const { paymentMethod, error } = await stripe.value.createPaymentMethod({
            type: 'card',
            card: cardElement.value,
        });

        if (error) {
            cardError.value = error.message;
            processing.value = false;
            return;
        }

        router.post(
            route('accounts.billing.payment-method', props.accountId),
            { payment_method: paymentMethod.id },
            {
                preserveScroll: true,
                onSuccess: () => {
                    emit('close');
                },
                onError: (errors) => {
                    cardError.value =
                        errors.payment_method ||
                        errors.stripe ||
                        'Could not update your payment method.';
                },
                onFinish: () => {
                    processing.value = false;
                },
            },
        );
    } catch {
        cardError.value = 'An unexpected error occurred. Please try again.';
        processing.value = false;
    }
};
</script>

<template>
    <div
        v-show="show"
        class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4"
        @click.self="close"
    >
        <div class="relative w-full max-w-md rounded-lg bg-white shadow dark:bg-gray-700">
            <button
                type="button"
                class="absolute right-2.5 top-3 inline-flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 hover:bg-gray-200 hover:text-gray-900 dark:hover:bg-gray-600 dark:hover:text-white"
                @click="close"
            >
                <span class="sr-only">Close</span>
                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>

            <div class="px-6 py-6">
                <h3 class="mb-2 text-xl font-medium text-gray-900 dark:text-white">Update payment method</h3>
                <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                    This card will be used for subscription renewals on this account.
                </p>

                <p v-if="setupError" class="mb-4 text-sm text-red-600 dark:text-red-400">{{ setupError }}</p>
                <p v-else-if="loadingIntent" class="mb-4 text-sm text-gray-500 dark:text-gray-400">Loading secure form…</p>

                <div id="account-payment-card-element" class="rounded-lg border border-gray-300 bg-gray-50 p-3 dark:border-gray-500 dark:bg-gray-600" />
                <p v-if="cardError" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ cardError }}</p>

                <div class="mt-6 flex gap-3">
                    <button
                        type="button"
                        class="flex w-full items-center justify-center rounded-lg bg-blue-700 px-5 py-2.5 text-sm font-medium text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-blue-600 dark:hover:bg-blue-700"
                        :disabled="processing || loadingIntent || !!setupError"
                        @click="submit"
                    >
                        {{ processing ? 'Saving…' : 'Save payment method' }}
                    </button>
                    <button
                        type="button"
                        class="w-full rounded-lg border border-gray-200 bg-white px-5 py-2.5 text-sm font-medium text-gray-500 hover:bg-gray-100 dark:border-gray-500 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                        :disabled="processing"
                        @click="close"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
