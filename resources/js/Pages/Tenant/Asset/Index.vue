<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Table from '@/Components/Tenant/Table.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

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
    /** Spec field definitions for the create modal (default asset type); switching type refetches in Form. */
    createAvailableSpecs: {
        type: Array,
        default: () => [],
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

const recordTypeOptions = [
    { value: 'assets', label: 'Assets', route: 'assets.index' },
    { value: 'inventoryitems', label: 'Parts & Accessories', route: 'inventoryitems.index' },
    { value: 'serviceitems', label: 'Service Items', route: 'serviceitems.index' },
];

const currentRecordType = ref(props.recordType);

const switchRecordType = (newType) => {
    const option = recordTypeOptions.find((opt) => opt.value === newType);
    if (option) {
        router.visit(route(option.route));
    }
};
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
                        <button
                            type="button"
                            role="tab"
                            aria-selected="true"
                            :class="tabBtnClass('assets', true)"
                        >
                            Assets
                        </button>
                        <Link
                            role="tab"
                            :aria-selected="false"
                            :href="route('assets.units.global-index')"
                            :class="tabBtnClass('units', false)"
                        >
                            Units
                        </Link>
                    </div>
                    <select
                        id="record-type-selector"
                        v-model="currentRecordType"
                        class="block min-w-[200px] rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm transition-colors focus:border-transparent focus:ring-2 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                        @change="switchRecordType($event.target.value)"
                    >
                        <option v-for="option in recordTypeOptions" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>
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
            record-title="Asset"
            plural-title="Assets"
            :create-available-specs="createAvailableSpecs"
        />
    </TenantLayout>
</template>
