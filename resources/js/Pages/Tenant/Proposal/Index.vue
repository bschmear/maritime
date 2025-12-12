<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Proposals" />

    <TenantLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Proposals
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <p>Proposals management coming soon...</p>
                    </div>
                </div>
            </div>
        </div>
    </TenantLayout>
</template>
