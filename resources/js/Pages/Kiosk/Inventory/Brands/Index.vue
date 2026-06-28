<script setup>
import KioskLayout from '@/Layouts/KioskLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({
    brands: Object,
    filters: {
        type: Object,
        default: () => ({ search: '' }),
    },
});

const deleteBrand = (brand) => {
    if (confirm(`Delete "${brand.display_name}"? This cannot be undone.`)) {
        router.delete(route('kiosk.inventory-brands.destroy', brand.id));
    }
};

const applySearch = (event) => {
    router.get(route('kiosk.inventory-brands.index'), { search: event.target.value }, {
        preserveState: true,
        replace: true,
    });
};
</script>

<template>
    <Head title="Inventory Brands" />

    <KioskLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Inventory Brands</h1>
        </template>

        <div class="space-y-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex-1">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Manage shared manufacturer brands in the inventory catalog.
                    </p>
                    <input
                        type="search"
                        :value="filters.search"
                        placeholder="Search by name or slug…"
                        class="mt-3 block w-full max-w-md rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                        @change="applySearch"
                    />
                </div>
                <Link
                    :href="route('kiosk.inventory-brands.create')"
                    class="gradient-btn gap-x-2 rounded-lg px-4 py-2.5 text-sm"
                >
                    New Brand
                </Link>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800/50">
                            <tr>
                                <th class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 dark:text-white sm:pl-6">
                                    Logo
                                </th>
                                <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">
                                    Brand
                                </th>
                                <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">
                                    Slug
                                </th>
                                <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">
                                    Models
                                </th>
                                <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">
                                    Status
                                </th>
                                <th class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                                    <span class="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                            <tr
                                v-for="brand in brands.data"
                                :key="brand.id"
                                class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-800/50"
                            >
                                <td class="whitespace-nowrap py-4 pl-4 pr-3 sm:pl-6">
                                    <img
                                        v-if="brand.logo_url"
                                        :src="brand.logo_url"
                                        :alt="brand.display_name"
                                        class="h-10 w-16 object-contain"
                                    />
                                    <span v-else class="text-xs text-gray-400">—</span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm font-medium">
                                    <Link
                                        :href="route('kiosk.inventory-brands.show', brand.id)"
                                        class="text-gray-900 hover:text-primary-600 dark:text-white dark:hover:text-primary-400"
                                    >
                                        {{ brand.display_name }}
                                    </Link>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 font-mono text-xs text-gray-500 dark:text-gray-400">
                                    {{ brand.slug }}
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    {{ brand.catalog_assets_count || 0 }}
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm">
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                                        :class="brand.active
                                            ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200'
                                            : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300'"
                                    >
                                        {{ brand.active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                                    <div class="flex items-center justify-end gap-x-3">
                                        <Link
                                            :href="route('kiosk.inventory-brands.show', brand.id)"
                                            class="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200"
                                        >
                                            View
                                        </Link>
                                        <Link
                                            :href="route('kiosk.inventory-brands.edit', brand.id)"
                                            class="text-primary-600 hover:text-primary-700 dark:text-primary-400"
                                        >
                                            Edit
                                        </Link>
                                        <button
                                            type="button"
                                            class="text-red-600 hover:text-red-800 dark:text-red-400"
                                            @click="deleteBrand(brand)"
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!brands.data?.length">
                                <td colspan="6" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No brands found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div
                    v-if="brands.data?.length && (brands.prev_page_url || brands.next_page_url || brands.last_page > 1)"
                    class="border-t border-gray-200 bg-gray-50 px-4 py-3 sm:px-6 dark:border-gray-700 dark:bg-gray-800/50"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex flex-1 justify-between sm:hidden">
                            <Link
                                v-if="brands.prev_page_url"
                                :href="brands.prev_page_url"
                                class="relative inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                            >
                                Previous
                            </Link>
                            <Link
                                v-if="brands.next_page_url"
                                :href="brands.next_page_url"
                                class="relative ml-3 inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                            >
                                Next
                            </Link>
                        </div>
                        <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm text-gray-700 dark:text-gray-300">
                                    Showing
                                    <span class="font-medium">{{ brands.from ?? 0 }}</span>
                                    to
                                    <span class="font-medium">{{ brands.to ?? 0 }}</span>
                                    of
                                    <span class="font-medium">{{ brands.total ?? 0 }}</span>
                                    results
                                </p>
                            </div>
                            <div>
                                <nav class="isolate inline-flex -space-x-px rounded-lg shadow-sm" aria-label="Pagination">
                                    <Link
                                        v-if="brands.prev_page_url"
                                        :href="brands.prev_page_url"
                                        class="relative inline-flex items-center rounded-l-lg px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 transition-colors hover:bg-gray-50 dark:text-gray-500 dark:ring-gray-600 dark:hover:bg-gray-700"
                                    >
                                        <span class="sr-only">Previous</span>
                                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" />
                                        </svg>
                                    </Link>
                                    <span
                                        class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 ring-1 ring-inset ring-gray-300 dark:text-gray-300 dark:ring-gray-600"
                                    >
                                        Page {{ brands.current_page }} of {{ brands.last_page }}
                                    </span>
                                    <Link
                                        v-if="brands.next_page_url"
                                        :href="brands.next_page_url"
                                        class="relative inline-flex items-center rounded-r-lg px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 transition-colors hover:bg-gray-50 dark:text-gray-500 dark:ring-gray-600 dark:hover:bg-gray-700"
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
