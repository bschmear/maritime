<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import ContractForm from '@/Components/Tenant/ContractForm.vue';
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

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Contracts', href: route('contracts.index') },
    { label: props.record.contract_number || `Contract #${props.record.id}` },
]);

const handleCancel = () => {
    router.visit(route('contracts.show', props.record.id));
};
</script>

<template>
    <Head title="Edit Contract" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
            </div>
        </template>

        <ContractForm
            :record="record"
            :form-schema="formSchema"
            :fields-schema="fieldsSchema"
            :enum-options="enumOptions"
            :account="account"
            :timezones="timezones"
            mode="edit"
            @cancel="handleCancel"
        />
    </TenantLayout>
</template>
