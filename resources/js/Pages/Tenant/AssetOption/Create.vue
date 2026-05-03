<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import Form from '@/Components/Tenant/Form.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    recordType: { type: String, required: true },
    recordTitle: { type: String, default: 'Asset Option' },
    domainName: { type: String, default: 'AssetOption' },
    formSchema: { type: Object, required: true },
    fieldsSchema: { type: Object, required: true },
    enumOptions: { type: Object, default: () => ({}) },
    account: { type: Object, default: null },
    timezones: { type: Array, default: () => [] },
});

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Asset options', href: route('asset-options.index') },
    { label: 'New' },
]);

const handleCreated = (recordId) => {
    router.visit(route('asset-options.show', { assetOption: recordId }));
};
const handleCancel = () => router.visit(route('asset-options.index'));
</script>

<template>
    <Head title="New asset option" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <h2 class="mt-4 text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">New asset option</h2>
            </div>
        </template>

        <div class="mx-auto w-full max-w-4xl px-4 py-6">
            <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                <Form
                    :schema="formSchema"
                    :fields-schema="fieldsSchema"
                    :enum-options="enumOptions"
                    :record-type="recordType"
                    :record-title="recordTitle"
                    mode="create"
                    @created="handleCreated"
                    @cancel="handleCancel"
                />
            </div>
        </div>
    </TenantLayout>
</template>
