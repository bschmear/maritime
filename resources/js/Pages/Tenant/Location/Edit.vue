<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import LocationForm from '@/Components/Tenant/LocationForm.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    record: { type: Object, required: true },
    recordType: { type: String, default: 'locations' },
    fieldsSchema: { type: Object, default: () => ({}) },
    enumOptions: { type: Object, default: () => ({}) },
    timezones: { type: Array, default: () => [] },
});

const label = computed(
    () => props.record.display_name?.trim() || `Location #${props.record.id}`,
);

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Locations', href: route('locations.index') },
    { label: label.value, href: route('locations.show', props.record.id) },
    { label: 'Edit' },
]);

const handleSaved = () => {
    router.visit(route('locations.show', props.record.id));
};

const handleCancelled = () => {
    router.visit(route('locations.show', props.record.id));
};
</script>

<template>
    <Head :title="`Edit ${label}`" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
            </div>
        </template>

        <div class="w-full p-4">
            <LocationForm
                mode="edit"
                record-type="locations"
                :record="record"
                :fields-schema="fieldsSchema"
                :enum-options="enumOptions"
                :timezones="timezones"
                @saved="handleSaved"
                @cancelled="handleCancelled"
            />
        </div>
    </TenantLayout>
</template>
