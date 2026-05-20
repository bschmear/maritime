<script setup>
import DocumentationLayout from '@/Layouts/DocumentationLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    featured: Array,
    categories: Array,
    search: String,
});
</script>

<template>
    <Head title="Documentation" />

    <DocumentationLayout>
        <div class="mb-10">
            <h1 class="text-3xl font-bold tracking-tight text-gray-900">Documentation</h1>
            <p class="mt-3 text-lg text-gray-600">
                Guides and reference material for using the platform.
            </p>
        </div>

        <section v-if="featured?.length" class="mb-12">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Featured</h2>
            <ul class="mt-4 grid gap-4 sm:grid-cols-2">
                <li v-for="article in featured" :key="article.id">
                    <Link
                        :href="route('docs.article', article.slug)"
                        class="block rounded-xl border border-gray-200 bg-white p-5 shadow-sm hover:border-primary-300 hover:shadow-md transition"
                    >
                        <h3 class="font-semibold text-gray-900">{{ article.title }}</h3>
                        <p v-if="article.excerpt" class="mt-2 text-sm text-gray-600 line-clamp-2">{{ article.excerpt }}</p>
                    </Link>
                </li>
            </ul>
        </section>

        <section>
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Browse by category</h2>
            <ul class="mt-4 grid gap-4 sm:grid-cols-2">
                <li v-for="category in categories" :key="category.id">
                    <Link
                        :href="route('docs.category', category.slug)"
                        class="block rounded-xl border border-gray-200 bg-white p-5 hover:border-primary-300 transition"
                    >
                        <h3 class="font-semibold text-gray-900">{{ category.name }}</h3>
                        <p v-if="category.description" class="mt-1 text-sm text-gray-600">{{ category.description }}</p>
                        <p class="mt-2 text-xs text-gray-400">{{ category.articles_count }} articles</p>
                    </Link>
                </li>
            </ul>
        </section>
    </DocumentationLayout>
</template>
