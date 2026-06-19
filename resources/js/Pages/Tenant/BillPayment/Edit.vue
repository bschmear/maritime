<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import Form from '@/Components/Tenant/Form.vue';
import { useQuickBooksApSyncOverlay } from '@/composables/useQuickBooksApSyncOverlay';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import { buildResourceRouteParams } from '@/Utils/resourceRoutes.js';

const page = usePage();
const { handleCreateFlash } = useQuickBooksApSyncOverlay();

watch(
    () => page.props.flash,
    (flash) => handleCreateFlash(flash),
    { immediate: true, deep: true },
);

const props = defineProps({
    record: { type: Object, required: true },
    recordType: { type: String, default: 'bill-payments' },
    formSchema: { type: Object, required: true },
    fieldsSchema: { type: Object, required: true },
    enumOptions: { type: Object, default: () => ({}) },
    account: { type: Object, default: null },
    timezones: { type: Array, default: () => [] },
    quickbooksApSync: { type: Object, default: null },
});

const paymentLabel = computed(() => props.record.display_name || `Payment #${props.record.id}`);

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Bill payments', href: route('bill-payments.index') },
    {
        label: paymentLabel.value,
        href: route('bill-payments.show', buildResourceRouteParams('bill-payments', props.record.id)),
    },
    { label: 'Edit' },
]);

const cancel = () => {
    router.visit(route('bill-payments.show', buildResourceRouteParams('bill-payments', props.record.id)));
};
</script>

<template>
    <Head :title="`Edit ${paymentLabel}`" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <h2 class="mt-4 text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Edit {{ paymentLabel }}
                </h2>
            </div>
        </template>

        <div class="mx-auto max-w-3xl px-4 py-6 sm:px-6 lg:px-8">
            <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                <Form
                    mode="edit"
                    record-type="bill-payments"
                    record-title="Bill payment"
                    :schema="formSchema"
                    :fields-schema="fieldsSchema"
                    :enum-options="enumOptions"
                    :record="record"
                    :account="account"
                    :timezones="timezones"
                    :quickbooks-ap-sync="quickbooksApSync"
                    :record-identifier="record.id"
                    :redirect-after-update="route('bill-payments.show', buildResourceRouteParams('bill-payments', record.id))"
                    @cancel="cancel"
                />
            </div>
        </div>
    </TenantLayout>
</template>
