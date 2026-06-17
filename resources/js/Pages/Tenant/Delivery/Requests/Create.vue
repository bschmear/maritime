<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import DeliveryForm from '@/Components/Tenant/DeliveryForm.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    record: { type: Object, default: null },
    enumOptions: { type: Object, default: () => ({}) },
    account: { type: Object, default: null },
    canCreateDelivery: { type: Boolean, default: false },
    approverLocationIds: { type: Array, default: () => [] },
});

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Delivery requests', href: route('deliveries.requests.index') },
    { label: 'New request' },
]);

const canApproveAnyLocation = computed(() => props.approverLocationIds.length > 0);
</script>

<template>
    <Head title="Create delivery request" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <h2 class="mt-4 text-xl font-semibold text-gray-800 dark:text-gray-200">Create delivery request</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    <template v-if="canApproveAnyLocation">
                        Submit a request for approval, or schedule directly when you are the approver for the depart-from location.
                    </template>
                    <template v-else>
                        Your request will be sent to the delivery approver for the depart-from location.
                    </template>
                </p>
            </div>
        </template>

        <DeliveryForm
            :record="record"
            mode="request"
            :enum-options="enumOptions"
            :approver-location-ids="approverLocationIds"
            @cancelled="router.visit(route('deliveries.requests.index'))"
        />
    </TenantLayout>
</template>
