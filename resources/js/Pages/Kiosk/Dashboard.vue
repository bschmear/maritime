<script setup>
import KioskLayout from '@/Layouts/KioskLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    stats: Object,
    recentPosts: Array,
});
</script>

<template>
    <Head title="Kiosk Dashboard" />

    <KioskLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900 dark:text-white-100">Dashboard</h1>
        </template>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
            <!-- Total Posts -->
            <div class="overflow-hidden rounded-xl bg-white dark:bg-navy-900 border border-gray-200 dark:border-navy-700 shadow-sm hover:shadow-md transition-shadow">
                <div class="p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 rounded-lg bg-primary-100 dark:bg-primary-900/30 p-3">
                            <svg class="h-6 w-6 text-primary-600 dark:text-primary-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" />
                            </svg>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="truncate text-sm font-medium text-gray-600 dark:text-white-400">Total Posts</dt>
                                <dd class="text-3xl font-semibold text-gray-900 dark:text-white-100">{{ stats.posts }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
                <div class="bg-primary-50 dark:bg-navy-800/50 px-6 py-3 border-t border-gray-200 dark:border-navy-700/50">
                    <Link :href="route('kiosk.posts.index')" class="text-sm font-medium text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300">
                        View all
                    </Link>
                </div>
            </div>

            <!-- Published Posts -->
            <div class="overflow-hidden rounded-xl bg-white dark:bg-navy-900 border border-gray-200 dark:border-navy-700 shadow-sm hover:shadow-md transition-shadow">
                <div class="p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 rounded-lg bg-green-100 dark:bg-green-900/30 p-3">
                            <svg class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="truncate text-sm font-medium text-gray-600 dark:text-white-400">Published</dt>
                                <dd class="text-3xl font-semibold text-gray-900 dark:text-white-100">{{ stats.published_posts }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
                <div class="bg-green-50 dark:bg-navy-800/50 px-6 py-3 border-t border-gray-200 dark:border-navy-700/50">
                    <Link :href="route('kiosk.posts.index', { published: true })" class="text-sm font-medium text-green-600 dark:text-green-400 hover:text-green-700 dark:hover:text-green-300">
                        View published
                    </Link>
                </div>
            </div>

            <!-- Categories -->
            <div class="overflow-hidden rounded-xl bg-white dark:bg-navy-900 border border-gray-200 dark:border-navy-700 shadow-sm hover:shadow-md transition-shadow">
                <div class="p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 rounded-lg bg-secondary-100 dark:bg-secondary-900/30 p-3">
                            <svg class="h-6 w-6 text-secondary-600 dark:text-secondary-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                            </svg>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="truncate text-sm font-medium text-gray-600 dark:text-white-400">Categories</dt>
                                <dd class="text-3xl font-semibold text-gray-900 dark:text-white-100">{{ stats.categories }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
                <div class="bg-secondary-50 dark:bg-navy-800/50 px-6 py-3 border-t border-gray-200 dark:border-navy-700/50">
                    <Link :href="route('kiosk.categories.index')" class="text-sm font-medium text-secondary-600 dark:text-secondary-400 hover:text-secondary-700 dark:hover:text-secondary-300">
                        Manage categories
                    </Link>
                </div>
            </div>

            <!-- Tags -->
            <div class="overflow-hidden rounded-xl bg-white dark:bg-navy-900 border border-gray-200 dark:border-navy-700 shadow-sm hover:shadow-md transition-shadow">
                <div class="p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 rounded-lg bg-primary-100 dark:bg-primary-900/30 p-3">
                            <svg class="h-6 w-6 text-primary-600 dark:text-primary-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                            </svg>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="truncate text-sm font-medium text-gray-600 dark:text-white-400">Tags</dt>
                                <dd class="text-3xl font-semibold text-gray-900 dark:text-white-100">{{ stats.tags }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
                <div class="bg-primary-50 dark:bg-navy-800/50 px-6 py-3 border-t border-gray-200 dark:border-navy-700/50">
                    <Link :href="route('kiosk.tags.index')" class="text-sm font-medium text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300">
                        Manage tags
                    </Link>
                </div>
            </div>
        </div>

        <!-- Recent Posts -->
<div class="mt-8">
    <div class="overflow-hidden rounded-xl bg-white dark:bg-navy-900 border border-gray-200 dark:border-navy-700 shadow-sm">
        <div class="border-b border-gray-200 dark:border-navy-700 px-6 py-4">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white-100">Recent Posts</h2>
                <div>
                    <Link :href="route('kiosk.posts.create')" class="gradient-btn inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium text-white shadow-md hover:shadow-lg transition-all">
                        <svg class="-ml-1 mr-2 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        New Post
                    </Link>
                </div>
            </div>
        </div>
        <ul class="divide-y divide-gray-200 dark:divide-navy-700">
            <li v-for="post in recentPosts" :key="post.id" class="px-6 py-4 hover:bg-primary-50 dark:hover:bg-navy-800/50 transition-colors">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <Link :href="route('kiosk.posts.show', post.id)" class="text-sm font-medium text-gray-900 dark:text-white-100 hover:text-primary-600 dark:hover:text-primary-400 transition-colors">
                            {{ post.title }}
                        </Link>
                        <div class="mt-1 flex items-center space-x-4 text-sm text-gray-600 dark:text-white-400">
                            <span v-if="post.category">{{ post.category.name }}</span>
                            <span>by {{ post.user.name }}</span>
                            <span>{{ new Date(post.created_at).toLocaleDateString() }}</span>
                        </div>
                    </div>
                    <div class="ml-4 flex items-center space-x-2">
                        <span v-if="post.published" class="inline-flex rounded-full bg-green-100 dark:bg-green-900/30 px-2 py-1 text-xs font-semibold text-green-800 dark:text-green-400">
                            Published
                        </span>
                        <span v-else class="inline-flex rounded-full bg-gray-100 dark:bg-gray-700/50 px-2 py-1 text-xs font-semibold text-gray-800 dark:text-gray-400">
                            Draft
                        </span>
                        <Link :href="route('kiosk.posts.edit', post.id)" class="text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300 transition-colors">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                        </Link>
                    </div>
                </div>
            </li>
            <li v-if="!recentPosts || recentPosts.length === 0" class="px-6 py-12 text-center flex flex-col">
                <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white-100">No posts</h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-white-400">Get started by creating a new post.</p>
                <div class="mt-6">
                    <div>
                    <Link :href="route('kiosk.posts.create')" class="gradient-btn inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium text-white shadow-md hover:shadow-lg transition-all">
                        <svg class="-ml-1 mr-2 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        New Post
                    </Link>
                </div>
                </div>
            </li>
        </ul>
    </div>
</div>

        <!-- Quick Actions -->
        <div class="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <Link :href="route('kiosk.categories.create')" class="group relative overflow-hidden rounded-xl bg-white dark:bg-navy-900 border border-gray-200 dark:border-navy-700 p-6 shadow-sm hover:shadow-md transition-all hover:scale-105">
                <div class="flex items-center">
                    <div class="flex-shrink-0 rounded-lg bg-secondary-100 dark:bg-secondary-900/30 p-3 group-hover:bg-secondary-200 dark:group-hover:bg-secondary-900/40 transition-colors">
                        <svg class="h-6 w-6 text-secondary-600 dark:text-secondary-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white-100">Add Category</h3>
                        <p class="mt-1 text-sm text-gray-600 dark:text-white-400">Create a new category</p>
                    </div>
                </div>
            </Link>

            <Link :href="route('kiosk.tags.create')" class="group relative overflow-hidden rounded-xl bg-white dark:bg-navy-900 border border-gray-200 dark:border-navy-700 p-6 shadow-sm hover:shadow-md transition-all hover:scale-105">
                <div class="flex items-center">
                    <div class="flex-shrink-0 rounded-lg bg-primary-100 dark:bg-primary-900/30 p-3 group-hover:bg-primary-200 dark:group-hover:bg-primary-900/40 transition-colors">
                        <svg class="h-6 w-6 text-primary-600 dark:text-primary-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white-100">Add Tag</h3>
                        <p class="mt-1 text-sm text-gray-600 dark:text-white-400">Create a new tag</p>
                    </div>
                </div>
            </Link>

            <Link :href="route('kiosk.faqs.create')" class="group relative overflow-hidden rounded-xl bg-white dark:bg-navy-900 border border-gray-200 dark:border-navy-700 p-6 shadow-sm hover:shadow-md transition-all hover:scale-105">
                <div class="flex items-center">
                    <div class="flex-shrink-0 rounded-lg bg-primary-100 dark:bg-primary-900/30 p-3 group-hover:bg-primary-200 dark:group-hover:bg-primary-900/40 transition-colors">
                        <svg class="h-6 w-6 text-primary-600 dark:text-primary-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white-100">Add FAQ</h3>
                        <p class="mt-1 text-sm text-gray-600 dark:text-white-400">Create a new FAQ</p>
                    </div>
                </div>
            </Link>
        </div>
    </KioskLayout>
</template>
