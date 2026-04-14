<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import PaymentForm from '@/Components/Tenant/PaymentForm.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    payment: { type: Object, required: true },
    fieldsSchema: { type: Object, default: () => ({}) },
    enumOptions: { type: Object, default: () => ({}) },
});

function paidAtForInput(paidAt) {
    if (!paidAt) return '';
    const s = typeof paidAt === 'string' ? paidAt : String(paidAt);
    if (s.length >= 16) return s.slice(0, 16);
    return '';
}

const form = useForm({
    payment_method_code: props.payment.payment_method_code ?? '',
    processor: props.payment.processor ?? 'manual',
    reference_number: props.payment.reference_number ?? '',
    memo: props.payment.memo ?? '',
    paid_at: paidAtForInput(props.payment.paid_at),
});

const title = computed(() =>
    props.payment?.sequence != null ? `Edit payment #${props.payment.sequence}` : 'Edit payment',
);

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Payments', href: route('payments.index') },
    { label: props.payment?.sequence != null ? `Payment #${props.payment.sequence}` : 'Payment', href: route('payments.show', props.payment.id) },
    { label: 'Edit' },
]);

function stripEmptyPaidAt(data) {
    const out = { ...data };
    if (out.paid_at === '' || out.paid_at == null) {
        out.paid_at = null;
    }

    return out;
}

function submit() {
    form.transform(stripEmptyPaidAt).put(route('payments.update', props.payment.id));
}
</script>

<template>
    <Head :title="title" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <h2 class="mt-4 text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    {{ title }}
                </h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Update logging details. Amount and invoice are fixed once recorded.
                </p>
            </div>
        </template>

        <div class="w-full max-w-3xl p-4 pb-12">
            <form class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-100 dark:border-gray-700 p-6 space-y-6" @submit.prevent="submit">
                <PaymentForm
                    mode="edit"
                    :form="form"
                    :enum-options="enumOptions"
                    :fields-schema="fieldsSchema"
                    :payment="payment"
                />

                <div class="flex flex-wrap items-center justify-between gap-3 pt-2 border-t border-gray-100 dark:border-gray-700">
                    <Link
                        :href="route('payments.show', payment.id)"
                        class="text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white"
                    >
                        Cancel
                    </Link>
                    <button
                        type="submit"
                        class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg disabled:opacity-50"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Saving…' : 'Save changes' }}
                    </button>
                </div>
            </form>
        </div>
    </TenantLayout>
</template>
