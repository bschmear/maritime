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
        default: 'user',
    },
    recordTitle: {
        type: String,
        default: 'user',
    },
    pluralTitle: {
        type: String,
        default: 'Users',
    },
});

const breadcrumbItems = computed(() => {
    return [
        { label: 'Home', href: route('dashboard') },
        { label: 'Account', href: route('account.index') },
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
            </div>
        </template>

        <Table :records="records" :schema="schema" :form-schema="formSchema" :fields-schema="fieldsSchema" :enum-options="enumOptions" :record-type="recordType" :record-title="recordTitle" :plural-title="pluralTitle" />
    </TenantLayout>
</template>

