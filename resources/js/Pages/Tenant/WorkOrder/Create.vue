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
    serviceTicket: {
        type: Object,
        default: null,
    },
    serviceTicketItems: {
        type: Array,
        default: () => [],
    },
    estimateThreshold: {
        type: Number,
        default: 20,
    },
});

const breadcrumbItems = computed(() => {
    const items = [
        { label: 'Home', href: route('dashboard') },
        { label: 'Work Orders', href: route('workorders.index') },
    ];
    if (props.serviceTicket) {
        items.push({ label: props.serviceTicket.service_ticket_number, href: route('servicetickets.show', props.serviceTicket.id) });
        items.push({ label: 'Create Work Order' });
    } else {
        items.push({ label: 'Create Work Order' });
    }
    return items;
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
            :service-ticket="serviceTicket"
            :service-ticket-items="serviceTicketItems"
            :estimate-threshold="estimateThreshold"
            mode="create"
        />
    </TenantLayout>
</template>