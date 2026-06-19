<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import ChartOfAccountNestedTable from '@/Components/Tenant/ChartOfAccountNestedTable.vue';
import QuickBooksImport from '@/Components/Tenant/QuickBooksImport.vue';
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    accountTree: { type: Array, default: () => [] },
    filters: {
        type: Object,
        default: () => ({ search: '', account_type: null, active: null }),
    },
    accountTypes: { type: Array, default: () => [] },
    stats: {
        type: Object,
        default: () => ({ total: 0, active: 0, roots: 0 }),
    },
    pluralTitle: { type: String, default: 'Chart of accounts' },
    recordType: { type: String, default: 'chart-of-accounts' },
    recordTitle: { type: String, default: 'Chart of account' },
});

const quickBooksImportRef = ref(null);
const importing = ref(false);

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Bills', href: route('bills.index') },
    { label: props.pluralTitle },
]);

function openQuickBooksSync() {
    quickBooksImportRef.value?.openImportModal?.();
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
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                            :disabled="importing"
                            @click="openQuickBooksSync"
                        >
                            <span v-if="importing" class="material-icons animate-spin text-base leading-none" aria-hidden="true">sync</span>
                            {{ importing ? 'Syncing from QuickBooks…' : 'Sync from QuickBooks' }}
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <ChartOfAccountNestedTable
            :account-tree="accountTree"
            :filters="filters"
            :account-types="accountTypes"
            :stats="stats"
            :record-type="recordType"
        />

        <QuickBooksImport ref="quickBooksImportRef" v-model:importing="importing" record-type="chartofaccounts" />
    </TenantLayout>
</template>
