<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import AssetForm from '@/Components/Tenant/AssetForm.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    recordType: {
        type: String,
        default: 'assets',
    },
    recordTitle: {
        type: String,
        default: 'Asset',
    },
    formSchema: {
        type: Object,
        required: true,
    },
    fieldsSchema: {
        type: Object,
        required: true,
    },
    enumOptions: {
        type: Object,
        default: () => ({}),
    },
    timezones: {
        type: Array,
        default: () => [],
    },
    createAvailableSpecs: {
        type: Array,
        default: () => [],
    },
    account: {
        type: Object,
        default: null,
    },
});

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Assets', href: route('assets.index') },
    { label: 'Create Asset' },
]);

const handleCancel = () => {
    router.visit(route('assets.index'));
};
</script>

<template>
    <Head title="Create Asset" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="mt-4">
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        New Asset
                    </h2>
                </div>
            </div>
        </template>

        <div class="mx-auto flex w-full flex-col space-y-6">
            <AssetForm
                :schema="formSchema"
                :fields-schema="fieldsSchema"
                :record-type="recordType"
                :record-title="recordTitle"
                :enum-options="enumOptions"
                :timezones="timezones"
                :available-specs="createAvailableSpecs"
                :account="account"
                mode="create"
                @cancel="handleCancel"
            />
        </div>
    </TenantLayout>
</template>
