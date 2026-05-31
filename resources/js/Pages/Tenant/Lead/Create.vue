<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import LeadForm from '@/Components/Tenant/LeadForm.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    recordType: { type: String, default: 'leads' },
    formSchema: { type: Object, required: true },
    fieldsSchema: { type: Object, required: true },
    enumOptions: { type: Object, default: () => ({}) },
    account: { type: Object, default: null },
    timezones: { type: Array, default: () => [] },
    initialData: { type: Object, default: () => ({}) },
});

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Leads', href: route('leads.index') },
    { label: 'New' },
]);

const handleSaved = (payload) => {
    if (payload?.recordId != null) {
        router.visit(route('leads.show', payload.recordId));
        return;
    }
    router.visit(route('leads.index'));
};

const handleCancelled = () => {
    router.visit(route('leads.index'));
};
</script>

<template>
    <Head title="New lead" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        New lead
                    </h2>
                </div>
            </div>
        </template>

        <div class="mx-auto flex w-full flex-col space-y-6">
            <LeadForm
                :record="null"
                :form-schema="formSchema"
                :fields-schema="fieldsSchema"
                :enum-options="enumOptions"
                :account="account"
                :timezones="timezones"
                :initial-data="initialData"
                mode="create"
                :record-type="recordType"
                record-title="Lead"
                @saved="handleSaved"
                @cancelled="handleCancelled"
            />
        </div>
    </TenantLayout>
</template>
