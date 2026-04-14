<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import SchedulerGrid from '@/Components/Tenant/SchedulerGrid.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';


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
        <SchedulerGrid />

    </TenantLayout>
</template>
