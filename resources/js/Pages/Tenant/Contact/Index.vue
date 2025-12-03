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
    recordType: {
        type: String,
        default: 'contact',
    },
});
</script>

<template>
    <Head title="Contacts" />

    <TenantLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Contacts
            </h2>
        </template>

        <Table :records="records" :schema="schema" :record-type="recordType" />
    </TenantLayout>
</template>

