<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    accounts: {
        type: Array,
        default: () => [],
    },
});

const getTenantUrl = (domain) => {
    if (!domain) return null;
    const protocol = window.location.protocol;
    return `${protocol}//${domain}`;
};
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Dashboard
            </h2>
        </template>

        <div class="py-12 bg-gray-50 dark:bg-gray-900 grow">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <!-- Welcome Banner -->
                <div class="mb-8 overflow-hidden bg-gradient-to-r from-primary-500 to-secondary-500 rounded-2xl shadow-lg">
                    <div class="p-8 sm:p-10">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-2xl sm:text-3xl font-bold text-white mb-2">
                                    Welcome back! 👋
                                </h3>
                                <p class="text-secondary-100 text-sm sm:text-base">
                                    Manage your accounts and access your tenant applications
                                </p>
                            </div>
                            <div class="hidden sm:block">
                                <svg class="w-24 h-24 text-white opacity-20" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Accounts Section -->
                <div class="mb-8">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            Your Accounts
                        </h3>
                        <Link
                            href="/pricing"
                            class="inline-flex items-center px-4 py-2 bg-primary-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-primary-700 focus:bg-primary-700 active:bg-primary-900 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition ease-in-out duration-150"
                        >
                            Create New Account
                        </Link>
                    </div>

                    <div v-if="accounts.length === 0" class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6 text-gray-900 dark:text-gray-100 text-center">
                            <p class="mb-4">You don't have any accounts yet.</p>
                            <Link
                                href="/pricing"
                                class="inline-flex items-center px-4 py-2 bg-primary-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-primary-700"
                            >
                                Get Started
                            </Link>
                        </div>
                    </div>

                    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div
                            v-for="account in accounts"
                            :key="account.id"
                            class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg border border-gray-200 dark:border-gray-700 hover:shadow-md transition-shadow"
                        >
                            <div class="p-6">
                                <div class="flex items-start justify-between mb-4">
                                    <div class="flex-1">
                                        <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-1">
                                            {{ account.name }}
                                        </h4>
                                        <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                                :class="account.is_owner ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:bg-blue-200'">
                                                {{ account.is_owner ? 'Owner' : account.user_role }}
                                            </span>
                                            <span>•</span>
                                            <span>{{ account.users_count }} {{ account.users_count === 1 ? 'user' : 'users' }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-3">
                                    <!-- Tenant Link -->
                                    <div v-if="account.domain" class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                                        <div class="flex-1 min-w-0">
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Tenant URL</p>
                                            <p class="text-sm font-mono text-gray-900 dark:text-white truncate">{{ account.domain }}</p>
                                        </div>
                                        <a
                                            :href="getTenantUrl(account.domain)"
                                            target="_blank"
                                            class="ml-3 inline-flex items-center px-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded-md text-xs font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700"
                                        >
                                            Open
                                            <svg class="ml-1 w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                            </svg>
                                        </a>
                                    </div>

                                    <!-- Owner Actions -->
                                    <div v-if="account.is_owner" class="space-y-2">
                                        <button
                                            class="w-full inline-flex items-center justify-center px-4 py-2 bg-primary-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-primary-700 focus:bg-primary-700 active:bg-primary-900 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition ease-in-out duration-150"
                                        >
                                            Manage Account
                                        </button>
                                        <div class="grid grid-cols-2 gap-2">
                                            <button
                                                class="px-3 py-2 text-xs font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-200 dark:hover:bg-gray-600"
                                            >
                                                View Details
                                            </button>
                                            <button
                                                class="px-3 py-2 text-xs font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-200 dark:hover:bg-gray-600"
                                            >
                                                Add Users
                                            </button>
                                        </div>
                                        <button
                                            class="w-full px-3 py-2 text-xs font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-200 dark:hover:bg-gray-600"
                                        >
                                            Switch Plan
                                        </button>
                                    </div>

                                    <!-- Member View -->
                                    <div v-else class="text-xs text-gray-500 dark:text-gray-400">
                                        You are a {{ account.user_role }} of this account
                                    </div>
                                </div>

                                <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        Created {{ new Date(account.created_at).toLocaleDateString() }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
