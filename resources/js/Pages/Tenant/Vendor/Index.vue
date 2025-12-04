<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    recordTitle: {
        type: String,
        default: 'Vendor',
    },
    pluralTitle: {
        type: String,
        default: 'Vendors',
    },
});

const breadcrumbItems = computed(() => {
    return [
        { label: 'Home', href: route('dashboard') },
        { label: props.pluralTitle },
    ];
});
</script>

<template>
    <Head :title="recordTitle" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200 mt-4">
                    {{ recordTitle }}
                </h2>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <p>Vendors management coming soon...</p>
                    </div>
                </div>
            </div>
        </div>
    </TenantLayout>
</template>
