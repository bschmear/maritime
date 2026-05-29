<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import SchedulerGrid from '@/Components/Tenant/SchedulerGrid.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    technicians: {
        type: Array,
        default: () => [],
    },
    workOrders: {
        type: Array,
        default: () => [],
    },
    locations: {
        type: Array,
        default: () => ['All Locations'],
    },
    scheduleDefaults: {
        type: Object,
        default: () => ({
            workday_hours: 6,
            workday_start_hour: 8,
            allow_overlap: false,
        }),
    },
});

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Service Yard', href: route('serviceyard.index') },
    { label: 'Scheduler' },
]);
</script>

<template>
    <Head title="Service Yard — Scheduler" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div>
                    <h2 class="mt-4 text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        Scheduler
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Work orders only. Schedule deliveries on the
                        <a :href="route('deliveries.delivery-schedule')" class="text-primary-600 hover:underline dark:text-primary-400">delivery board</a>.
                    </p>
                </div>
            </div>
        </template>
        <SchedulerGrid
            work-orders-only
            :technicians="technicians"
            :work-orders="workOrders"
            :locations="locations"
            :schedule-defaults="scheduleDefaults"
        />
    </TenantLayout>
</template>
