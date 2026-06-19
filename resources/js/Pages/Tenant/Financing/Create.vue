<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import FinancingForm from '@/Components/Tenant/FinancingForm.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    recordType: { type: String, default: 'financings' },
    formSchema: { type: Object, required: true },
    fieldsSchema: { type: Object, required: true },
    enumOptions: { type: Object, default: () => ({}) },
    initialData: { type: Object, default: () => ({}) },
});

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Financings', href: route('financings.index') },
    { label: 'New' },
]);

const handleSaved = (payload) => {
    if (payload?.recordId != null) {
        router.visit(route('financings.show', payload.recordId));
        return;
    }
    router.visit(route('financings.index'));
};
</script>

<template>
    <Head title="New financing" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <h2 class="mt-4 text-xl font-semibold text-gray-800 dark:text-gray-200">New financing</h2>
            </div>
        </template>

        <FinancingForm
            :schema="formSchema"
            :fields-schema="fieldsSchema"
            :enum-options="enumOptions"
            :prefill="initialData"
            mode="create"
            @saved="handleSaved"
            @cancelled="router.visit(route('financings.index'))"
        />
    </TenantLayout>
</template>
