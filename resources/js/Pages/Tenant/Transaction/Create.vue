<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import TransactionForm from '@/Components/Tenant/TransactionForm.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    recordType: {
        type: String,
        default: 'Transaction',
    },
    recordTitle: {
        type: String,
        default: 'Transaction',
    },
    domainName: {
        type: String,
        default: 'Transaction',
    },
    formSchema: {
        type: Object,
        required: true,
    },
    fieldsSchema: {
        type: Object,
        required: true,
    },
    enumOptions: {
        type: Object,
        default: () => ({}),
    },
    account: {
        type: Object,
        default: null,
    },
    timezones: {
        type: Array,
        default: () => [],
    },
    initialData: {
        type: Object,
        default: () => ({}),
    },
});

const isFromEstimate = computed(() => {
    const params = new URLSearchParams(typeof window !== 'undefined' ? window.location.search : '');
    return Boolean(params.get('estimate_id'));
});

const estimateLabel = computed(() => props.initialData?.estimate?.display_name ?? null);

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Transactions', href: route('transactions.index') },
    { label: 'Create Transaction' },
]);

const handleCancelled = () => {
    router.visit(route('transactions.index'));
};
</script>

<template>
    <Head title="Create Transaction" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        New Transaction
                    </h2>
                    <div
                        v-if="isFromEstimate && estimateLabel"
                        class="inline-flex items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 px-4 py-2 dark:border-blue-800 dark:bg-blue-900/20"
                    >
                        <span class="material-icons text-sm text-blue-600 dark:text-blue-400">description</span>
                        <span class="text-sm font-medium text-blue-800 dark:text-blue-200">
                            Prefilled from {{ estimateLabel }}
                        </span>
                    </div>
                </div>
            </div>
        </template>

        <TransactionForm
            :record="null"
            :form-schema="formSchema"
            :fields-schema="fieldsSchema"
            :enum-options="enumOptions"
            :initial-data="initialData"
            :account="account"
            :timezones="timezones"
            mode="create"
            @cancel="handleCancelled"
        />
    </TenantLayout>
</template>
