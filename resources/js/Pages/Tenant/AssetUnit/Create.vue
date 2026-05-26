<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import AssetUnitForm from '@/Components/Tenant/AssetUnitForm.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    recordType: { type: String, default: 'assetunits' },
    recordTitle: { type: String, default: 'Asset Unit' },
    formSchema: { type: Object, required: true },
    fieldsSchema: { type: Object, default: () => ({}) },
    enumOptions: { type: Object, default: () => ({}) },
    timezones: { type: Array, default: () => [] },
    account: { type: Object, default: null },
    prefill: { type: Object, default: () => ({}) },
});

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Asset units', href: route('assetunits.index') },
    { label: 'New' },
]);

const handleCancel = () => {
    router.visit(route('assetunits.index'));
};

const handleCreated = (recordId) => {
    if (recordId) {
        router.visit(route('assetunits.show', recordId));
    }
};
</script>

<template>
    <Head title="New asset unit" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="mt-4">
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        New asset unit
                    </h2>
                </div>
            </div>
        </template>

        <div class="mx-auto flex w-full flex-col space-y-6">
            <AssetUnitForm
                :schema="formSchema"
                :fields-schema="fieldsSchema"
                :record-type="recordType"
                :record-title="recordTitle"
                :enum-options="enumOptions"
                :timezones="timezones"
                :prefill="prefill"
                mode="create"
                @cancelled="handleCancel"
                @saved="({ recordId }) => handleCreated(recordId)"
            />
        </div>
    </TenantLayout>
</template>
