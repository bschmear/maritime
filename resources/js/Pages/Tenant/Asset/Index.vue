<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Table from '@/Components/Tenant/Table.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import { Head, router } from '@inertiajs/vue3';
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
        type: String
    },
    recordTitle: {
        type: String
    },
    pluralTitle: {
        type: String
    },
});

const breadcrumbItems = computed(() => {
    return [
        { label: 'Home', href: route('dashboard') },
        { label: props.pluralTitle },
    ];
});

// Record type selector options (alphabetical)
const recordTypeOptions = [
    { value: 'assets', label: 'Assets', route: 'assets.index' },
    { value: 'inventoryitems', label: 'Parts & Accessories', route: 'inventoryitems.index' },
    { value: 'serviceitems', label: 'Service Items', route: 'serviceitems.index' },
];

const currentRecordType = ref(props.recordType);

const switchRecordType = (newType) => {
    const option = recordTypeOptions.find(opt => opt.value === newType);
    if (option) {
        router.visit(route(option.route));
    }
};
</script>

<template>
    <Head :title="recordTitle" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full flex items-center justify-between">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="flex items-center space-x-4">
                    <select
                        id="record-type-selector"
                        v-model="currentRecordType"
                        @change="switchRecordType($event.target.value)"
                        class="block w-full min-w-[200px] px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent shadow-sm transition-colors"
                    >
                        <option v-for="option in recordTypeOptions" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>
                </div>
            </div>
        </template>
        <Table :records="records" :schema="schema" :form-schema="formSchema" :fields-schema="fieldsSchema" :enum-options="enumOptions" :record-type="recordType" record-title="Asset" plural-title="Assets" />
    </TenantLayout>
</template>

