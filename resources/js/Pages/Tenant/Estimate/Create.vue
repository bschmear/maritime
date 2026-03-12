<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import EstimateForm from '@/Components/Tenant/EstimateForm.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    recordType: {
        type: String,
        default: 'Estimate',
    },
    recordTitle: {
        type: String,
        default: 'Estimate',
    },
    domainName: {
        type: String,
        default: 'Estimate',
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
    opportunityLineItems: {
        type: Object,
        default: null,
    },
});

const isFromOpportunity = computed(() => {
    const params = new URLSearchParams(window.location.search);
    return params.get('from') === 'opportunity';
});
const opportunityName = computed(() => props.initialData?.opportunity?.display_name ?? null);

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Estimates', href: route('estimates.index') },
    { label: 'Create Estimate' },
]);

const handleSaved = () => {
    router.visit(route('estimates.index'));
};

const handleCancel = () => {
    router.visit(route('estimates.index'));
};
</script>

<template>
    <Head title="Create Estimate" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="flex flex-wrap items-center gap-3 mt-4">
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        New Estimate
                    </h2>
                    <div
                        v-if="isFromOpportunity"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-800 rounded-lg"
                    >
                        <span class="text-sm font-medium text-primary-700 dark:text-primary-300">
                            {{ opportunityName ? `From: ${opportunityName}` : 'From Opportunity' }}
                        </span>
                    </div>
                </div>
            </div>
        </template>

        <div class="w-full flex flex-col space-y-6 max-w-7xl mx-auto">
            <EstimateForm
                :record="null"
                :form-schema="formSchema"
                :fields-schema="fieldsSchema"
                :enum-options="enumOptions"
                :account="account"
                :timezones="timezones"
                :initial-data="initialData"
                :opportunity-line-items="opportunityLineItems"
                mode="create"
                @saved="handleSaved"
                @cancelled="handleCancel"
            />
        </div>
    </TenantLayout>
</template>
