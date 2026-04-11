<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Table from '@/Components/Tenant/Table.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import { Head, Link } from '@inertiajs/vue3';
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
        default: 'contact',
    },
    recordTitle: {
        type: String,
        default: 'contact',
    },
    pluralTitle: {
        type: String,
        default: 'contacts',
    },
    roleFilter: {
        type: String,
        default: null,
    },
});

const breadcrumbItems = computed(() => {
    return [
        { label: 'Home', href: route('dashboard') },
        { label: props.pluralTitle },
    ];
});

const roleLinks = [
    { key: null, label: 'All' },
    { key: 'lead', label: 'Leads' },
    { key: 'customer', label: 'Customers' }
];

const roleLinkClass = (key) => {
    const active = (props.roleFilter ?? null) === key;
    return [
        'inline-flex items-center rounded-lg px-3 py-1.5 text-sm font-medium transition-colors',
        active
            ? 'bg-primary-600 text-white dark:bg-primary-500'
            : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600',
    ];
};
</script>

<template>
    <Head :title="recordTitle" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <Link
                        v-for="item in roleLinks"
                        :key="item.key ?? 'all'"
                        :href="item.key ? route('contacts.index', { role: item.key }) : route('contacts.index')"
                        :class="roleLinkClass(item.key)"
                    >
                        {{ item.label }}
                    </Link>
                </div>
            </div>
        </template>

        <Table :records="records" :schema="schema" :form-schema="formSchema" :fields-schema="fieldsSchema" :enum-options="enumOptions" :record-type="recordType" :record-title="recordTitle" :plural-title="pluralTitle" />
    </TenantLayout>
</template>

