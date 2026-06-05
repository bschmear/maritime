<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import SalesFlowHorizontal from '@/Components/Tenant/Sales/SalesFlowHorizontal.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Sales', href: route('sales.index') },
    { label: 'Process map' },
]);
</script>

<template>
    <Head title="Sales process map" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
            </div>
            <div class="flex flex-wrap items-start justify-between gap-4 mt-4">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                        End-to-end sales flow
                    </h2>
                    <p class="mt-1 max-w-3xl text-sm text-gray-500 dark:text-gray-400">
                        From first contact through closed transaction — use this map to train staff and see how
                        leads, opportunities, estimates, deals, service, and delivery connect in Helmful.
                    </p>
                </div>
                <Link
                    :href="route('sales.index')"
                    class="inline-flex items-center gap-1 rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                >
                    <span class="material-icons text-base">arrow_back</span>
                    Sales home
                </Link>
            </div>
        </template>

        <SalesFlowHorizontal />
    </TenantLayout>
</template>
