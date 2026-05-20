<script setup>
import KioskLayout from '@/Layouts/KioskLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ categories: Object, filters: Object });
const search = ref(props.filters?.search || '');

const searchCategories = () => {
    router.get(route('kiosk.help-categories.index'), { search: search.value }, { preserveState: true, replace: true });
};
</script>

<template>
    <Head title="Help Categories" />
    <KioskLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Help Categories</h1>
        </template>
        <div class="space-y-6">
            <div class="flex justify-between gap-4">
                <input v-model="search" type="search" placeholder="Search..." class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white" @keyup.enter="searchCategories" />
                <Link :href="route('kiosk.help-categories.create')" class="gradient-btn rounded-lg px-4 py-2 text-sm">New Category</Link>
            </div>
            <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800/50">
                        <tr>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900 dark:text-white">Name</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900 dark:text-white">Parent</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900 dark:text-white">Active</th>
                            <th class="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr v-for="cat in categories.data" :key="cat.id">
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ cat.name }}</td>
                            <td class="px-4 py-3 text-sm text-gray-500">{{ cat.parent?.name || '—' }}</td>
                            <td class="px-4 py-3 text-sm">{{ cat.active ? 'Yes' : 'No' }}</td>
                            <td class="px-4 py-3 text-right">
                                <Link :href="route('kiosk.help-categories.edit', cat.id)" class="text-primary-600 text-sm">Edit</Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </KioskLayout>
</template>
