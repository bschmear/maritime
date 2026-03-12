<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import EstimateForm from '@/Components/Tenant/EstimateForm.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    record: {
        type: Object,
        required: true,
    },
    recordType: {
        type: String,
        default: 'estimates',
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
});

const estimateLabel = computed(() =>
    props.record?.display_name ? props.record.display_name : `Estimate #${props.record?.id}`
);

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Estimates', href: route('estimates.index') },
    { label: estimateLabel.value, href: route('estimates.show', props.record.id) },
    { label: 'Edit' },
]);

const handleSaved = () => {
    router.visit(route('estimates.show', props.record.id));
};

const handleCancelled = () => {
    router.visit(route('estimates.show', props.record.id));
};
</script>

<template>
    <Head :title="`Edit ${estimateLabel}`" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="flex flex-wrap items-center gap-3 mt-4">
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        Edit {{ estimateLabel }}
                    </h2>
                </div>
            </div>
        </template>

        <div class="w-full flex flex-col space-y-6  mx-auto">
            <EstimateForm
                :record="record"
                :form-schema="formSchema"
                :fields-schema="fieldsSchema"
                :enum-options="enumOptions"
                :account="account"
                :timezones="timezones"
                :initial-data="initialData"
                mode="edit"
                @saved="handleSaved"
                @cancelled="handleCancelled"
            />
        </div>
    </TenantLayout>
</template>
