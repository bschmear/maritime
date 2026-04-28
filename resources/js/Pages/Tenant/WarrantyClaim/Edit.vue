<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import WarrantyClaimForm from '@/Components/Tenant/WarrantyClaimForm.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    record: { type: Object, required: true },
    recordType: { type: String, default: 'warrantyclaims' },
    recordTitle: { type: String, default: 'Warranty claim' },
    domainName: { type: String, default: 'WarrantyClaim' },
    formSchema: { type: Object, required: true },
    fieldsSchema: { type: Object, required: true },
    enumOptions: { type: Object, default: () => ({}) },
    account: { type: Object, default: null },
    timezones: { type: Array, default: () => [] },
    imageUrls: { type: Object, default: () => ({}) },
    availableSpecs: { type: Array, default: () => [] },
});

const claimLabel = computed(() => {
    const r = props.record;
    return (r.claim_number && String(r.claim_number).trim()) || `Claim #${r.id}`;
});

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Warranty claims', href: route('warrantyclaims.index') },
    { label: claimLabel.value, href: route('warrantyclaims.show', props.record.id) },
    { label: 'Edit' },
]);

const handleCancel = () => {
    router.visit(route('warrantyclaims.show', props.record.id));
};
</script>

<template>
    <Head :title="`Edit ${claimLabel}`" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <h2 class="mt-4 text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Edit {{ claimLabel }}
                </h2>
            </div>
        </template>

        <WarrantyClaimForm
            :record="record"
            mode="edit"
            :form-schema="formSchema"
            :fields-schema="fieldsSchema"
            :enum-options="enumOptions"
            @cancel="handleCancel"
        />
    </TenantLayout>
</template>
