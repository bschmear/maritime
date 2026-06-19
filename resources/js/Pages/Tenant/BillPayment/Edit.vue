<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import BillPaymentForm from '@/Components/Tenant/BillPaymentForm.vue';
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
    editRestrictions: {
        type: Object,
        default: () => ({
            restricted: false,
            allowedFields: ['vendor_id'],
            reason: null,
        }),
    },
});

const paymentLabel = computed(() => props.record.display_name || `Payment #${props.record.id}`);
const isEditRestricted = computed(() => !!props.editRestrictions?.restricted);
const pageTitle = computed(() => (isEditRestricted.value ? `Link records · ${paymentLabel.value}` : `Edit ${paymentLabel.value}`));

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Bill payments', href: route('bill-payments.index') },
    {
        label: paymentLabel.value,
        href: route('bill-payments.show', buildResourceRouteParams('bill-payments', props.record.id)),
    },
    { label: 'Edit' },
]);

const handleSaved = () => {
    router.visit(route('bill-payments.show', buildResourceRouteParams('bill-payments', props.record.id)));
};

const handleCancelled = () => {
    router.visit(route('bill-payments.show', buildResourceRouteParams('bill-payments', props.record.id)));
};
</script>

<template>
    <Head :title="pageTitle" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <h2 class="mt-4 text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    {{ isEditRestricted ? `Link records · ${paymentLabel}` : `Edit ${paymentLabel}` }}
                </h2>
            </div>
        </template>

        <div class="mx-auto flex w-full max-w-4xl flex-col space-y-6 px-4 py-6 sm:px-6 lg:px-8">
            <BillPaymentForm
                :record="record"
                :form-schema="formSchema"
                :fields-schema="fieldsSchema"
                :enum-options="enumOptions"
                :edit-restrictions="editRestrictions"
                mode="edit"
                record-type="bill-payments"
                record-title="Bill payment"
                @saved="handleSaved"
                @cancelled="handleCancelled"
            />
        </div>
    </TenantLayout>
</template>
