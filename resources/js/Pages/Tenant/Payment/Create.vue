<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import PaymentForm from '@/Components/Tenant/PaymentForm.vue';
import PaymentInvoicePicker from '@/Components/Tenant/PaymentInvoicePicker.vue';
import PaymentCreateInvoiceSummary from '@/Components/Tenant/PaymentCreateInvoiceSummary.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, onMounted } from 'vue';

const props = defineProps({
    fieldsSchema: { type: Object, default: () => ({}) },
    enumOptions: { type: Object, default: () => ({}) },
    invoiceEnumOptions: { type: Array, default: () => [] },
    /** When opening with ?invoice_id= for an open invoice, skip to payment details. */
    prefillInvoice: { type: Object, default: null },
});

const step = ref(1);
const selectedInvoice = ref(null);

const form = useForm({
    invoice_id: null,
    amount: '',
    payment_method_code: 'check',
    processor: 'manual',
    reference_number: '',
    memo: '',
    paid_at: '',
    apply_to_invoice: true,
});

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Payments', href: route('payments.index') },
    { label: 'Log payment' },
]);

function amountDueAsFormString(inv) {
    if (inv?.amount_due == null || inv.amount_due === '') {
        return '';
    }
    const n = Number(inv.amount_due);
    return Number.isNaN(n) ? '' : n.toFixed(2);
}

function applyInvoiceSelection(inv) {
    selectedInvoice.value = inv;
    form.invoice_id = inv.id;
    form.amount = amountDueAsFormString(inv);
    step.value = 2;
}

onMounted(() => {
    if (props.prefillInvoice) {
        applyInvoiceSelection(props.prefillInvoice);
    }
});

function onInvoiceSelected(inv) {
    applyInvoiceSelection(inv);
}

function goBackToInvoicePicker() {
    selectedInvoice.value = null;
    form.invoice_id = null;
    form.amount = '';
    form.clearErrors();
    step.value = 1;
}

function stripEmptyPaidAt(data) {
    const out = { ...data };
    if (out.paid_at === '' || out.paid_at == null) {
        delete out.paid_at;
    }

    return out;
}

function submit() {
    form.transform(stripEmptyPaidAt).post(route('payments.store'));
}
</script>

<template>
    <Head title="Log payment" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <h2 class="mt-4 text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Log payment
                </h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400 max-w-2xl">
                    Record how a payment was received (cash, check, card, Stripe, QuickBooks, etc.). Choose an open invoice first, then enter payment details.
                </p>
            </div>
        </template>

        <div class="w-full max-w-6xl mx-auto p-4 pb-12">
            <!-- Step indicator -->
            <div class="flex items-center justify-center gap-2 sm:gap-4 mb-8">
                <div class="flex items-center gap-2 min-w-0">
                    <span
                        class="shrink-0 w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold transition-colors"
                        :class="step === 1
                            ? 'bg-primary-600 text-white'
                            : 'bg-green-500 text-white'"
                    >
                        <span v-if="step === 1">1</span>
                        <span v-else class="material-icons text-[18px]">check</span>
                    </span>
                    <span
                        class="text-sm font-medium truncate"
                        :class="step === 1 ? 'text-gray-900 dark:text-white' : 'text-gray-500 dark:text-gray-400'"
                    >
                        Invoice
                    </span>
                </div>
                <div class="h-px w-8 sm:w-16 bg-gray-200 dark:bg-gray-600 shrink-0" />
                <div class="flex items-center gap-2 min-w-0">
                    <span
                        class="shrink-0 w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold transition-colors"
                        :class="step === 2
                            ? 'bg-primary-600 text-white'
                            : 'bg-gray-200 dark:bg-gray-600 text-gray-500 dark:text-gray-400'"
                    >
                        2
                    </span>
                    <span
                        class="text-sm font-medium truncate"
                        :class="step === 2 ? 'text-gray-900 dark:text-white' : 'text-gray-500 dark:text-gray-400'"
                    >
                        Payment details
                    </span>
                </div>
            </div>

            <!-- Step 1: invoice picker -->
            <div v-if="step === 1" class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-100 dark:border-gray-700 p-6 sm:p-10">
                <PaymentInvoicePicker
                    :invoice-enum-options="invoiceEnumOptions"
                    @select="onInvoiceSelected"
                />
            </div>

            <!-- Step 2: form + invoice summary -->
            <div
                v-else
                class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_18rem] xl:grid-cols-[minmax(0,1fr)_20rem] gap-6 lg:gap-8 items-start"
            >
                <form
                    class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-100 dark:border-gray-700 p-6 space-y-6 order-2 lg:order-1"
                    @submit.prevent="submit"
                >
                    <PaymentForm
                        mode="create"
                        :form="form"
                        :enum-options="enumOptions"
                        :fields-schema="fieldsSchema"
                        :picked-invoice="selectedInvoice"
                        allow-change-invoice
                        @change-invoice="goBackToInvoicePicker"
                    />

                    <div class="flex flex-wrap items-center justify-between gap-3 pt-2 border-t border-gray-100 dark:border-gray-700">
                        <Link
                            :href="route('payments.index')"
                            class="text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white"
                        >
                            Cancel
                        </Link>
                        <button
                            type="submit"
                            class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg disabled:opacity-50"
                            :disabled="form.processing"
                        >
                            {{ form.processing ? 'Saving…' : 'Save payment' }}
                        </button>
                    </div>
                </form>

                <div class="order-1 lg:order-2">
                    <PaymentCreateInvoiceSummary
                        v-if="selectedInvoice"
                        :invoice="selectedInvoice"
                        :invoice-enum-options="invoiceEnumOptions"
                    />
                </div>
            </div>
        </div>
    </TenantLayout>
</template>
