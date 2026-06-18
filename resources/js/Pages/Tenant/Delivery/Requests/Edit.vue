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
    { label: 'Delivery requests', href: route('deliveries.requests.index') },
    { label: props.record?.display_name ?? 'Request', href: route('deliveries.show', props.record.id) },
    { label: 'Update' },
]);
</script>

<template>
    <Head :title="`Update ${record?.display_name}`" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <h2 class="mt-4 text-xl font-semibold text-gray-800 dark:text-gray-200">
                    Update delivery request
                </h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Changes will reset any in-progress review and resubmit the request for approval.
                </p>
            </div>
        </template>

        <DeliveryForm
            :record="record"
            mode="request-edit"
            :enum-options="enumOptions"
            :customer-addresses="customerAddresses"
            @cancelled="router.visit(route('deliveries.show', record.id))"
        />
    </TenantLayout>
</template>
