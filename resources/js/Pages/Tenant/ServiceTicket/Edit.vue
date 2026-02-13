<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import ServiceTicketForm from '@/Components/Tenant/ServiceTicketForm.vue';
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
    </TenantLayout>
</template>