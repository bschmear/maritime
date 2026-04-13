<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import InvoiceForm from '@/Components/Tenant/InvoiceForm.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    record: { type: Object, required: true },
    formSchema: { type: Object, required: true },
    fieldsSchema: { type: Object, required: true },
    enumOptions: { type: Object, default: () => ({}) },
    account: { type: Object, default: null },
    timezones: { type: Array, default: () => [] },
    enabledPaymentMethods: { type: Array, default: () => [] },
});

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Invoices', href: route('invoices.index') },
    { label: `Invoice #${props.record.sequence ?? props.record.id}`, href: route('invoices.show', props.record.id) },
    { label: 'Edit' },
]);

const handleCancel = () => {
    router.visit(route('invoices.show', props.record.id));
};
</script>

<template>
    <Head :title="`Edit Invoice #${record.sequence ?? record.id}`" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200 mt-4">
                    Edit Invoice #{{ record.sequence ?? record.id }}
                </h2>
            </div>
        </template>

        <div class="w-full flex flex-col space-y-6 mx-auto">
            <InvoiceForm
                :record="record"
                :form-schema="formSchema"
                :fields-schema="fieldsSchema"
                :enum-options="enumOptions"
                :account="account"
                :timezones="timezones"
                :enabled-payment-methods="enabledPaymentMethods"
                mode="edit"
                @cancel="handleCancel"
            />
        </div>
    </TenantLayout>
</template>