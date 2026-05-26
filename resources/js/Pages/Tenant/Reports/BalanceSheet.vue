<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    recordTitle: {
        type: String,
        default: 'Balance Sheet',
    },
});

const breadcrumbItems = computed(() => {
    return [
        { label: 'Home', href: route('dashboard') },
        { label: 'Reports' },
        { label: props.recordTitle },
    ];
});
</script>

<template>
    <Head :title="props.recordTitle" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full flex items-center justify-between">
                <Breadcrumb :items="breadcrumbItems" />
                <Link
                    :href="route('reports.pnl')"
                    class="text-sm font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300"
                >
                    Profit & Loss
                </Link>
            </div>
        </template>

       
    </TenantLayout>
</template>

