<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import DeliveryForm from '@/Components/Tenant/DeliveryForm.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    record: { type: Object, required: true },
    enumOptions: { type: Object, default: () => ({}) },
    customerAddresses: { type: Array, default: () => [] },
    account: { type: Object, default: null },
});

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Deliveries', href: route('deliveries.index') },
    { label: props.record?.display_name ?? 'Delivery', href: route('deliveries.show', props.record.id) },
    { label: 'Edit' },
]);
</script>

<template>
    <Head :title="`Edit ${record?.display_name}`" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200 mt-4">
                    Edit {{ record?.display_name }}
                </h2>
            </div>
        </template>

        <DeliveryForm
            :record="record"
            mode="edit"
            :enum-options="enumOptions"
            :customer-addresses="customerAddresses"
            @cancelled="router.visit(route('deliveries.show', record.id))"
        />
    </TenantLayout>
</template>
