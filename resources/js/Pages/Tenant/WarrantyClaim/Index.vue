<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Table from '@/Components/Tenant/Table.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    tab: { type: String, default: 'claims' },
    records: { type: Object, default: null },
    schema: { type: Object, default: null },
    formSchema: { type: Object, default: null },
    fieldsSchema: { type: Object, default: () => ({}) },
    enumOptions: { type: Object, default: () => ({}) },
    recordType: { type: String, default: 'warrantyclaims' },
    recordTitle: { type: String, default: 'Warranty claim' },
    pluralTitle: { type: String, default: 'Warranty claims' },
    workOrderQueueRecords: { type: Object, default: null },
    workOrderQueueSchema: { type: Object, default: null },
    workOrderQueueFormSchema: { type: Object, default: null },
    workOrderQueueFieldsSchema: { type: Object, default: () => ({}) },
    workOrderQueueEnumOptions: { type: Object, default: () => ({}) },
});

const isClaimsTab = computed(() => props.tab !== 'work-orders');

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: props.pluralTitle },
]);

const switchTab = (t) => {
    router.get(route('warrantyclaims.index'), { tab: t }, { preserveState: false, preserveScroll: true });
};
</script>

<template>
    <Head :title="pluralTitle" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="mt-4 flex flex-wrap gap-2 border-b border-gray-200 pb-3 dark:border-gray-700">
                    <button
                        type="button"
                        class="rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                        :class="isClaimsTab
                            ? 'bg-primary-600 text-white'
                            : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700'"
                        @click="switchTab('claims')"
                    >
                        Claims
                    </button>
                    <button
                        type="button"
                        class="rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                        :class="!isClaimsTab
                            ? 'bg-primary-600 text-white'
                            : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700'"
                        @click="switchTab('work-orders')"
                    >
                        Work orders needing warranty
                    </button>
                </div>
            </div>
        </template>

        <Table
            v-if="isClaimsTab && records"
            :records="records"
            :schema="schema"
            :form-schema="formSchema"
            :fields-schema="fieldsSchema"
            :enum-options="enumOptions"
            :record-type="recordType"
            :record-title="recordTitle"
            :plural-title="pluralTitle"
        />

        <div v-if="!isClaimsTab && workOrderQueueRecords" class="space-y-4">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Work orders with manufacturer warranty lines that still need a completed warranty claim (paid or voided).
            </p>
            <Table
                :records="workOrderQueueRecords"
                :schema="workOrderQueueSchema"
                :form-schema="workOrderQueueFormSchema"
                :fields-schema="workOrderQueueFieldsSchema"
                :enum-options="workOrderQueueEnumOptions"
                record-type="workorders"
                record-title="Work order"
                plural-title="Work orders"
                :create-modal="false"
            />
            <div class="text-sm">
                <Link
                    :href="route('warrantyclaims.create')"
                    class="text-primary-600 hover:underline dark:text-primary-400"
                >
                    New warranty claim
                </Link>
            </div>
        </div>
    </TenantLayout>
</template>
