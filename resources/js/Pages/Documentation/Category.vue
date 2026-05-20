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
        <p class="text-sm text-gray-500">
            <Link :href="route('docs.home')" class="transition hover:text-gray-900">Documentation</Link>
            <span class="mx-2 text-gray-300">/</span>
            <span class="text-gray-900">{{ category.name }}</span>
        </p>

        <h1 class="mt-4 text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">{{ category.name }}</h1>
        <p v-if="category.description" class="mt-4 text-lg text-gray-600">{{ category.description }}</p>

        <section v-if="category.children?.length" class="mt-12">
            <h2 class="text-sm font-semibold text-gray-900">Subcategories</h2>
            <ul class="not-prose mt-4 grid gap-4 border-t border-gray-900/5 pt-8 sm:grid-cols-2">
                <li v-for="child in category.children" :key="child.id">
                    <Link
                        :href="route('docs.category', child.slug)"
                        class="group block rounded-2xl bg-white/70 px-5 py-4 ring-1 ring-primary-900/5 backdrop-blur-sm transition hover:bg-white hover:shadow-sm"
                    >
                        <span class="text-sm font-semibold text-gray-900 group-hover:text-primary-600">
                            {{ child.name }}
                        </span>
                    </Link>
                </li>
            </ul>
        </section>

        <section class="mt-12">
            <h2 class="text-sm font-semibold text-gray-900">Articles</h2>
            <ul class="mt-4 divide-y divide-gray-900/5 border-t border-gray-900/5">
                <li v-for="article in category.articles" :key="article.id">
                    <Link
                        :href="route('docs.article', article.slug)"
                        class="group flex flex-col py-4 transition hover:text-primary-600"
                    >
                        <span class="text-sm font-semibold text-gray-900 group-hover:text-primary-600">
                            {{ article.title }}
                        </span>
                        <span v-if="article.excerpt" class="mt-1 text-sm text-gray-600 line-clamp-2">
                            {{ article.excerpt }}
                        </span>
                    </Link>
                </li>
                <li v-if="!category.articles?.length" class="py-10 text-center text-sm text-gray-500">
                    No articles in this category yet.
                </li>
            </ul>
        </section>
    </DocumentationLayout>
</template>
