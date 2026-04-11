<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import ContactForm from '@/Components/Tenant/ContactForm.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    recordType: { type: String, default: 'contacts' },
    formSchema: { type: Object, required: true },
    fieldsSchema: { type: Object, required: true },
    enumOptions: { type: Object, default: () => ({}) },
    account: { type: Object, default: null },
    timezones: { type: Array, default: () => [] },
    initialData: { type: Object, default: () => ({}) },
});

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Contacts', href: route('contacts.index') },
    { label: 'New' },
]);

const handleSaved = (payload) => {
    if (payload?.recordId != null) {
        router.visit(route('contacts.show', payload.recordId));
        return;
    }
    router.visit(route('contacts.index'));
};

const handleCancelled = () => {
    router.visit(route('contacts.index'));
};
</script>

<template>
    <Head title="New contact" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="flex flex-wrap items-center gap-3 mt-4">
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        New contact
                    </h2>
                </div>
            </div>
        </template>

        <div class="w-full flex flex-col space-y-6 mx-auto">
            <ContactForm
                :record="null"
                :form-schema="formSchema"
                :fields-schema="fieldsSchema"
                :enum-options="enumOptions"
                :account="account"
                :timezones="timezones"
                :initial-data="initialData"
                mode="create"
                :record-type="recordType"
                record-title="Contact"
                @saved="handleSaved"
                @cancelled="handleCancelled"
            />
        </div>
    </TenantLayout>
</template>
