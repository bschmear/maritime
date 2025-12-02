<script setup>
import KioskLayout from '@/Layouts/KioskLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    posts: Object,
    filters: Object,
});

const search = ref(props.filters.search || '');

const searchPosts = () => {
    router.get(route('kiosk.posts.index'), { search: search.value }, {
        preserveState: true,
        replace: true,
    });
};

const deletePost = (post) => {
    if (confirm('Are you sure you want to delete this post?')) {
        router.delete(route('kiosk.posts.destroy', post.id));
    }
};

const truncate = (text, length = 50) => {
    if (!text) return '';
    return text.length > length ? text.substring(0, length) + '...' : text;
};
</script>

<template>
    <Head title="Posts" />

    <KioskLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Posts</h1>
        </template>

        <div class="space-y-6">
            <!-- Header Actions -->
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4">
                <div class="flex-1 max-w-lg">
                    <div class="relative">
                        <input
                            v-model="search"
                            @keyup.enter="searchPosts"
                            type="search"
                            placeholder="Search posts..."
                            class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-sm focus:border-secondary-500 dark:focus:border-secondary-400 focus:ring-secondary-500 dark:focus:ring-secondary-400 placeholder:text-gray-400 dark:placeholder:text-gray-500 pr-10"
                        />
                        <button @click="searchPosts" class="absolute inset-y-0 right-0 flex items-center pr-3">
                            <svg class="h-5 w-5 text-gray-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                            </svg>
                        </button>
                    </div>
                </div>
                <Link
                    :href="route('kiosk.posts.create')"
                    class="inline-flex items-center justify-center gap-x-2 rounded-lg bg-gradient-to-r from-primary-500 to-secondary-500 px-4 py-2.5 text-sm font-semibold text-white shadow-md hover:shadow-lg transition-all hover:scale-105 whitespace-nowrap"
                >
                    <svg class="-ml-0.5 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    New Post
                </Link>
            </div>

            <!-- Posts Table -->
            <div class="overflow-hidden bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 shadow-sm rounded-xl">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800/50">
                            <tr>
                                <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 dark:text-white sm:pl-6">
                                    Title
                                </th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">
                                    Category
                                </th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">
                                    Author
                                </th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">
                                    Status
                                </th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">
                                    Date
                                </th>
                                <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                                    <span class="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-900">
                            <tr
                                v-for="post in posts.data"
                                :key="post.id"
                                class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors"
                            >
                                <td class="py-4 pl-4 pr-3 text-sm sm:pl-6">
                                    <div class="font-medium text-gray-900 dark:text-white">
                                        {{ truncate(post.title, 60) }}
                                    </div>
                                    <div v-if="post.short_description" class="text-gray-500 dark:text-gray-400 mt-1">
                                        {{ truncate(post.short_description, 80) }}
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm">
                                    <span
                                        v-if="post.category"
                                        class="inline-flex items-center rounded-md bg-gray-100 dark:bg-gray-800 px-2.5 py-1 text-xs font-medium text-gray-700 dark:text-gray-300 ring-1 ring-inset ring-gray-500/10 dark:ring-gray-500/20"
                                    >
                                        {{ post.category.name }}
                                    </span>
                                    <span v-else class="text-gray-400 dark:text-gray-500 italic text-xs">
                                        No category
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    {{ post.user.name }}
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm">
                                    <span
                                        v-if="post.published"
                                        class="inline-flex items-center rounded-full bg-green-100 dark:bg-green-500/20 px-2.5 py-0.5 text-xs font-medium text-green-800 dark:text-green-400"
                                    >
                                        Published
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex items-center rounded-full bg-yellow-100 dark:bg-yellow-500/20 px-2.5 py-0.5 text-xs font-medium text-yellow-800 dark:text-yellow-400"
                                    >
                                        Draft
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    {{ new Date(post.created_at).toLocaleDateString() }}
                                </td>
                                <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                                    <div class="flex items-center justify-end gap-x-3">
                                        <Link
                                            :href="route('kiosk.posts.edit', post.id)"
                                            class="inline-flex items-center gap-x-1 text-secondary-600 dark:text-secondary-400 hover:text-secondary-900 dark:hover:text-secondary-300 transition-colors"
                                        >
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                            Edit
                                        </Link>
                                        <button
                                            @click="deletePost(post)"
                                            class="inline-flex items-center gap-x-1 text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-300 transition-colors"
                                        >
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <!-- Empty State -->
                            <tr v-if="!posts.data || posts.data.length === 0">
                                <td colspan="6" class="px-6 py-12 text-center">
                                    <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                    </svg>
                                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No posts</h3>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Get started by creating a new post.</p>
                                    <div class="mt-6">
                                        <Link
                                            :href="route('kiosk.posts.create')"
                                            class="inline-flex items-center gap-x-2 rounded-lg bg-gradient-to-r from-primary-500 to-secondary-500 px-4 py-2 text-sm font-medium text-white shadow-md hover:shadow-lg transition-all hover:scale-105"
                                        >
                                            <svg class="-ml-0.5 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                            </svg>
                                            New Post
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="posts.data && posts.data.length > 0 && (posts.prev_page_url || posts.next_page_url)" class="border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 px-4 py-3 sm:px-6">
                    <div class="flex items-center justify-between">
                        <div class="flex-1 flex justify-between sm:hidden">
                            <Link
                                v-if="posts.prev_page_url"
                                :href="posts.prev_page_url"
                                class="relative inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                            >
                                Previous
                            </Link>
                            <Link
                                v-if="posts.next_page_url"
                                :href="posts.next_page_url"
                                class="relative ml-3 inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                            >
                                Next
                            </Link>
                        </div>
                        <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm text-gray-700 dark:text-gray-300">
                                    Showing
                                    <span class="font-medium">{{ posts.from }}</span>
                                    to
                                    <span class="font-medium">{{ posts.to }}</span>
                                    of
                                    <span class="font-medium">{{ posts.total }}</span>
                                    results
                                </p>
                            </div>
                            <div>
                                <nav class="isolate inline-flex -space-x-px rounded-lg shadow-sm" aria-label="Pagination">
                                    <Link
                                        v-if="posts.prev_page_url"
                                        :href="posts.prev_page_url"
                                        class="relative inline-flex items-center rounded-l-lg px-2 py-2 text-gray-400 dark:text-gray-500 ring-1 ring-inset ring-gray-300 dark:ring-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                                    >
                                        <span class="sr-only">Previous</span>
                                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" />
                                        </svg>
                                    </Link>
                                    <Link
                                        v-if="posts.next_page_url"
                                        :href="posts.next_page_url"
                                        class="relative inline-flex items-center rounded-r-lg px-2 py-2 text-gray-400 dark:text-gray-500 ring-1 ring-inset ring-gray-300 dark:ring-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                                    >
                                        <span class="sr-only">Next</span>
                                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
                                        </svg>
                                    </Link>
                                </nav>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </KioskLayout>
</template>
