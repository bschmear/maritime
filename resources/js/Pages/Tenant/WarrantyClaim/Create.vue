<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import WarrantyClaimForm from '@/Components/Tenant/WarrantyClaimForm.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    recordType: { type: String, default: 'warrantyclaims' },
    recordTitle: { type: String, default: 'Warranty claim' },
    domainName: { type: String, default: 'WarrantyClaim' },
    formSchema: { type: Object, required: true },
    fieldsSchema: { type: Object, required: true },
    enumOptions: { type: Object, default: () => ({}) },
    account: { type: Object, default: null },
    timezones: { type: Array, default: () => [] },
});

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Warranty claims', href: route('warrantyclaims.index') },
    { label: 'New' },
]);

const handleCancel = () => {
    router.visit(route('warrantyclaims.index'));
};
</script>

<template>
    <Head title="New warranty claim" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <h2 class="mt-4 text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    New warranty claim
                </h2>
            </div>
        </template>

        <WarrantyClaimForm
            :record="null"
            mode="create"
            :form-schema="formSchema"
            :fields-schema="fieldsSchema"
            :enum-options="enumOptions"
            @cancel="handleCancel"
        />
    </TenantLayout>
</template>
