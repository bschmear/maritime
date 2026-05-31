<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import LeadForm from '@/Components/Tenant/LeadForm.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    record: { type: Object, required: true },
    recordType: { type: String, default: 'leads' },
    formSchema: { type: Object, required: true },
    fieldsSchema: { type: Object, required: true },
    enumOptions: { type: Object, default: () => ({}) },
    account: { type: Object, default: null },
    timezones: { type: Array, default: () => [] },
    imageUrls: { type: Object, default: () => ({}) },
    availableSpecs: { type: Array, default: () => [] },
    initialData: { type: Object, default: () => ({}) },
});

const leadLabel = computed(() => {
    const r = props.record;
    return (
        r.display_name?.trim()
        || [r.first_name, r.last_name].filter(Boolean).join(' ').trim()
        || r.company
        || `Lead #${r.id}`
    );
});

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Leads', href: route('leads.index') },
    { label: leadLabel.value, href: route('leads.show', props.record.id) },
    { label: 'Edit' },
]);

const handleSaved = () => {
    router.visit(route('leads.show', props.record.id));
};

const handleCancelled = () => {
    router.visit(route('leads.show', props.record.id));
};
</script>

<template>
    <Head :title="`Edit ${leadLabel}`" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        Edit {{ leadLabel }}
                    </h2>
                </div>
            </div>
        </template>

        <div class="mx-auto flex w-full flex-col space-y-6">
            <LeadForm
                :record="record"
                :form-schema="formSchema"
                :fields-schema="fieldsSchema"
                :enum-options="enumOptions"
                :account="account"
                :timezones="timezones"
                :image-urls="imageUrls"
                :available-specs="availableSpecs"
                :initial-data="initialData"
                mode="edit"
                :record-type="recordType"
                record-title="Lead"
                @saved="handleSaved"
                @cancelled="handleCancelled"
            />
        </div>
    </TenantLayout>
</template>
