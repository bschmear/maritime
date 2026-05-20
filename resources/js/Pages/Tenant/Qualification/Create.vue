<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import QualificationForm from '@/Components/Tenant/QualificationForm.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    recordType: { type: String, default: 'qualifications' },
    recordTitle: { type: String, default: 'Qualification' },
    domainName: { type: String, default: 'Qualification' },
    formSchema: { type: Object, required: true },
    fieldsSchema: { type: Object, required: true },
    enumOptions: { type: Object, default: () => ({}) },
    account: { type: Object, default: null },
    timezones: { type: Array, default: () => [] },
    initialData: { type: Object, default: () => ({}) },
});

const isFromLead = computed(() => {
    const params = new URLSearchParams(window.location.search);
    return params.has('lead_id');
});

const leadLabel = computed(() => props.initialData?.lead?.display_name ?? null);

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Qualifications', href: route('qualifications.index') },
    { label: 'New Qualification' },
]);

const handleCancelled = () => {
    router.visit(route('qualifications.index'));
};
</script>

<template>
    <Head title="New Qualification" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="flex flex-wrap items-center gap-3 mt-4">
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        New Qualification
                    </h2>
                    <div
                        v-if="isFromLead && leadLabel"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-800 rounded-lg"
                    >
                        <span class="text-sm font-medium text-primary-700 dark:text-primary-300">
                            From lead: {{ leadLabel }}
                        </span>
                    </div>
                </div>
            </div>
        </template>

        <div class="w-full flex flex-col space-y-6 mx-auto">
            <QualificationForm
                :record="null"
                :form-schema="formSchema"
                :fields-schema="fieldsSchema"
                :enum-options="enumOptions"
                :account="account"
                :timezones="timezones"
                :initial-data="initialData"
                mode="create"
                @cancelled="handleCancelled"
            />
        </div>
    </TenantLayout>
</template>
