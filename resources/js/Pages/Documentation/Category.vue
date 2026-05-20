<script setup>
import DocumentationLayout from '@/Layouts/DocumentationLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    category: Object,
});
</script>

<template>
    <Head :title="category.name" />

    <DocumentationLayout>
        <nav class="mb-6 text-sm text-gray-500">
            <Link :href="route('docs.home')" class="hover:text-primary-600">Documentation</Link>
            <span class="mx-2">/</span>
            <span class="text-gray-900">{{ category.name }}</span>
        </nav>

        <h1 class="text-3xl font-bold text-gray-900">{{ category.name }}</h1>
        <p v-if="category.description" class="mt-3 text-gray-600">{{ category.description }}</p>

        <ul v-if="category.children?.length" class="mt-8 grid gap-3 sm:grid-cols-2">
            <li v-for="child in category.children" :key="child.id">
                <Link
                    :href="route('docs.category', child.slug)"
                    class="block rounded-lg border border-gray-200 bg-white px-4 py-3 hover:border-primary-300"
                >
                    {{ child.name }}
                </Link>
            </li>
        </ul>

        <ul class="mt-8 divide-y divide-gray-200 rounded-xl border border-gray-200 bg-white">
            <li v-for="article in category.articles" :key="article.id">
                <Link
                    :href="route('docs.article', article.slug)"
                    class="block px-5 py-4 hover:bg-gray-50"
                >
                    <h2 class="font-medium text-gray-900">{{ article.title }}</h2>
                    <p v-if="article.excerpt" class="mt-1 text-sm text-gray-500">{{ article.excerpt }}</p>
                </Link>
            </li>
            <li v-if="!category.articles?.length" class="px-5 py-8 text-center text-sm text-gray-500">
                No articles in this category yet.
            </li>
        </ul>
    </DocumentationLayout>
</template>
