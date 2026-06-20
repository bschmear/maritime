<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Table from '@/Components/Tenant/Table.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import QuickBooksImport from '@/Components/Tenant/QuickBooksImport.vue';
import BulkActionsGearModal from '@/Components/Tenant/BulkActionsGearModal.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    records: { type: Object, required: true },
    schema: { type: Object, default: null },
    fieldsSchema: { type: Object, default: () => ({}) },
    enumOptions: { type: Object, default: () => ({}) },
    quickbooksApSync: { type: Object, default: null },
    recordType: { type: String, default: 'bill-payments' },
    recordTitle: { type: String, default: 'Bill payment' },
    pluralTitle: { type: String, default: 'Bill payments' },
});

const quickBooksImportRef = ref(null);

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Bills', href: route('bills.index') },
    { label: 'Bill Payments' },
]);

const bulkActions = computed(() => {
    const raw = props.schema?.settings?.bulk_actions;
    if (Array.isArray(raw) && raw.length) {
        return raw.filter((a) => a && typeof a.label === 'string' && typeof a.action === 'string');
    }
    return [];
});

function runBulkAction(item) {
    if (item?.action === 'importFromQuickbooks') {
        quickBooksImportRef.value?.openImportModal?.();
    }
}
</script>

<template>
    <Head :title="pluralTitle" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white">{{ pluralTitle }}</h2>
                    <div class="flex items-center gap-2">
                        <BulkActionsGearModal
                            v-if="bulkActions.length"
                            :actions="bulkActions"
                            @action="runBulkAction"
                        />
                        <button
                            type="button"
                            class="inline-flex rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700"
                            @click="router.visit(route('bill-payments.create'))"
                        >
                            New payment
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <Table
            :records="records"
            :schema="schema"
            :fields-schema="fieldsSchema"
            :enum-options="enumOptions"
            :quickbooks-ap-sync="quickbooksApSync"
            :record-type="recordType"
            :record-title="recordTitle"
            :plural-title="pluralTitle"
        />

        <QuickBooksImport ref="quickBooksImportRef" record-type="billpayment" />
    </TenantLayout>
</template>
