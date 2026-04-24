<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import DeliveryScheduler from '@/Components/Tenant/DeliveryScheduler.vue';
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    locationOptions: { type: Array, default: () => [] },
    enumOptions: { type: Object, default: () => ({}) },
    account: { type: Object, default: null },
});

const selectedLocationId = ref('');

const filterLocationId = computed(() => {
    const v = selectedLocationId.value;
    if (v === '' || v === null || v === undefined) {
        return null;
    }
    const n = Number(v);
    return Number.isFinite(n) && n > 0 ? n : null;
});

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Deliveries', href: route('deliveries.index') },
    { label: 'Schedule' },
]);
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
        <div v-if="locationOptions.length" class="mb-4 max-w-md">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Depart-from location</label>
            <select v-model="selectedLocationId" class="input-style w-full">
                <option value="">All locations</option>
                <option v-for="loc in locationOptions" :key="loc.id" :value="String(loc.id)">
                    {{ loc.display_name }}
                </option>
            </select>
        </div>
        <DeliveryScheduler :filter-location-id="filterLocationId" />
    </TenantLayout>
</template>
