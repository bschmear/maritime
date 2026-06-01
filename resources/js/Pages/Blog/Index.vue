<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import FeaturePageCta from '@/Components/Features/FeaturePageCta.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    posts: Object,
    categories: Array,
    tags: Array,
    filters: Object,
});

const search = ref(props.filters.search || '');
const selectedCategory = ref(props.filters.category || '');
const selectedTag = ref(props.filters.tag || '');

const performSearch = () => {
    router.get(route('blog'), {
        search: search.value,
        category: selectedCategory.value,
        tag: selectedTag.value,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const clearFilters = () => {
    search.value = '';
    selectedCategory.value = '';
    selectedTag.value = '';
    router.get(route('blog'));
};
</script>

<template>
    <Head title="Blog" />

    <AppLayout>
        <!-- Hero Section -->
        <section class="relative border-b border-gray-200 bg-primary-50 py-20 dark:border-gray-800 dark:bg-gray-900 px-4 sm:px-6 lg:px-8">
            <div class="relative mx-auto max-w-7xl">
                <div class="text-center">
                    <div class="mb-6 inline-flex items-center gap-2 rounded-full border border-primary-200/50 bg-primary-100 px-4 py-2 text-sm font-medium text-primary-700 backdrop-blur-sm dark:border-primary-700/50 dark:bg-primary-900/50 dark:text-primary-300">
                        <span class="material-icons text-base leading-none">auto_stories</span>
                        <span>Insights & Resources</span>
                    </div>

                    <h1 class="mb-4 text-5xl font-bold tracking-tight text-gray-900 dark:text-white sm:text-6xl">
                        Our <span class="text-primary-600 dark:text-primary-400">Blog</span>
                    </h1>
                    <p class="mx-auto max-w-2xl text-xl text-gray-600 dark:text-gray-300">
                        Insights, tips, and stories about social media management and AI
                    </p>
                </div>
            </div>
        </section>

        <!-- Main Content -->
        <section class="py-12 bg-gray-50 dark:bg-gray-900">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
                    <!-- Sidebar -->
                    <aside class="lg:col-span-1">
                        <div class="sticky top-8 space-y-6">
                            <!-- Search -->
                            <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-lg border border-gray-200 dark:border-gray-700">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Search</h3>
                                <div class="relative">
                                    <input
                                        v-model="search"
                                        @keyup.enter="performSearch"
                                        type="text"
                                        placeholder="Search posts..."
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-shadow"
                                    />
                                    <button
                                        @click="performSearch"
                                        class="absolute right-2 top-1/2 -translate-y-1/2 p-2 text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Categories -->
                            <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-lg border border-gray-200 dark:border-gray-700">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Categories</h3>
                                <div class="space-y-2">
                                    <button
                                        @click="selectedCategory = ''; performSearch()"
                                        :class="[
                                            'w-full text-left px-3 py-2 rounded-lg transition-all duration-200',
                                            !selectedCategory
                                                ? 'bg-primary-600 dark:bg-primary-600 text-white font-medium shadow-md'
                                                : 'text-gray-700 dark:text-gray-300 hover:bg-primary-50 dark:hover:bg-gray-700'
                                        ]"
                                    >
                                        All Categories
                                    </button>
                                    <button
                                        v-for="category in categories"
                                        :key="category.id"
                                        @click="selectedCategory = category.slug; performSearch()"
                                        :class="[
                                            'w-full text-left px-3 py-2 rounded-lg transition-all duration-200 flex items-center justify-between',
                                            selectedCategory === category.slug
                                                ? 'bg-primary-600 dark:bg-primary-600 text-white font-medium shadow-md'
                                                : 'text-gray-700 dark:text-gray-300 hover:bg-primary-50 dark:hover:bg-gray-700'
                                        ]"
                                    >
                                        <span>{{ category.name }}</span>
                                        <span :class="selectedCategory === category.slug ? 'text-white/80' : 'text-gray-500 dark:text-gray-400'" class="text-sm">({{ category.posts_count }})</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Tags -->
                            <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-lg border border-gray-200 dark:border-gray-700">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Popular Tags</h3>
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        v-for="tag in tags"
                                        :key="tag.id"
                                        type="button"
                                        @click.prevent="selectedTag = tag.slug; performSearch()"
                                        :class="[
                                            'px-3 py-1.5 rounded-full text-sm font-medium transition-all duration-200 cursor-pointer',
                                            selectedTag === tag.slug
                                                ? 'bg-primary-600 text-white shadow-md'
                                                : 'bg-primary-50 dark:bg-gray-700 text-primary-700 dark:text-primary-300 hover:bg-primary-100 dark:hover:bg-gray-600'
                                        ]"
                                    >
                                        {{ tag.name }}
                                    </button>
                                </div>
                            </div>

                            <!-- Clear Filters -->
                            <button
                                v-if="search || selectedCategory || selectedTag"
                                @click="clearFilters"
                                class="w-full px-4 py-2.5 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded-lg border-2 border-gray-300 dark:border-gray-600 hover:border-primary-500 dark:hover:border-primary-500 hover:bg-primary-50 dark:hover:bg-gray-700 transition-all duration-200 font-medium shadow-md"
                            >
                                Clear Filters
                            </button>
                        </div>
                    </aside>

                    <!-- Posts Grid -->
                    <div class="lg:col-span-3">
                        <!-- Active Filters -->
                        <div v-if="search || selectedCategory || selectedTag" class="mb-6 flex flex-wrap gap-2 items-center">
                            <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Active filters:</span>
                            <span v-if="search" class="inline-flex items-center gap-1 px-3 py-1.5 bg-primary-100 dark:bg-primary-900/50 text-primary-800 dark:text-primary-300 rounded-full text-sm font-medium border border-primary-200 dark:border-primary-700">
                                Search: "{{ search }}"
                            </span>
                            <span v-if="selectedCategory" class="inline-flex items-center gap-1 px-3 py-1.5 bg-primary-100 dark:bg-primary-900/50 text-primary-800 dark:text-primary-300 rounded-full text-sm font-medium border border-primary-200 dark:border-primary-700">
                                Category: {{ categories.find(c => c.slug === selectedCategory)?.name }}
                            </span>
                            <span v-if="selectedTag" class="inline-flex items-center gap-1 px-3 py-1.5 bg-primary-100 dark:bg-primary-900/50 text-primary-800 dark:text-primary-300 rounded-full text-sm font-medium border border-primary-200 dark:border-primary-700">
                                Tag: {{ tags.find(t => t.slug === selectedTag)?.name }}
                            </span>
                        </div>

                        <!-- Posts -->
                        <div v-if="posts.data.length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <article
                                v-for="post in posts.data"
                                :key="post.id"
                                class="group bg-white dark:bg-gray-800 rounded-xl overflow-hidden shadow-lg hover:shadow-2xl transition-all duration-300 hover:-translate-y-2 border border-gray-200 dark:border-gray-700"
                            >
                                <!-- Image -->
                                <Link :href="`/blog/${post.slug}`" class="block relative overflow-hidden h-52">
                                    <img
                                        :src="post.cover_image"
                                        :alt="post.title"
                                        class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500"
                                    />
                                    <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                                    <div v-if="post.category" class="absolute top-4 left-4">
                                        <span class="inline-block rounded-full bg-black/60 px-3 py-1.5 text-xs font-semibold text-white backdrop-blur-sm">
                                            {{ post.category.name }}
                                        </span>
                                    </div>
                                </Link>

                                <!-- Content -->
                                <div class="p-6">
                                    <!-- Meta -->
                                    <div class="flex items-center gap-4 text-sm text-gray-500 dark:text-gray-400 mb-3 font-medium">
                                        <span class="flex items-center gap-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                            {{ post.published_at }}
                                        </span>
                                        <!-- <span>•</span>
                                        <span class="flex items-center gap-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            {{ post.read_time }}
                                        </span> -->
                                    </div>

                                    <!-- Title -->
                                    <Link :href="`/blog/${post.slug}`">
                                        <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-3 line-clamp-2 group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors duration-300">
                                            {{ post.title }}
                                        </h3>
                                    </Link>

                                    <!-- Excerpt -->
                                    <p class="text-gray-600 dark:text-gray-400 mb-4 line-clamp-3 leading-relaxed">
                                        {{ post.excerpt }}
                                    </p>

                                    <!-- Footer -->
                                    <div class="flex items-center justify-between border-t border-gray-200 pt-4 dark:border-gray-700">
                                        <span class="flex items-center gap-1.5 text-sm font-medium text-gray-600 dark:text-gray-400">
                                            <span class="material-icons text-lg leading-none">schedule</span>
                                            {{ post.read_time }}
                                        </span>
                                        <Link
                                            :href="`/blog/${post.slug}`"
                                            class="inline-flex items-center gap-1 text-sm font-semibold text-primary-600 transition-all duration-200 group-hover:gap-2 dark:text-primary-400"
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
                            <div class="inline-flex items-center justify-center w-16 h-16 bg-primary-100 dark:bg-primary-900/50 rounded-full mb-4 border border-primary-200 dark:border-primary-700">
                                <svg class="w-8 h-8 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">No posts found</h3>
                            <p class="text-gray-600 dark:text-gray-400">Try adjusting your search or filters</p>
                        </div>

                        <!-- Pagination -->
                        <div v-if="posts.data.length > 0 && (posts.prev_page_url || posts.next_page_url)" class="mt-12 flex items-center justify-center gap-3">
                            <Link
                                v-if="posts.prev_page_url"
                                :href="posts.prev_page_url"
                                class="px-5 py-2.5 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded-lg border-2 border-gray-300 dark:border-gray-600 hover:border-primary-500 dark:hover:border-primary-500 hover:bg-primary-50 dark:hover:bg-gray-700 transition-all duration-200 font-medium shadow-md"
                            >
                                Previous
                            </Link>
                            <span class="px-5 py-2.5 bg-primary-100 dark:bg-primary-900/50 text-primary-800 dark:text-primary-300 rounded-lg font-semibold border border-primary-200 dark:border-primary-700">
                                Page {{ posts.current_page }} of {{ posts.last_page }}
                            </span>
                            <Link
                                v-if="posts.next_page_url"
                                :href="posts.next_page_url"
                                class="px-5 py-2.5 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded-lg border-2 border-gray-300 dark:border-gray-600 hover:border-primary-500 dark:hover:border-primary-500 hover:bg-primary-50 dark:hover:bg-gray-700 transition-all duration-200 font-medium shadow-md"
                            >
                                Next
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <FeaturePageCta
            badge="Get started"
            badge-icon="rocket_launch"
            title="Ready to see it in action?"
            description="Talk with our team about how Helmful fits your dealership or start with pricing."
            primary-label="Contact us"
            primary-route="contact"
            secondary-label="View pricing"
            secondary-route="checkout.plans"
        />
    </AppLayout>
</template>
