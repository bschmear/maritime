<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import SchedulerGrid from '@/Components/Tenant/SchedulerGrid.vue';
import { Head, Link } from '@inertiajs/vue3';
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
                <div class="mt-4 flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                            Scheduler
                        </h2>
                        <p class="mt-1 text-base text-gray-500 dark:text-gray-400">
                            Work orders only. Schedule deliveries on the
                            <a :href="route('deliveries.delivery-schedule')" class="text-primary-600 hover:underline dark:text-primary-400">delivery board</a>.
                        </p>
                    </div>
                    <Link
                        :href="route('serviceyard.index')"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-base font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                    >
                        <span class="material-icons text-xl" aria-hidden="true">home_repair_service</span>
                        Service Yard
                    </Link>
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
