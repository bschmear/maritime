<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import FinancingForm from '@/Components/Tenant/FinancingForm.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    record: { type: Object, required: true },
    recordType: { type: String, default: 'financings' },
    formSchema: { type: Object, required: true },
    fieldsSchema: { type: Object, required: true },
    enumOptions: { type: Object, default: () => ({}) },
});

const label = computed(() => props.record.display_name || `Financing #${props.record.id}`);

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Financings', href: route('financings.index') },
    { label: label.value, href: route('financings.show', props.record.id) },
    { label: 'Edit' },
]);

const handleSaved = () => {
    router.visit(route('financings.show', props.record.id));
};
</script>

<template>
    <Head :title="`Edit ${label}`" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <h2 class="mt-4 text-xl font-semibold text-gray-800 dark:text-gray-200">Edit {{ label }}</h2>
            </div>
        </template>

        <FinancingForm
            :schema="formSchema"
            :fields-schema="fieldsSchema"
            :record="record"
            :enum-options="enumOptions"
            mode="edit"
            @saved="handleSaved"
            @cancelled="router.visit(route('financings.show', record.id))"
        />
    </TenantLayout>
</template>
