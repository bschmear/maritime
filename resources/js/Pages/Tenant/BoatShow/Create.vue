<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import Form from '@/Components/Tenant/Form.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    recordType: { type: String, required: true },
    formSchema: { type: Object, required: true },
    fieldsSchema: { type: Object, required: true },
    enumOptions: { type: Object, default: () => ({}) },
    account: { type: Object, default: null },
    timezones: { type: Array, default: () => [] },
    initialData: { type: Object, default: () => ({}) },
    extraRouteParams: { type: Object, default: () => ({}) },
});

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Boat Shows', href: route('boat-shows.index') },
    { label: 'Create' },
]);

const handleCancel = () => {
    router.visit(route('boat-shows.index'));
};
</script>

<template>
    <Head title="Create Boat Show" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200 mt-4">
                    New boat show
                </h2>
            </div>
        </template>

        <div class="w-full flex flex-col space-y-6 mx-auto max-w-4xl">
            <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden p-6">
                <Form
                    :schema="formSchema"
                    :fields-schema="fieldsSchema"
                    :enum-options="enumOptions"
                    :account="account"
                    :timezones="timezones"
                    :initial-data="initialData"
                    :extra-route-params="extraRouteParams"
                    :record-type="recordType"
                    record-title="Boat Show"
                    mode="create"
                    @cancel="handleCancel"
                />
            </div>
        </div>
    </TenantLayout>
</template>
