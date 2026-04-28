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
    },
    recordTitle: {
        type: String,
    },
    pluralTitle: {
        type: String,
    },
});

const tabBtnClass = (tab, active) => [
    'rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
    active
        ? 'bg-primary-600 text-white shadow-sm dark:bg-primary-500'
        : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800',
];

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
            <div class="col-span-full flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="flex flex-wrap items-center gap-3">
                    <div
                        class="inline-flex rounded-lg border border-gray-200 bg-gray-50 p-0.5 dark:border-gray-600 dark:bg-gray-800"
                        role="tablist"
                        aria-label="List view"
                    >
                        <Link
                            role="tab"
                            :aria-selected="false"
                            :href="route('assets.index')"
                            :class="tabBtnClass('assets', false)"
                        >
                            Assets
                        </Link>
                        <button
                            type="button"
                            role="tab"
                            aria-selected="true"
                            :class="tabBtnClass('units', true)"
                        >
                            Units
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <Table
            :records="records"
            :schema="schema"
            :form-schema="formSchema"
            :fields-schema="fieldsSchema"
            :enum-options="enumOptions"
            :record-type="recordType"
            record-title="Asset Units"
            plural-title="Units"
        />
    </TenantLayout>
</template>
