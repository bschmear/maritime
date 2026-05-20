<script setup>
import KioskLayout from '@/Layouts/KioskLayout.vue';
import ApexPieChart from '@/Components/Charts/ApexPieChart.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    stats: Object,
    charts: Object,
    recentPosts: Array,
});
</script>

<template>
    <Head title="Kiosk Dashboard" />

    <KioskLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Dashboard</h1>
        </template>

        <!-- Stats with pie charts -->
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <!-- Posts: published vs draft -->
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm transition-shadow hover:shadow-md dark:border-gray-800 dark:bg-gray-950">
                <div class="flex items-center justify-between gap-4 p-5">
                    <dl class="min-w-0 flex-1">
                        <dt class="truncate text-sm font-medium text-gray-600 dark:text-gray-400">Total posts</dt>
                        <dd class="text-3xl font-semibold text-gray-900 dark:text-white">{{ charts.posts.total }}</dd>
                        <dd class="mt-1 text-xs text-gray-500 dark:text-gray-400">Hover chart for breakdown</dd>
                    </dl>
                    <ApexPieChart
                        :series="charts.posts.series"
                        :labels="charts.posts.labels"
                        :colors="charts.posts.colors"
                    />
                </div>
                <div class="border-t border-gray-200 bg-primary-50 px-5 py-3 dark:border-gray-800 dark:bg-gray-900/50">
                    <Link :href="route('kiosk.posts.index')" class="text-sm font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300">
                        View all posts
                    </Link>
                </div>
            </div>

            <!-- Categories: posts per category -->
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm transition-shadow hover:shadow-md dark:border-gray-800 dark:bg-gray-950">
                <div class="flex items-center justify-between gap-4 p-5">
                    <dl class="min-w-0 flex-1">
                        <dt class="truncate text-sm font-medium text-gray-600 dark:text-gray-400">Categories</dt>
                        <dd class="text-3xl font-semibold text-gray-900 dark:text-white">{{ charts.categories.total }}</dd>
                        <dd class="mt-1 text-xs text-gray-500 dark:text-gray-400">Posts per category</dd>
                    </dl>
                    <ApexPieChart
                        :series="charts.categories.series"
                        :labels="charts.categories.labels"
                        :colors="charts.categories.colors"
                    />
                </div>
                <div class="border-t border-gray-200 bg-gray-50 px-5 py-3 dark:border-gray-800 dark:bg-gray-900/50">
                    <Link :href="route('kiosk.categories.index')" class="text-sm font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300">
                        Manage categories
                    </Link>
                </div>
            </div>

            <!-- Support tickets: by status -->
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm transition-shadow hover:shadow-md dark:border-gray-800 dark:bg-gray-950">
                <div class="flex items-center justify-between gap-4 p-5">
                    <dl class="min-w-0 flex-1">
                        <dt class="truncate text-sm font-medium text-gray-600 dark:text-gray-400">Support tickets</dt>
                        <dd class="text-3xl font-semibold text-gray-900 dark:text-white">{{ charts.supportTickets.total }}</dd>
                        <dd class="mt-1 text-xs text-gray-500 dark:text-gray-400">By status</dd>
                    </dl>
                    <ApexPieChart
                        :series="charts.supportTickets.series"
                        :labels="charts.supportTickets.labels"
                        :colors="charts.supportTickets.colors"
                    />
                </div>
                <div class="border-t border-gray-200 bg-gray-50 px-5 py-3 dark:border-gray-800 dark:bg-gray-900/50">
                    <Link :href="route('kiosk.support-tickets.index')" class="text-sm font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300">
                        View tickets
                    </Link>
                </div>
            </div>
        </div>

        <!-- Recent Posts -->
<div class="mt-8">
    <div class="overflow-hidden rounded-xl bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 shadow-sm">
        <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-800">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Recent Posts</h2>
                <div>
                    <Link :href="route('kiosk.posts.create')" class="gradient-btn inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors">
                        <svg class="-ml-1 mr-2 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        New Post
                    </Link>
                </div>
            </div>
        </div>
        <ul class="divide-y divide-gray-200 dark:divide-gray-800">
            <li v-for="post in recentPosts" :key="post.id" class="px-6 py-4 transition-colors hover:bg-gray-50 dark:hover:bg-gray-900/50">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <Link :href="route('kiosk.posts.show', post.id)" class="text-sm font-medium text-gray-900 dark:text-white hover:text-primary-600 dark:hover:text-primary-400 transition-colors">
                            {{ post.title }}
                        </Link>
                        <div class="mt-1 flex items-center space-x-4 text-sm text-gray-600 dark:text-gray-400">
                            <span v-if="post.category">{{ post.category.name }}</span>
                            <span>by {{ post.user.name }}</span>
                            <span>{{ new Date(post.created_at).toLocaleDateString() }}</span>
                        </div>
                    </div>
                    <div class="ml-4 flex items-center space-x-2">
                        <span v-if="post.published" class="inline-flex rounded-full bg-primary-100 px-2 py-1 text-xs font-semibold text-primary-800 dark:bg-primary-900/40 dark:text-primary-300">
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
                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No posts</h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Get started by creating a new post.</p>
                <div class="mt-6">
                    <Link :href="route('kiosk.posts.create')" class="gradient-btn inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors">
                        <svg class="-ml-1 mr-2 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        New Post
                    </Link>
                </div>
            </li>
        </ul>
    </div>
</div>

        <!-- Quick Actions -->
        <div class="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <Link :href="route('kiosk.categories.create')" class="group relative overflow-hidden rounded-xl bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 p-6 shadow-sm hover:shadow-md transition-all hover:scale-105">
                <div class="flex items-center">
                    <div class="flex-shrink-0 rounded-lg bg-gray-100 p-3 transition-colors group-hover:bg-gray-200 dark:bg-gray-800 dark:group-hover:bg-gray-700">
                        <svg class="h-6 w-6 text-gray-700 dark:text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Add Category</h3>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Create a new category</p>
                    </div>
                </div>
            </Link>

            <Link :href="route('kiosk.tags.create')" class="group relative overflow-hidden rounded-xl bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 p-6 shadow-sm hover:shadow-md transition-all hover:scale-105">
                <div class="flex items-center">
                    <div class="flex-shrink-0 rounded-lg bg-primary-100 dark:bg-primary-900/30 p-3 group-hover:bg-primary-200 dark:group-hover:bg-primary-900/40 transition-colors">
                        <svg class="h-6 w-6 text-primary-600 dark:text-primary-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Add Tag</h3>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Create a new tag</p>
                    </div>
                </div>
            </Link>

            <Link :href="route('kiosk.faqs.create')" class="group relative overflow-hidden rounded-xl bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 p-6 shadow-sm hover:shadow-md transition-all hover:scale-105">
                <div class="flex items-center">
                    <div class="flex-shrink-0 rounded-lg bg-primary-100 dark:bg-primary-900/30 p-3 group-hover:bg-primary-200 dark:group-hover:bg-primary-900/40 transition-colors">
                        <svg class="h-6 w-6 text-primary-600 dark:text-primary-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Add FAQ</h3>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Create a new FAQ</p>
                    </div>
                </div>
            </Link>
        </div>
    </KioskLayout>
</template>
