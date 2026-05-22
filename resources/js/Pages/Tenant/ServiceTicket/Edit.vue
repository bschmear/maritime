<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import ServiceTicketForm from '@/Components/Tenant/ServiceTicketForm.vue';
import Sublist from '@/Components/Tenant/Sublist.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    record: {
        type: Object,
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
    account: {
        type: Object,
        default: null,
    },
    timezones: {
        type: Array,
        default: () => [],
    },
});

const breadcrumbItems = computed(() => {
    return [
        { label: 'Home', href: route('dashboard') },
        { label: 'Service Tickets', href: route('servicetickets.index') },
        { label: props.record?.display_name || props.record?.uuid?.substring(0, 8) || 'Edit' },
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

// Only Images belong in Sublist here — service items and revisions are covered elsewhere on the ticket UI.
const ALLOWED_SERVICE_TICKET_SUBLIST_DOMAINS = new Set(['InventoryImage']);

const visibleSublists = computed(() =>
    (props.formSchema?.sublists || [])
        .filter(isSublistVisible)
        .filter((sub) => ALLOWED_SERVICE_TICKET_SUBLIST_DOMAINS.has(sub?.domain)),
);

const handleCancelled = () => {
    router.visit(route('servicetickets.show', props.record.id));
};
</script>

<template>
    <Head title="Edit Service Ticket" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
            </div>
        </template>
        <ServiceTicketForm
            :record="record"
            :form-schema="formSchema"
            :fields-schema="fieldsSchema"
            :enum-options="enumOptions"
            :account="account"
            :timezones="timezones"
            mode="edit"
            @cancelled="handleCancelled"
        />
        <div
            v-if="visibleSublists.length > 0 && formSchema"
            class="col-span-full mt-8 w-full"
        >
            <Sublist
                :key="`service-ticket-edit-sublist-${record?.id || 'new'}`"
                :parent-record="record"
                parent-domain="ServiceTicket"
                :sublists="visibleSublists"
            />
        </div>
    </TenantLayout>
</template>