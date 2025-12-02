<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    post: Object,
    relatedPosts: Array,
});
</script>

<template>
    <Head :title="post.title" />

    <AppLayout>
        <!-- Hero Section with Cover Image -->
        <section class="relative h-96 bg-gray-900">
            <img
                v-if="post.cover_image"
                :src="post.cover_image"
                :alt="post.title"
                class="absolute inset-0 w-full h-full object-cover opacity-50"
            />
            <div class="absolute inset-0 bg-gradient-to-t from-gray-900 via-gray-900/50 to-transparent"></div>
            
            <div class="relative h-full max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col justify-end pb-12">
                <!-- Category Badge -->
                <div v-if="post.category" class="mb-4">
                    <Link
                        :href="`/blog/category?slug=${post.category.slug}`"
                        class="inline-block px-4 py-2 bg-secondary-600 hover:bg-secondary-700 text-white text-sm font-semibold rounded-full transition-colors"
                    >
                        {{ post.category.name }}
                    </Link>
                </div>

                <!-- Title -->
                <h1 class="text-4xl sm:text-5xl font-bold text-white mb-4">
                    {{ post.title }}
                </h1>

                <!-- Meta Info -->
                <div class="flex flex-wrap items-center gap-4 text-gray-300">
                    <div class="flex items-center gap-2">
                        <div class="w-10 h-10 bg-gradient-to-br from-secondary-500 to-purple-600 rounded-full flex items-center justify-center text-white font-semibold">
                            {{ post.author.name.charAt(0) }}
                        </div>
                        <span class="font-medium">{{ post.author.name }}</span>
                    </div>
                    <span>•</span>
                    <span>{{ post.published_at }}</span>
                    <span>•</span>
                    <span>{{ post.read_time }}</span>
                </div>
            </div>
        </section>

        <!-- Main Content -->
        <article class="py-12 bg-white dark:bg-gray-900">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Short Description -->
                <div v-if="post.short_description" class="text-xl text-gray-600 dark:text-gray-400 mb-8 pb-8 border-b border-gray-200 dark:border-gray-700 italic">
                    {{ post.short_description }}
                </div>

                <!-- Post Body -->
                <div
                    class="prose prose-lg prose-secondary dark:prose-invert max-w-none
                           prose-headings:font-bold prose-headings:text-gray-900 dark:prose-headings:text-white
                           prose-p:text-gray-700 dark:prose-p:text-gray-300
                           prose-a:text-secondary-600 dark:prose-a:text-secondary-400 prose-a:no-underline hover:prose-a:underline
                           prose-strong:text-gray-900 dark:prose-strong:text-white
                           prose-code:text-secondary-600 dark:prose-code:text-secondary-400
                           prose-blockquote:border-secondary-500 dark:prose-blockquote:border-secondary-400
                           prose-img:rounded-xl prose-img:shadow-lg"
                    v-html="post.body"
                ></div>

                <!-- Tags -->
                <div v-if="post.tags.length > 0" class="mt-12 pt-8 border-t border-gray-200 dark:border-gray-700">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-4">Tags:</h3>
                    <div class="flex flex-wrap gap-2">
                        <Link
                            v-for="tag in post.tags"
                            :key="tag.slug"
                            :href="`/blog/tag?slug=${tag.slug}`"
                            class="px-4 py-2 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded-full text-sm font-medium hover:bg-secondary-100 dark:hover:bg-secondary-900 hover:text-secondary-700 dark:hover:text-secondary-300 transition-colors"
                        >
                            #{{ tag.name }}
                        </Link>
                    </div>
                </div>

                <!-- Share Section -->
                <div class="mt-8 pt-8 border-t border-gray-200 dark:border-gray-700">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-4">Share this post:</h3>
                    <div class="flex gap-3">
                        <a
                            :href="`https://twitter.com/intent/tweet?text=${encodeURIComponent(post.title)}&url=${encodeURIComponent(window.location.href)}`"
                            target="_blank"
                            class="p-3 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-blue-500 hover:text-white transition-colors"
                        >
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/>
                            </svg>
                        </a>
                        <a
                            :href="`https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(window.location.href)}`"
                            target="_blank"
                            class="p-3 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-blue-600 hover:text-white transition-colors"
                        >
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                            </svg>
                        </a>
                        <a
                            :href="`https://www.linkedin.com/shareArticle?mini=true&url=${encodeURIComponent(window.location.href)}&title=${encodeURIComponent(post.title)}`"
                            target="_blank"
                            class="p-3 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-blue-700 hover:text-white transition-colors"
                        >
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
                            </svg>
                        </a>
                    </div>
                </div>
            </div>
        </article>

        <!-- Related Posts -->
        <section v-if="relatedPosts.length > 0" class="py-12 bg-gray-50 dark:bg-gray-800">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-8">Related Posts</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <article
                        v-for="relatedPost in relatedPosts"
                        :key="relatedPost.id"
                        class="group bg-white dark:bg-gray-900 rounded-xl overflow-hidden shadow-lg hover:shadow-2xl transition-all duration-300 hover:-translate-y-2"
                    >
                        <Link :href="`/blog/${relatedPost.slug}`" class="block relative overflow-hidden h-48">
                            <img
                                :src="relatedPost.cover_image"
                                :alt="relatedPost.title"
                                class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500"
                            />
                        </Link>

                        <div class="p-6">
                            <div class="text-sm text-gray-500 dark:text-gray-400 mb-2">
                                {{ relatedPost.published_at }}
                            </div>
                            
                            <Link :href="`/blog/${relatedPost.slug}`">
                                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2 line-clamp-2 group-hover:text-secondary-600 dark:group-hover:text-secondary-400 transition-colors">
                                    {{ relatedPost.title }}
                                </h3>
                            </Link>

                            <p class="text-gray-600 dark:text-gray-400 text-sm line-clamp-2">
                                {{ relatedPost.excerpt }}
                            </p>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <!-- Back to Blog -->
        <div class="py-8 bg-white dark:bg-gray-900">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <Link
                    :href="route('blog')"
                    class="inline-flex items-center gap-2 text-secondary-600 dark:text-secondary-400 hover:text-secondary-700 dark:hover:text-secondary-300 font-semibold transition-colors"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    <span>Back to Blog</span>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>

