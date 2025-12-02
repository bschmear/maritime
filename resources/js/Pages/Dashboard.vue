<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Dashboard
            </h2>
        </template>

        <div class="py-12 bg-gray-50 dark:bg-gray-900">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <!-- Welcome Banner -->
                <div class="mb-8 overflow-hidden bg-gradient-to-r from-secondary-600 to-purple-600 rounded-2xl shadow-lg">
                    <div class="p-8 sm:p-10">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-2xl sm:text-3xl font-bold text-white mb-2">
                                    Welcome back! 👋
                                </h3>
                                <p class="text-secondary-100 text-sm sm:text-base">
                                    Ready to create amazing social media content with AI?
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

                <!-- Stats Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <!-- Stat Card 1 -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-6 border border-gray-100 dark:border-gray-700 hover:shadow-lg transition-shadow">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900/30 rounded-xl flex items-center justify-center">
                                <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                </svg>
                            </div>
                            <span class="text-xs font-semibold text-green-600 dark:text-green-400 bg-green-100 dark:bg-green-900/30 px-2 py-1 rounded-full">
                                +12%
                            </span>
                        </div>
                        <h4 class="text-gray-500 dark:text-gray-400 text-sm font-medium mb-1">Connected Accounts</h4>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">3</p>
                    </div>

                    <!-- Stat Card 2 -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-6 border border-gray-100 dark:border-gray-700 hover:shadow-lg transition-shadow">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-purple-100 dark:bg-purple-900/30 rounded-xl flex items-center justify-center">
                                <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                                </svg>
                            </div>
                            <span class="text-xs font-semibold text-green-600 dark:text-green-400 bg-green-100 dark:bg-green-900/30 px-2 py-1 rounded-full">
                                +24%
                            </span>
                        </div>
                        <h4 class="text-gray-500 dark:text-gray-400 text-sm font-medium mb-1">AI Posts Generated</h4>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">127</p>
                    </div>

                    <!-- Stat Card 3 -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-6 border border-gray-100 dark:border-gray-700 hover:shadow-lg transition-shadow">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-pink-100 dark:bg-pink-900/30 rounded-xl flex items-center justify-center">
                                <svg class="w-6 h-6 text-pink-600 dark:text-pink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <span class="text-xs font-semibold text-blue-600 dark:text-blue-400 bg-blue-100 dark:bg-blue-900/30 px-2 py-1 rounded-full">
                                Active
                            </span>
                        </div>
                        <h4 class="text-gray-500 dark:text-gray-400 text-sm font-medium mb-1">Scheduled Posts</h4>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">18</p>
                    </div>

                    <!-- Stat Card 4 -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-6 border border-gray-100 dark:border-gray-700 hover:shadow-lg transition-shadow">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-green-100 dark:bg-green-900/30 rounded-xl flex items-center justify-center">
                                <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                </svg>
                            </div>
                            <span class="text-xs font-semibold text-green-600 dark:text-green-400 bg-green-100 dark:bg-green-900/30 px-2 py-1 rounded-full">
                                +18%
                            </span>
                        </div>
                        <h4 class="text-gray-500 dark:text-gray-400 text-sm font-medium mb-1">Engagement Rate</h4>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">4.2%</p>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Generate Content Card -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-8 border border-gray-100 dark:border-gray-700">
                        <div class="flex items-start justify-between mb-6">
                            <div>
                                <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">
                                    Generate AI Content
                                </h3>
                                <p class="text-gray-600 dark:text-gray-400 text-sm">
                                    Create engaging posts for your social media platforms
                                </p>
                            </div>
                            <div class="w-12 h-12 bg-gradient-to-br from-secondary-500 to-purple-600 rounded-xl flex items-center justify-center">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                            </div>
                        </div>
                        <button class="w-full px-6 py-3 bg-gradient-to-r from-secondary-600 to-purple-600 hover:from-secondary-700 hover:to-purple-700 text-white font-semibold rounded-xl transition-all duration-200 shadow-lg hover:shadow-xl">
                            Start Creating
                        </button>
                    </div>

                    <!-- Connect Accounts Card -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-8 border border-gray-100 dark:border-gray-700">
                        <div class="flex items-start justify-between mb-6">
                            <div>
                                <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">
                                    Connect Platforms
                                </h3>
                                <p class="text-gray-600 dark:text-gray-400 text-sm">
                                    Link your social media accounts to get started
                                </p>
                            </div>
                            <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-cyan-600 rounded-xl flex items-center justify-center">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                </svg>
                            </div>
                        </div>
                        <button class="w-full px-6 py-3 bg-gray-900 dark:bg-white hover:bg-gray-800 dark:hover:bg-gray-100 text-white dark:text-gray-900 font-semibold rounded-xl transition-all duration-200 shadow-lg hover:shadow-xl">
                            Connect Accounts
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
