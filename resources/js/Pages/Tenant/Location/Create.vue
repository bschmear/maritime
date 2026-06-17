<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import LocationForm from '@/Components/Tenant/LocationForm.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    recordType: { type: String, default: 'locations' },
    fieldsSchema: { type: Object, default: () => ({}) },
    enumOptions: { type: Object, default: () => ({}) },
    timezones: { type: Array, default: () => [] },
    account: { type: Object, default: null },
});

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Locations', href: route('locations.index') },
    { label: 'New' },
]);

const handleSaved = (payload) => {
    if (payload?.recordId != null) {
        router.visit(route('locations.show', payload.recordId));
        return;
    }
    router.visit(route('locations.index'));
};

const handleCancelled = () => {
    router.visit(route('locations.index'));
};
</script>

<template>
    <Head title="New location" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
            </div>
        </template>

        <div class="w-full p-4">
            <LocationForm
                mode="create"
                record-type="locations"
                :fields-schema="fieldsSchema"
                :enum-options="enumOptions"
                :timezones="timezones"
                :account="account"
                @saved="handleSaved"
                @cancelled="handleCancelled"
            />
        </div>
    </TenantLayout>
</template>
