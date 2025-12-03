<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';

defineProps({
    account: {
        type: Object,
        default: null,
    },
});
</script>

<template>
    <Head title="Tenant Dashboard" />

    <AppLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Tenant Dashboard
            </h2>
        </template>

        <div class="py-12 bg-gray-50 dark:bg-gray-900">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <!-- Welcome Banner -->
                <div class="mb-8 overflow-hidden bg-gradient-to-r from-blue-600 to-purple-600 rounded-2xl shadow-lg">
                    <div class="p-8 sm:p-10">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-2xl sm:text-3xl font-bold text-white mb-2">
                                    Welcome to your Tenant Dashboard!
                                </h3>
                                <p class="text-blue-100 text-sm sm:text-base">
                                    Manage your tenant resources and data
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <Link
                        href="/contacts"
                        class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-6 border border-gray-100 dark:border-gray-700 hover:shadow-lg transition-shadow"
                    >
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900/30 rounded-xl flex items-center justify-center">
                                <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                            </div>
                        </div>
                        <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-1">Contacts</h4>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Manage your contacts</p>
                    </Link>

                    <!-- Add more quick action cards here -->
                </div>
            </div>
        </div>
    </AppLayout>
</template>

