<script setup>
import KioskLayout from '@/Layouts/KioskLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({
    faqs: Object,
});

const deleteFaq = (faq) => {
    if (confirm(`Delete "${faq.question}"?`)) {
        router.delete(route('kiosk.faqs.destroy', faq.id));
    }
};
</script>

<template>
    <Head title="FAQs" />

    <KioskLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900 dark:text-white">FAQs</h1>
        </template>

        <div class="space-y-6">
            <!-- Header with Create Button -->
            <div class="flex justify-between items-center">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Manage your frequently asked questions
                    </p>
                </div>
                <Link
                    :href="route('kiosk.faqs.create')"
                    class="inline-flex items-center gap-x-2 rounded-lg bg-gradient-to-r from-primary-500 to-secondary-500 px-4 py-2.5 text-sm font-semibold text-white shadow-md hover:shadow-lg transition-all hover:scale-105"
                >
                    <svg class="-ml-0.5 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    New FAQ
                </Link>
            </div>

            <!-- Table -->
            <div class="overflow-hidden bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 shadow-sm rounded-xl">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800/50">
                            <tr>
                                <th class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 dark:text-white sm:pl-6">
                                    Question
                                </th>
                                <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">
                                    Answer
                                </th>
                                <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">
                                    Featured
                                </th>
                                <th class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                                    <span class="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-900">
                            <tr
                                v-for="faq in faqs.data"
                                :key="faq.id"
                                class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors"
                            >
                                <td class="py-4 pl-4 pr-3 text-sm font-medium text-gray-900 dark:text-white sm:pl-6">
                                    {{ faq.question?.substring(0, 80) }}{{ faq.question?.length > 80 ? '...' : '' }}
                                </td>
                                <td class="px-3 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    <span v-if="faq.answer">
                                        {{ faq.answer?.substring(0, 100) }}{{ faq.answer?.length > 100 ? '...' : '' }}
                                    </span>
                                    <span v-else class="text-gray-400 dark:text-gray-500 italic">
                                        No answer
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    <span v-if="faq.featured" class="inline-flex items-center rounded-full bg-green-100 dark:bg-green-500/20 px-2.5 py-0.5 text-xs font-medium text-green-800 dark:text-green-400">
                                        <svg class="mr-1 h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                                        </svg>
                                        Featured
                                    </span>
                                    <span v-else class="inline-flex items-center rounded-full bg-gray-100 dark:bg-gray-500/20 px-2.5 py-0.5 text-xs font-medium text-gray-600 dark:text-gray-400">
                                        Not featured
                                    </span>
                                </td>
                                <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                                    <div class="flex items-center justify-end gap-x-3">
                                        <Link
                                            :href="route('kiosk.faqs.edit', faq.id)"
                                            class="inline-flex items-center gap-x-1 text-secondary-600 dark:text-secondary-400 hover:text-secondary-900 dark:hover:text-secondary-300 transition-colors"
                                        >
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                            Edit
                                        </Link>
                                        <button
                                            @click="deleteFaq(faq)"
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
                            <tr v-if="!faqs.data || faqs.data.length === 0">
                                <td colspan="4" class="px-6 py-12 text-center">
                                    <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No FAQs</h3>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Get started by creating a new FAQ.</p>
                                    <div class="mt-6">
                                        <Link
                                            :href="route('kiosk.faqs.create')"
                                            class="inline-flex items-center gap-x-2 rounded-lg bg-gradient-to-r from-primary-500 to-secondary-500 px-4 py-2 text-sm font-medium text-white shadow-md hover:shadow-lg transition-all hover:scale-105"
                                        >
                                            <svg class="-ml-0.5 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                            </svg>
                                            New FAQ
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="faqs.data && faqs.data.length > 0 && (faqs.prev_page_url || faqs.next_page_url)" class="border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 px-4 py-3 sm:px-6">
                    <div class="flex items-center justify-between">
                        <div class="flex-1 flex justify-between sm:hidden">
                            <Link
                                v-if="faqs.prev_page_url"
                                :href="faqs.prev_page_url"
                                class="relative inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                            >
                                Previous
                            </Link>
                            <Link
                                v-if="faqs.next_page_url"
                                :href="faqs.next_page_url"
                                class="relative ml-3 inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                            >
                                Next
                            </Link>
                        </div>
                        <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm text-gray-700 dark:text-gray-300">
                                    Showing
                                    <span class="font-medium">{{ faqs.from }}</span>
                                    to
                                    <span class="font-medium">{{ faqs.to }}</span>
                                    of
                                    <span class="font-medium">{{ faqs.total }}</span>
                                    results
                                </p>
                            </div>
                            <div>
                                <nav class="isolate inline-flex -space-x-px rounded-lg shadow-sm" aria-label="Pagination">
                                    <Link
                                        v-if="faqs.prev_page_url"
                                        :href="faqs.prev_page_url"
                                        class="relative inline-flex items-center rounded-l-lg px-2 py-2 text-gray-400 dark:text-gray-500 ring-1 ring-inset ring-gray-300 dark:ring-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                                    >
                                        <span class="sr-only">Previous</span>
                                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" />
                                        </svg>
                                    </Link>
                                    <Link
                                        v-if="faqs.next_page_url"
                                        :href="faqs.next_page_url"
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
