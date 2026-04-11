<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import VendorForm from '@/Components/Tenant/VendorForm.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    recordType: { type: String, default: 'vendors' },
    formSchema: { type: Object, required: true },
    fieldsSchema: { type: Object, required: true },
    enumOptions: { type: Object, default: () => ({}) },
    account: { type: Object, default: null },
    timezones: { type: Array, default: () => [] },
    initialData: { type: Object, default: () => ({}) },
});

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Vendors', href: route('vendors.index') },
    { label: 'New' },
]);

const handleSaved = (payload) => {
    if (payload?.recordId != null) {
        router.visit(route('vendors.show', payload.recordId));
        return;
    }
    router.visit(route('vendors.index'));
};

const handleCancelled = () => {
    router.visit(route('vendors.index'));
};
</script>

<template>
    <Head title="New vendor" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        New vendor
                    </h2>
                </div>
            </div>
        </template>

        <div class="mx-auto flex w-full flex-col space-y-6">
            <VendorForm
                :record="null"
                :form-schema="formSchema"
                :fields-schema="fieldsSchema"
                :enum-options="enumOptions"
                :account="account"
                :timezones="timezones"
                :initial-data="initialData"
                mode="create"
                :record-type="recordType"
                record-title="Vendor"
                @saved="handleSaved"
                @cancelled="handleCancelled"
            />
        </div>
    </TenantLayout>
</template>
