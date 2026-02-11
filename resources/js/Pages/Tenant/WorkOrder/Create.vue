<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import WorkOrderForm from '@/Components/Tenant/WorkOrderForm.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    recordType: {
        type: String,
        required: true,
    },
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
    initialData: {
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
    serviceItems: {
        type: Array,
        default: () => [],
    },
});

const breadcrumbItems = computed(() => {
    return [
        { label: 'Home', href: route('dashboard') },
        { label: 'Work Orders', href: route('workorders.index') },
        { label: 'Create Work Order' },
    ];
});
</script>

<template>
    <Head title="Create Work Order" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
            </div>
        </template>
        <WorkOrderForm
            :record="null"
            :record-type="recordType"
            :form-schema="formSchema"
            :fields-schema="fieldsSchema"
            :enum-options="enumOptions"
            :initial-data="initialData"
            :account="account"
            :timezones="timezones"
            :service-items="serviceItems"
            mode="create"
        />
    </TenantLayout>
</template>