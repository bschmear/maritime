<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import DeliveryForm from '@/Components/Tenant/DeliveryForm.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    enumOptions: { type: Object, default: () => ({}) },
    /** Initial form state when opening from a transaction or work order (see DeliveryController::create). */
    prefill: { type: Object, default: null },
    customerAddresses: { type: Array, default: () => [] },
    account: { type: Object, default: null },
});

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Deliveries', href: route('deliveries.index') },
    { label: 'New Delivery' },
]);
</script>

<template>
    <Head title="New Delivery" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200 mt-4">New Delivery</h2>
            </div>
        </template>

        <DeliveryForm
            :record="prefill"
            mode="create"
            :enum-options="enumOptions"
            :customer-addresses="customerAddresses"
            @cancelled="router.visit(route('deliveries.index'))"
        />
    </TenantLayout>
</template>
