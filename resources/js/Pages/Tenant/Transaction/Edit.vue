<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import TransactionForm from '@/Components/Tenant/TransactionForm.vue';
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
    { label: 'Transactions', href: route('transactions.index') },
    { label: props.record.title || `Deal #${props.record.sequence}` },
]);

const handleCancel = () => {
    router.visit(route('transactions.show', props.record.id));
};
</script>

<template>
    <Head title="Edit Transaction" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
            </div>
        </template>

        <TransactionForm
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
