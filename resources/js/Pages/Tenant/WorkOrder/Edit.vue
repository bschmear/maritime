<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import Sublist from '@/Components/Tenant/Sublist.vue';
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
    serviceTicket: {
        type: Object,
        default: null,
    },
    estimateThreshold: {
        type: Number,
        default: 20,
    },
});

const breadcrumbItems = computed(() => {
    return [
        { label: 'Home', href: route('dashboard') },
        { label: 'Work Orders', href: route('workorders.index') },
        { label: `WO-${props.record?.work_order_number || props.record?.id || 'Edit'}` },
    ];
});

const isSublistVisible = (sub) => {
    if (!sub?.conditional || typeof sub.conditional !== 'object') {
        return true;
    }
    const { key, value, operator = 'equals' } = sub.conditional;
    const current = props.record[key];
    const boolCurrent = current === true || current === 1;
    switch (operator) {
        case 'equals':
        case 'eq':
            return typeof value === 'boolean' ? boolCurrent === value : current == value;
        case 'not_equals':
        case 'neq':
            return typeof value === 'boolean' ? boolCurrent !== value : current != value;
        default:
            return typeof value === 'boolean' ? boolCurrent === value : current == value;
    }
};

const visibleSublists = computed(() => (props.formSchema?.sublists || []).filter(isSublistVisible));

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
            :service-ticket="serviceTicket"
            :estimate-threshold="estimateThreshold"
            mode="edit"
            @cancelled="handleCancelled"
        />
        <div
            v-if="visibleSublists.length > 0 && formSchema"
            class="col-span-full mt-8 w-full"
        >
            <Sublist
                :key="`work-order-edit-sublist-${record?.id || 'new'}`"
                :parent-record="record"
                parent-domain="WorkOrder"
                :sublists="visibleSublists"
            />
        </div>
    </TenantLayout>
</template>