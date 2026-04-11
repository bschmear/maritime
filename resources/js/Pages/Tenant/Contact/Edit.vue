<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import ContactForm from '@/Components/Tenant/ContactForm.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    record: { type: Object, required: true },
    recordType: { type: String, default: 'contacts' },
    formSchema: { type: Object, required: true },
    fieldsSchema: { type: Object, required: true },
    enumOptions: { type: Object, default: () => ({}) },
    account: { type: Object, default: null },
    timezones: { type: Array, default: () => [] },
    imageUrls: { type: Object, default: () => ({}) },
    availableSpecs: { type: Array, default: () => [] },
    initialData: { type: Object, default: () => ({}) },
});

const contactLabel = computed(() => {
    const r = props.record;
    return (
        r.display_name?.trim()
        || [r.first_name, r.last_name].filter(Boolean).join(' ').trim()
        || r.company
        || `Contact #${r.id}`
    );
});

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Contacts', href: route('contacts.index') },
    { label: contactLabel.value, href: route('contacts.show', props.record.id) },
    { label: 'Edit' },
]);

const handleSaved = () => {
    router.visit(route('contacts.show', props.record.id));
};

const handleCancelled = () => {
    router.visit(route('contacts.show', props.record.id));
};
</script>

<template>
    <Head :title="`Edit ${contactLabel}`" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="flex flex-wrap items-center gap-3 mt-4">
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        Edit {{ contactLabel }}
                    </h2>
                </div>
            </div>
        </template>

        <div class="w-full flex flex-col space-y-6 mx-auto">
            <ContactForm
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
                record-title="Contact"
                @saved="handleSaved"
                @cancelled="handleCancelled"
            />
        </div>
    </TenantLayout>
</template>
