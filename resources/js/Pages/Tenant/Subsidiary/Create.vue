<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import SubsidiaryForm from '@/Components/Tenant/SubsidiaryForm.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    recordType: { type: String, default: 'subsidiaries' },
    fieldsSchema: { type: Object, default: () => ({}) },
    enumOptions: { type: Object, default: () => ({}) },
    timezones: { type: Array, default: () => [] },
});

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Subsidiaries', href: route('subsidiaries.index') },
    { label: 'New' },
]);

const handleSaved = (payload) => {
    if (payload?.recordId != null) {
        router.visit(route('subsidiaries.show', payload.recordId));
        return;
    }
    router.visit(route('subsidiaries.index'));
};

const handleCancelled = () => {
    router.visit(route('subsidiaries.index'));
};
</script>

<template>
    <Head title="New subsidiary" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
            </div>
        </template>

        <div class="w-full p-4">
            <SubsidiaryForm
            mode="create"
            record-type="subsidiaries"
            :fields-schema="fieldsSchema"
            :enum-options="enumOptions"
            :timezones="timezones"
            @saved="handleSaved"
            @cancelled="handleCancelled"
            />
        </div>
    </TenantLayout>
</template>
