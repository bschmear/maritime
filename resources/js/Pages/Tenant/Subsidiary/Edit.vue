<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import SubsidiaryForm from '@/Components/Tenant/SubsidiaryForm.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    record: { type: Object, required: true },
    recordType: { type: String, default: 'subsidiaries' },
    fieldsSchema: { type: Object, default: () => ({}) },
    enumOptions: { type: Object, default: () => ({}) },
    timezones: { type: Array, default: () => [] },
    imageUrls: { type: Object, default: () => ({}) },
});

const label = computed(
    () => props.record.display_name?.trim() || `Subsidiary #${props.record.id}`,
);

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Subsidiaries', href: route('subsidiaries.index') },
    { label: label.value, href: route('subsidiaries.show', props.record.id) },
    { label: 'Edit' },
]);

const handleSaved = () => {
    router.visit(route('subsidiaries.show', props.record.id));
};

const handleCancelled = () => {
    router.visit(route('subsidiaries.show', props.record.id));
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
            <SubsidiaryForm
            mode="edit"
            record-type="subsidiaries"
            :record="record"
            :fields-schema="fieldsSchema"
            :enum-options="enumOptions"
            :timezones="timezones"
            :image-urls="imageUrls"
            @saved="handleSaved"
            @cancelled="handleCancelled"
            />
        </div>
    </TenantLayout>
</template>
