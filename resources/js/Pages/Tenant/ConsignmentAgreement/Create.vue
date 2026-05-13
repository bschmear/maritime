<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import ConsignmentAgreementForm from '@/Components/Tenant/ConsignmentAgreementForm.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    formSchema: { type: Object, default: null },
    fieldsSchema: { type: Object, default: () => ({}) },
    enumOptions: { type: Object, default: () => ({}) },
    account: { type: Object, default: null },
    timezones: { type: Array, default: () => [] },
    prefill: { type: Object, default: () => ({}) },
});

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Consignment agreements', href: route('consignmentagreements.index') },
    { label: 'New' },
]);

const handleCancel = () => {
    router.visit(route('consignmentagreements.index'));
};
</script>

<template>
    <Head title="New consignment agreement" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <h2 class="mt-4 text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    New consignment agreement
                </h2>
            </div>
        </template>

        <div class="mx-auto flex w-full max-w-5xl flex-col space-y-6">
            <ConsignmentAgreementForm
                mode="create"
                :prefill="prefill"
                :fields-schema="fieldsSchema"
                :enum-options="enumOptions"
                @cancel="handleCancel"
            />
        </div>
    </TenantLayout>
</template>
