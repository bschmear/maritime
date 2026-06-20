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
    record: { type: Object, required: true },
    recordType: { type: String, default: 'bills' },
    formSchema: { type: Object, required: true },
    fieldsSchema: { type: Object, required: true },
    enumOptions: { type: Object, default: () => ({}) },
    account: { type: Object, default: null },
    timezones: { type: Array, default: () => [] },
    quickbooksApSync: { type: Object, default: null },
    editRestrictions: {
        type: Object,
        default: () => ({
            restricted: false,
            allowedFields: ['vendor_id'],
            reason: null,
        }),
    },
});

const billLabel = computed(() => props.record.display_name || `Bill #${props.record.id}`);

const isEditRestricted = computed(() => !!props.editRestrictions?.restricted);

const pageTitle = computed(() => (isEditRestricted.value ? `Link records · ${billLabel.value}` : `Edit ${billLabel.value}`));

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Bills', href: route('bills.index') },
    { label: billLabel.value, href: route('bills.show', props.record.id) },
    { label: 'Edit' },
]);

const handleSaved = () => {
    router.visit(route('bills.show', props.record.id));
};

const handleCancelled = () => {
    router.visit(route('bills.show', props.record.id));
};
</script>

<template>
    <Head :title="pageTitle" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <h2 class="mt-4 text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    {{ isEditRestricted ? `Link records · ${billLabel}` : `Edit ${billLabel}` }}
                </h2>
            </div>
        </template>

        <div class="mx-auto flex w-full max-w-7xl flex-col space-y-6 px-4 py-6 sm:px-6 lg:px-8">
            <BillForm
                :record="record"
                :form-schema="formSchema"
                :fields-schema="fieldsSchema"
                :enum-options="enumOptions"
                :edit-restrictions="editRestrictions"
                mode="edit"
                :record-type="recordType === 'Bill' ? 'bills' : recordType"
                record-title="Bill"
                @saved="handleSaved"
                @cancelled="handleCancelled"
            />
        </div>
    </TenantLayout>
</template>
