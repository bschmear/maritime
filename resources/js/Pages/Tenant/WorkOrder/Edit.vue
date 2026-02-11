<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import WorkOrderForm from '@/Components/Tenant/WorkOrderForm.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    record: {
        type: Object,
        required: true,
    },
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
    imageUrls: {
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
        { label: `WO-${props.record?.work_order_number || props.record?.id || 'Edit'}` },
    ];
});

const handleCancelled = () => {
    router.visit(route('workorders.show', props.record.id));
};
</script>

<template>
    <Head title="Edit Work Order" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
            </div>
        </template>
        <WorkOrderForm
            :record="record"
            :record-type="recordType"
            :form-schema="formSchema"
            :fields-schema="fieldsSchema"
            :enum-options="enumOptions"
            :image-urls="imageUrls"
            :account="account"
            :timezones="timezones"
            :service-items="serviceItems"
            mode="edit"
            @cancelled="handleCancelled"
        />
    </TenantLayout>
</template>