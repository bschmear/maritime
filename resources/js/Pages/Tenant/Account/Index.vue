<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    accountSections: {
        type: Array,
        required: true,
    },
});

const getColorClasses = (color) => {
    const colors = {
        blue: {
            bg: 'bg-blue-50 dark:bg-blue-900/20',
            icon: 'text-blue-600 dark:text-blue-400',
            hover: 'hover:bg-blue-50 dark:hover:bg-blue-900/30',
            border: 'group-hover:border-blue-300 dark:group-hover:border-blue-600',
        },
        green: {
            bg: 'bg-green-50 dark:bg-green-900/20',
            icon: 'text-green-600 dark:text-green-400',
            hover: 'hover:bg-green-50 dark:hover:bg-green-900/30',
            border: 'group-hover:border-green-300 dark:group-hover:border-green-600',
        },
        purple: {
            bg: 'bg-purple-50 dark:bg-purple-900/20',
            icon: 'text-purple-600 dark:text-purple-400',
            hover: 'hover:bg-purple-50 dark:hover:bg-purple-900/30',
            border: 'group-hover:border-purple-300 dark:group-hover:border-purple-600',
        },
        red: {
            bg: 'bg-red-50 dark:bg-red-900/20',
            icon: 'text-red-600 dark:text-red-400',
            hover: 'hover:bg-red-50 dark:hover:bg-red-900/30',
            border: 'group-hover:border-red-300 dark:group-hover:border-red-600',
        },
        yellow: {
            bg: 'bg-yellow-50 dark:bg-yellow-900/20',
            icon: 'text-yellow-600 dark:text-yellow-400',
            hover: 'hover:bg-yellow-50 dark:hover:bg-yellow-900/30',
            border: 'group-hover:border-yellow-300 dark:group-hover:border-yellow-600',
        },
    };
    return colors[color] || colors.blue;
};
</script>

<template>
    <Head title="Account Management" />

    <TenantLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                        Account Management
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Manage your organization's users, roles, and permissions
                    </p>
                </div>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Account Sections Grid -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="section in accountSections"
                    :key="section.title"
                    :href="section.href"
                    :class="[
                        'group block rounded-lg border border-gray-200 bg-white p-6 shadow-sm transition-all duration-200 hover:shadow-md dark:border-gray-700 dark:bg-gray-800',
                        getColorClasses(section.color).border
                    ]"
                >
                    <div class="flex items-start gap-4">
                        <!-- Icon -->
                        <div :class="[
                            'flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-lg',
                            getColorClasses(section.color).bg
                        ]">
                            <svg
                                :class="['h-6 w-6', getColorClasses(section.color).icon]"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    :d="section.icon"
                                />
                            </svg>
                        </div>

                        <!-- Content -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between gap-2">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                    {{ section.title }}
                                </h3>
                                <!-- Arrow icon -->
                                <svg class="h-5 w-5 flex-shrink-0 text-gray-400 transition-transform group-hover:translate-x-1 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </div>

                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                {{ section.description }}
                            </p>

                            <!-- Stats (if available) -->
                            <div v-if="section.stats" class="mt-3 flex items-center gap-1 text-sm">
                                <span class="text-gray-500 dark:text-gray-400">{{ section.stats.label }}:</span>
                                <span class="font-semibold text-gray-900 dark:text-white">{{ section.stats.value }}</span>
                            </div>
                        </div>
                    </div>
                </Link>
            </div>

        </div>
    </TenantLayout>
</template>