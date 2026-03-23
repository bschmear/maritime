<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import ContractForm from '@/Components/Tenant/ContractForm.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    formSchema: {
        type: Object,
        required: true,
    },
    fieldsSchema: {
        type: Object,
        required: true,
    },
    enumOptions: {
        type: Object,
        default: () => ({}),
    },
    account: {
        type: Object,
        default: null,
    },
    timezones: {
        type: Array,
        default: () => [],
    },
    initialData: {
        type: Object,
        default: () => ({}),
    },
});

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Contracts', href: route('contracts.index') },
    { label: 'Create' },
]);

const handleCancel = () => {
    router.visit(route('contracts.index'));
};
</script>

<template>
    <Head title="Create Contract" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200 mt-4">
                    New contract
                </h2>
            </div>
        </template>

        <div class="w-full flex flex-col space-y-6 mx-auto">
            <ContractForm
                :record="null"
                :form-schema="formSchema"
                :fields-schema="fieldsSchema"
                :enum-options="enumOptions"
                :account="account"
                :timezones="timezones"
                :initial-data="initialData"
                mode="create"
                @cancel="handleCancel"
            />
        </div>
    </TenantLayout>
</template>
