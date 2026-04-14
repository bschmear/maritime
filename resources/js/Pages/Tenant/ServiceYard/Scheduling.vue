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
    { label: 'Scheduling' },
]);
</script>

<template>
    <Head title="Scheduling" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <h2 class="mt-4 text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Scheduling
                </h2>
            </div>
        </template>
        <SchedulerGrid
            :technicians="technicians"
            :work-orders="workOrders"
            :locations="locations"
            :schedule-defaults="scheduleDefaults"
        />
    </TenantLayout>
</template>
