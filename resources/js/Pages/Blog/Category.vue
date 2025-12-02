<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    category: Object,
    posts: Object,
    categories: Array,
    tags: Array,
});
</script>

<template>
    <Head :title="`${category.name} - Blog`" />

    <AppLayout>
        <!-- Hero Section -->
        <section class="relative overflow-hidden bg-gradient-to-br from-secondary-100 via-purple-50 to-pink-100 dark:from-gray-900 dark:via-secondary-950 dark:to-purple-950 py-20">
            <!-- Background decoration -->
            <div class="absolute inset-0 overflow-hidden">
                <div class="absolute -top-40 -right-40 w-80 h-80 bg-purple-400 dark:bg-purple-600 rounded-full mix-blend-multiply dark:mix-blend-normal filter blur-xl opacity-40 dark:opacity-30 animate-blob"></div>
                <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-secondary-400 dark:bg-secondary-600 rounded-full mix-blend-multiply dark:mix-blend-normal filter blur-xl opacity-40 dark:opacity-30 animate-blob animation-delay-2000"></div>
                <div class="absolute top-20 right-1/4 w-60 h-60 bg-cyan-400 dark:bg-cyan-600 rounded-full mix-blend-multiply dark:mix-blend-normal filter blur-3xl opacity-25 dark:opacity-20 animate-blob animation-delay-1000"></div>
            </div>

            <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center">
                    <Link
                        :href="route('blog')"
                        class="inline-flex items-center gap-2 text-secondary-600 dark:text-secondary-400 hover:text-secondary-700 dark:hover:text-secondary-300 font-semibold mb-6 transition-all duration-200 hover:gap-3 group"
                    >
                        <svg class="w-5 h-5 group-hover:-translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        <span>Back to Blog</span>
                    </Link>
                    
                    <!-- Category Badge -->
                    <div class="inline-flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-secondary-600 to-purple-600 text-white rounded-full text-sm font-semibold mb-6 shadow-lg">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                        </svg>
                        <span>Category</span>
                    </div>

                    <h1 class="text-5xl sm:text-6xl font-bold text-gray-900 dark:text-white mb-4 tracking-tight">
                        <span class="bg-gradient-to-r from-secondary-600 via-purple-600 to-pink-600 dark:from-secondary-400 dark:via-purple-400 dark:to-pink-400 bg-clip-text text-transparent">
                            {{ category.name }}
                        </span>
                    </h1>
                    
                    <p v-if="category.description" class="text-xl text-gray-600 dark:text-gray-300 max-w-2xl mx-auto mb-3">
                        {{ category.description }}
                    </p>
                    
                    <div class="inline-flex items-center gap-2 px-4 py-2 bg-white/80 dark:bg-gray-800/80 backdrop-blur-sm rounded-full border border-gray-200 dark:border-gray-700">
                        <svg class="w-4 h-4 text-secondary-600 dark:text-secondary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <span class="text-gray-700 dark:text-gray-300 font-semibold">
                            {{ posts.total }} {{ posts.total === 1 ? 'post' : 'posts' }}
                        </span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Posts Grid -->
        <section class="py-12 bg-gray-50 dark:bg-gray-900">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div v-if="posts.data.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <article
                        v-for="post in posts.data"
                        :key="post.id"
                        class="group bg-white dark:bg-gray-800 rounded-xl overflow-hidden shadow-lg hover:shadow-2xl transition-all duration-300 hover:-translate-y-2 border border-gray-200 dark:border-gray-700"
                    >
                        <Link :href="`/blog/${post.slug}`" class="block relative overflow-hidden h-52">
                            <img
                                :src="post.cover_image"
                                :alt="post.title"
                                class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500"
                            />
                            <div class="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                        </Link>

                        <div class="p-6">
                            <div class="flex items-center gap-4 text-sm text-gray-500 dark:text-gray-400 mb-3 font-medium">
                                <span class="flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    {{ post.published_at }}
                                </span>
                                <span>•</span>
                                <span class="flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    {{ post.read_time }}
                                </span>
                            </div>

                            <Link :href="`/blog/${post.slug}`">
                                <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-3 line-clamp-2 group-hover:text-transparent group-hover:bg-gradient-to-r group-hover:from-secondary-600 group-hover:to-purple-600 group-hover:bg-clip-text transition-all duration-300">
                                    {{ post.title }}
                                </h3>
                            </Link>

                            <p class="text-gray-600 dark:text-gray-400 mb-4 line-clamp-3 leading-relaxed">
                                {{ post.excerpt }}
                            </p>

                            <div class="flex items-center justify-between pt-4 border-t border-gray-200 dark:border-gray-700">
                                <span class="text-sm text-gray-700 dark:text-gray-300 font-semibold">
                                    {{ post.author }}
                                </span>
                                <Link
                                    :href="`/blog/${post.slug}`"
                                    class="inline-flex items-center gap-1 text-secondary-600 dark:text-secondary-400 font-semibold text-sm group-hover:gap-2 transition-all duration-200"
                                >
                                    <span>Read More</span>
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                                    </svg>
                                </Link>
                            </div>
                        </div>
                    </article>
                </div>

                <!-- No Results -->
                <div v-else class="text-center py-16 bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-secondary-100 to-purple-100 dark:from-secondary-900 dark:to-purple-900 rounded-full mb-4">
                        <svg class="w-8 h-8 text-secondary-600 dark:text-secondary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">No posts in this category yet</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-6">Check back later for new content</p>
                    <Link
                        :href="route('blog')"
                        class="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-secondary-600 to-purple-600 hover:from-secondary-700 hover:to-purple-700 text-white font-semibold rounded-lg transition-all duration-200 shadow-lg hover:shadow-xl"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        <span>Browse All Posts</span>
                    </Link>
                </div>

                <!-- Pagination -->
                <div v-if="posts.data.length > 0 && (posts.prev_page_url || posts.next_page_url)" class="mt-12 flex items-center justify-center gap-3">
                    <Link
                        v-if="posts.prev_page_url"
                        :href="posts.prev_page_url"
                        class="px-5 py-2.5 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded-lg border-2 border-gray-300 dark:border-gray-600 hover:border-secondary-500 dark:hover:border-secondary-500 hover:bg-secondary-50 dark:hover:bg-gray-700 transition-all duration-200 font-medium shadow-md"
                    >
                        Previous
                    </Link>
                    <span class="px-5 py-2.5 bg-gradient-to-r from-secondary-100 to-purple-100 dark:from-secondary-900 dark:to-purple-900 text-secondary-700 dark:text-secondary-300 rounded-lg font-semibold border border-secondary-200 dark:border-secondary-700">
                        Page {{ posts.current_page }} of {{ posts.last_page }}
                    </span>
                    <Link
                        v-if="posts.next_page_url"
                        :href="posts.next_page_url"
                        class="px-5 py-2.5 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded-lg border-2 border-gray-300 dark:border-gray-600 hover:border-secondary-500 dark:hover:border-secondary-500 hover:bg-secondary-50 dark:hover:bg-gray-700 transition-all duration-200 font-medium shadow-md"
                    >
                        Next
                    </Link>
                </div>
            </div>
        </section>
    </AppLayout>
</template>
