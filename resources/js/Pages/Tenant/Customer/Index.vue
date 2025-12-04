<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Table from '@/Components/Tenant/Table.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    records: {
        type: Object,
        required: true,
    },
    schema: {
        type: Object,
        default: null,
    },
    formSchema: {
        type: Object,
        default: null,
    },
    fieldsSchema: {
        type: Object,
        default: () => ({}),
    },
    enumOptions: {
        type: Object,
        default: () => ({}),
    },
    recordType: {
        type: String,
        default: 'customer',
    },
    recordTitle: {
        type: String,
        default: 'Customer',
    },
});
</script>

<template>
    <Head title="Customers" />

    <TenantLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Customers
            </h2>
        </template>

        <Table :records="records" :schema="schema" :form-schema="formSchema" :fields-schema="fieldsSchema" :enum-options="enumOptions" :record-type="recordType" :record-title="recordTitle" />
    </TenantLayout>
</template>

