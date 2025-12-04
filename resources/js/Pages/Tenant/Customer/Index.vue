<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Table from '@/Components/Tenant/Table.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
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
    pluralTitle: {
        type: String,
        default: 'Customers',
    },
});

const breadcrumbItems = computed(() => {
    return [
        { label: 'Home', href: route('dashboard') },
        { label: props.pluralTitle },
    ];
});
</script>

<template>
    <Head :title="recordTitle" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200 mt-4">
                    {{ recordTitle }}
                </h2>
            </div>
        </template>

        <Table :records="records" :schema="schema" :form-schema="formSchema" :fields-schema="fieldsSchema" :enum-options="enumOptions" :record-type="recordType" :record-title="recordTitle" :plural-title="pluralTitle" />
    </TenantLayout>
</template>

