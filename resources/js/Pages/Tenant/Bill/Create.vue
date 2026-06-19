<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import BillForm from '@/Components/Tenant/BillForm.vue';
import { useQuickBooksApSyncOverlay } from '@/composables/useQuickBooksApSyncOverlay';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const page = usePage();
const { handleCreateFlash } = useQuickBooksApSyncOverlay();

watch(
    () => page.props.flash,
    (flash) => handleCreateFlash(flash),
    { immediate: true, deep: true },
);

const props = defineProps({
    recordType: { type: String, default: 'bills' },
    formSchema: { type: Object, required: true },
    fieldsSchema: { type: Object, required: true },
    enumOptions: { type: Object, default: () => ({}) },
    account: { type: Object, default: null },
    timezones: { type: Array, default: () => [] },
    quickbooksApSync: { type: Object, default: null },
    initialData: { type: Object, default: () => ({}) },
});

const initialDataFromQuery = computed(() => {
    const data = { ...props.initialData };
    if (typeof window === 'undefined') {
        return data;
    }

    const url = new URL(window.location.href);
    const vendorId = url.searchParams.get('vendor_id');
    if (vendorId) {
        data.vendor_id = Number(vendorId);
    }

    return data;
});

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Bills', href: route('bills.index') },
    { label: 'New' },
]);

const handleSaved = (payload) => {
    if (payload?.recordId != null) {
        router.visit(route('bills.show', payload.recordId));
        return;
    }
    router.visit(route('bills.index'));
};

const handleCancelled = () => {
    router.visit(route('bills.index'));
};
</script>

<template>
    <Head title="New bill" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <h2 class="mt-4 text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    New bill
                </h2>
            </div>
        </template>

        <div class="mx-auto flex w-full max-w-4xl flex-col space-y-6 px-4 py-6 sm:px-6 lg:px-8">
            <BillForm
                :form-schema="formSchema"
                :fields-schema="fieldsSchema"
                :enum-options="enumOptions"
                :initial-data="initialDataFromQuery"
                mode="create"
                :record-type="recordType === 'Bill' ? 'bills' : recordType"
                record-title="Bill"
                @saved="handleSaved"
                @cancelled="handleCancelled"
            />
        </div>
    </TenantLayout>
</template>
