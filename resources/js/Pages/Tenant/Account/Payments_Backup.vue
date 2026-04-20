<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    current_processor: {
        type: String,
        default: null,
    },
    stripe: {
        type: Object,
        required: true,
    },
    quickbooks: {
        type: Object,
        required: true,
    },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);

const currentProcessorLabel = computed(() => {
    if (props.current_processor === 'quickbooks') {
        return 'QuickBooks Online';
    }
    if (props.current_processor === 'stripe') {
        return 'Stripe';
    }
    return 'None';
});

const currentStatusDescription = computed(() => {
    if (props.current_processor === 'quickbooks') {
        return props.quickbooks?.company_name
            ? `Company: ${props.quickbooks.company_name} (${props.quickbooks.environment || 'sandbox'})`
            : `Environment: ${props.quickbooks?.environment || 'sandbox'}`;
    }
    if (props.current_processor === 'stripe') {
        return props.stripe?.status_label || 'Stripe';
    }
    return 'Connect Stripe or QuickBooks on the pages below. Only one can be active at a time.';
});
</script>

<template>
    <Head title="Payments" />

    <TenantLayout>
        <template #header>
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0 flex-1">
                    <Link
                        :href="route('account.index')"
                        class="mb-2 inline-flex items-center gap-1 text-sm font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400"
                    >
                        <span class="material-icons text-base">arrow_back</span>
                        Account
                    </Link>
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                        Payments
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Choose how customers pay invoices online. Open a processor to connect, view status, and manage settings.
                    </p>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-4xl space-y-6">
            <div
                v-if="flashSuccess"
                class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900/40 dark:bg-green-900/20 dark:text-green-200"
            >
                {{ flashSuccess }}
            </div>
            <div
                v-if="flashError"
                class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900/40 dark:bg-red-900/20 dark:text-red-200"
            >
                {{ flashError }}
            </div>

            <div
                class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:shadow-none"
            >
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Current processor
                </p>
                <div class="mt-2 flex flex-wrap items-baseline gap-x-3 gap-y-1">
                    <span class="text-lg font-semibold text-gray-900 dark:text-white">{{ currentProcessorLabel }}</span>
                    <span
                        v-if="current_processor"
                        class="inline-flex items-center rounded-md bg-primary-50 px-2 py-0.5 text-xs font-medium text-primary-800 dark:bg-primary-900/30 dark:text-primary-200"
                    >
                        Active
                    </span>
                    <span
                        v-else
                        class="inline-flex items-center rounded-md bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300"
                    >
                        Not configured
                    </span>
                </div>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                    {{ currentStatusDescription }}
                </p>
                <p
                    v-if="stripe?.status_label && current_processor !== 'quickbooks'"
                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                >
                    Stripe status: <span class="font-medium text-gray-800 dark:text-gray-200">{{ stripe.status_label }}</span>
                </p>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <Link
                    :href="route('account.payments.stripe')"
                    class="group flex flex-col rounded-xl border border-gray-200 bg-white p-6 shadow-sm transition hover:border-primary-300 hover:shadow-md dark:border-gray-700 dark:bg-gray-800 dark:hover:border-primary-700"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary-50 dark:bg-primary-900/30">
                                <span class="material-icons text-[20px] text-primary-600 dark:text-primary-400">account_balance</span>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-900 dark:text-white">Stripe</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Connect Express · Card &amp; bank</p>
                            </div>
                        </div>
                        <span class="material-icons text-gray-400 transition group-hover:text-primary-600 dark:group-hover:text-primary-400">chevron_right</span>
                    </div>
                    <p class="mt-4 text-sm text-gray-600 dark:text-gray-300">
                        {{ stripe?.status_label || 'Not connected' }}
                    </p>
                </Link>

                <Link
                    :href="route('account.payments.quickbooks')"
                    class="group flex flex-col rounded-xl border border-gray-200 bg-white p-6 shadow-sm transition hover:border-emerald-400 hover:shadow-md dark:border-gray-700 dark:bg-gray-800 dark:hover:border-emerald-600"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-50 dark:bg-emerald-900/30">
                                <span class="material-icons text-[20px] text-emerald-600 dark:text-emerald-400">receipt_long</span>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-900 dark:text-white">QuickBooks Online</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Intuit OAuth · Invoicing</p>
                            </div>
                        </div>
                        <span class="material-icons text-gray-400 transition group-hover:text-emerald-600 dark:group-hover:text-emerald-400">chevron_right</span>
                    </div>
                    <p class="mt-4 text-sm text-gray-600 dark:text-gray-300">
                        {{ quickbooks?.status_label || 'Not connected' }}
                        <span v-if="quickbooks?.company_name" class="block text-xs text-gray-500 dark:text-gray-400">
                            {{ quickbooks.company_name }}
                        </span>
                    </p>
                </Link>
            </div>
        </div>
    </TenantLayout>
</template>
