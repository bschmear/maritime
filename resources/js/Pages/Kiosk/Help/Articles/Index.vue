<script setup>
import KioskLayout from '@/Layouts/KioskLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ articles: Object, categories: Array, filters: Object });
const search = ref(props.filters?.search || '');

const searchArticles = () => {
    router.get(route('kiosk.help-articles.index'), { search: search.value }, { preserveState: true, replace: true });
};
</script>

<template>
    <Head title="Help Articles" />
    <KioskLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Help Articles</h1>
        </template>
        <div class="space-y-6">
            <div class="flex justify-between gap-4">
                <input v-model="search" type="search" placeholder="Search..." class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white" @keyup.enter="searchArticles" />
                <Link :href="route('kiosk.help-articles.create')" class="gradient-btn rounded-lg px-4 py-2 text-sm">New Article</Link>
            </div>
            <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800/50">
                        <tr>
                            <th class="px-4 py-3 text-left text-sm font-semibold">Title</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold">Category</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold">Active</th>
                            <th class="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr v-for="article in articles.data" :key="article.id">
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">
                                <Link :href="route('kiosk.help-articles.edit', article.id)" class="text-primary-600 text-sm">{{ article.title }}</Link>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-500">{{ article.category?.name || '—' }}</td>
                            <td class="px-4 py-3 text-sm">{{ article.active ? 'Yes' : 'No' }}</td>
                            <td class="px-4 py-3 text-right">
                                <Link :href="route('kiosk.help-articles.edit', article.id)" class="text-primary-600 text-sm">Edit</Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </KioskLayout>
</template>
