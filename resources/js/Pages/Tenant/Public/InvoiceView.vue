<script setup>
import InvoiceViewContent from '@/Components/Tenant/InvoiceViewContent.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted } from 'vue';

const page = usePage();

let checkoutRefreshTimer = null;

onMounted(() => {
    if (!page.props.flash?.checkout_refresh) {
        return;
    }
    checkoutRefreshTimer = window.setTimeout(() => {
        router.visit(window.location.pathname, {
            replace: true,
            preserveScroll: true,
        });
    }, 4500);
});

onUnmounted(() => {
    if (checkoutRefreshTimer !== null) {
        window.clearTimeout(checkoutRefreshTimer);
    }
});

const props = defineProps({
    record: { type: Object, required: true },
    account: { type: Object, required: true },
    logoUrl: { type: String, default: null },
    enumOptions: { type: Object, default: () => ({}) },
    canPayOnline: { type: Boolean, default: false },
    payOnlineUi: {
        type: Object,
        default: () => ({ card: false, bank: false, codes: [] }),
    },
    paymentConstraints: {
        type: Object,
        default: () => ({
            allow_partial_payment: false,
            minimum_partial_amount: null,
            amount_due: 0,
            amount_paid: 0,
            surcharge_percent: 0,
        }),
    },
});

const title = computed(() =>
    props.record?.display_name ? `Invoice ${props.record.display_name}` : 'Invoice',
);
</script>

<template>
    <Head :title="title" />

    <div class="min-h-screen bg-gray-100">
        <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8 print:max-w-none print:p-0">
            <p class="mb-4 text-center text-sm text-gray-500">
                Invoice from
                {{ account?.settings?.business_name ?? account?.business_name ?? 'your service provider' }}
            </p>

            <div
                v-if="page.props.flash?.checkout_refresh"
                class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950"
                role="status"
            >
                <span class="font-medium">Updating payment status…</span>
                <span class="block mt-1 text-amber-900/90">
                    This page will refresh automatically in a few seconds so you see the latest balance.
                </span>
            </div>
            <div
                v-if="page.props.flash?.success"
                class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800"
                role="status"
            >
                {{ page.props.flash.success }}
            </div>
            <div
                v-if="page.props.flash?.info"
                class="mb-4 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800"
                role="status"
            >
                {{ page.props.flash.info }}
            </div>
            <div
                v-if="page.props.flash?.error"
                class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
                role="alert"
            >
                {{ page.props.flash.error }}
            </div>

            <InvoiceViewContent
                :record="record"
                :account="account"
                :logo-url="logoUrl"
                :enum-options="enumOptions"
                :can-pay-online="canPayOnline"
                :pay-online-ui="payOnlineUi"
                :payment-constraints="paymentConstraints"
                show-portal-promotion
            />
        </div>
    </div>
</template>
