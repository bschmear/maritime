<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import OpportunityForm from '@/Components/Tenant/OpportunityForm.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    recordType: {
        type: String,
        default: 'Opportunity',
    },
    recordTitle: {
        type: String,
        default: 'Opportunity',
    },
    domainName: {
        type: String,
        default: 'Opportunity',
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

const isFromQualification = computed(() => {
    const params = new URLSearchParams(window.location.search);
    return params.get('from') === 'qualification';
});
const qualificationName = computed(() => props.initialData?.qualification?.display_name ?? null);

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Opportunities', href: route('opportunities.index') },
    { label: 'Create Opportunity' },
]);

const handleCancelled = () => {
    router.visit(route('opportunities.index'));
};
</script>

<template>
    <Head title="Create Opportunity" />

    <TenantLayout>
<template #header>
    <div class="col-span-full">
        <Breadcrumb :items="breadcrumbItems" />
        <div class="flex flex-wrap items-center gap-3 mt-4">
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                New Opportunity
            </h2>
            <div
                v-if="isFromQualification"
                class="inline-flex items-center gap-2 px-4 py-2 bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800 rounded-lg"
            >
                <span class="material-icons text-purple-600 dark:text-purple-400 text-sm">checklist</span>
                <span class="text-sm font-medium text-purple-700 dark:text-purple-300">
                    {{ 'Creating from: ' + qualificationName ?? 'From Qualification' }}
                </span>
            </div>
        </div>
    </div>
</template>

        <OpportunityForm
            :record="null"
            :form-schema="formSchema"
            :fields-schema="fieldsSchema"
            :enum-options="enumOptions"
            :initial-data="initialData"
            :account="account"
            :timezones="timezones"
            mode="create"
            :from-qualification="isFromQualification"
            @cancelled="handleCancelled"
        />
    </TenantLayout>
</template>
