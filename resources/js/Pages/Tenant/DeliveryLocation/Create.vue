<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import DeliveryLocationForm from '@/Components/Tenant/DeliveryLocationForm.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Delivery Locations', href: route('delivery-locations.index') },
    { label: 'New' },
]);
</script>

<template>
    <Head title="New Delivery Location" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200 mt-4">New Delivery Location</h2>
            </div>
        </template>

        <DeliveryLocationForm
            mode="create"
            @cancelled="router.visit(route('delivery-locations.index'))"
        />
    </TenantLayout>
</template>
