<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import DeliveryScheduler from '@/Components/Tenant/DeliveryScheduler.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref } from 'vue';

const props = defineProps({
    record: { type: Object, required: true },
    enumOptions: { type: Object, default: () => ({}) },
    account: { type: Object, default: null }
});

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Deliveries', href: route('deliveries.index') },
    { label: 'Schedule' },
]);

const googleMapsDirectionsUrl = computed(() => {
    const origin = mapsPointForLocation(locationRecord.value);
    const dest = mapsPointForDeliveryDestination(props.record);
    if (!origin || !dest) return null;
    return `https://www.google.com/maps/dir/?${new URLSearchParams({
        api: '1',
        origin,
        destination: dest,
        travelmode: 'driving',
    }).toString()}`;
});
</script>

<template>
    <Head title="Delivery - Schedule" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200 mt-4">
                    Schedule Deliveries
                </h2>
            </div>
        </template>
        <DeliveryScheduler></DeliveryScheduler>
    </TenantLayout>
</template>
