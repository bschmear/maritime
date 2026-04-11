<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import Form from '@/Components/Tenant/Form.vue';
import { buildResourceRouteParams } from '@/Utils/resourceRoutes.js';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    record: { type: Object, required: true },
    recordType: { type: String, default: 'contactaddresses' },
    formSchema: { type: Object, required: true },
    fieldsSchema: { type: Object, required: true },
    enumOptions: { type: Object, default: () => ({}) },
    account: { type: Object, default: null },
    timezones: { type: Array, default: () => [] },
});

const routeParams = computed(() => buildResourceRouteParams(props.recordType, props.record.id));

const title = computed(() => props.record.display_name || `Address #${props.record.id}`);

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Contact addresses', href: route('contactaddresses.index') },
    { label: title.value, href: route('contactaddresses.show', routeParams.value) },
    { label: 'Edit' },
]);

const handleUpdated = () => {
    router.visit(route('contactaddresses.show', routeParams.value));
};

const handleCancel = () => {
    router.visit(route('contactaddresses.show', routeParams.value));
};
</script>

<template>
    <Head :title="`Edit ${title}`" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <h2 class="mt-4 text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Edit {{ title }}
                </h2>
            </div>
        </template>

        <div class="mx-auto w-full max-w-4xl rounded-lg bg-white p-6 shadow dark:bg-gray-800">
            <Form
                :schema="formSchema"
                :fields-schema="fieldsSchema"
                :enum-options="enumOptions"
                :record="record"
                :record-type="recordType"
                record-title="Contact address"
                mode="edit"
                @updated="handleUpdated"
                @cancel="handleCancel"
            />
        </div>
    </TenantLayout>
</template>
