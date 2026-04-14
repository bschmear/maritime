<script setup>
import InvoiceViewContent from '@/Components/Tenant/InvoiceViewContent.vue';
import ClientPortalLayout from '@/Layouts/ClientPortalLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();

const props = defineProps({
    record: { type: Object, required: true },
    account: { type: Object, required: true },
    logoUrl: { type: String, default: null },
    enumOptions: { type: Object, default: () => ({}) },
    canPayOnline: { type: Boolean, default: false },
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
    <ClientPortalLayout :title="title">
        <Head :title="`${title} - Customer Portal`" />

        <div class="mb-4">
            <Link
                :href="route('portal.invoices')"
                class="inline-flex items-center gap-1 text-sm font-medium text-primary-600 hover:text-primary-700 no-underline"
            >
                <span class="material-icons text-base">arrow_back</span>
                Back to invoices
            </Link>
        </div>

        <div
            v-if="page.props.flash?.success"
            class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800"
            role="status"
        >
            {{ page.props.flash.success }}
        </div>
        <div
            v-if="page.props.flash?.error"
            class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
            role="alert"
        >
            {{ page.props.flash.error }}
        </div>

        <div class="mx-auto max-w-5xl print:max-w-none">
            <InvoiceViewContent
                :record="record"
                :account="account"
                :logo-url="logoUrl"
                :enum-options="enumOptions"
                :can-pay-online="canPayOnline"
                :payment-constraints="paymentConstraints"
                :show-portal-promotion="false"
            />
        </div>
    </ClientPortalLayout>
</template>
