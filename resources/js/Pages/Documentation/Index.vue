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
        <div>
            <h1 class="text-4xl font-bold tracking-tight text-gray-900 sm:text-5xl">Documentation</h1>
            <p class="mt-4 text-lg text-gray-600">
                Guides and reference material for using the platform.
            </p>
        </div>

        <section v-if="featured?.length" class="mt-16">
            <h2 class="text-sm font-semibold text-gray-900">Featured</h2>
            <div
                class="not-prose mt-4 grid grid-cols-1 gap-6 border-t border-gray-900/5 pt-10 sm:grid-cols-2"
            >
                <Link
                    v-for="article in featured"
                    :key="article.id"
                    :href="route('docs.article', article.slug)"
                    class="group relative flex rounded-2xl bg-white/70 p-6 ring-1 ring-primary-900/5 backdrop-blur-sm transition hover:bg-white hover:shadow-md hover:shadow-primary-900/5"
                >
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 group-hover:text-primary-600">
                            {{ article.title }}
                        </h3>
                        <p v-if="article.excerpt" class="mt-2 text-sm text-gray-600 line-clamp-3">
                            {{ article.excerpt }}
                        </p>
                        <p class="mt-4 text-sm font-medium text-primary-600">
                            Read article
                            <span aria-hidden="true" class="inline-block transition group-hover:translate-x-0.5">→</span>
                        </p>
                    </div>
                </Link>
            </div>
        </section>

        <section class="mt-16">
            <h2 class="text-sm font-semibold text-gray-900">Browse by category</h2>
            <div
                class="not-prose mt-4 grid grid-cols-1 gap-6 border-t border-gray-900/5 pt-10 sm:grid-cols-2 lg:grid-cols-3"
            >
                <Link
                    v-for="category in categories"
                    :key="category.id"
                    :href="route('docs.category', category.slug)"
                    class="group relative flex flex-col rounded-2xl bg-white/70 p-6 ring-1 ring-primary-900/5 backdrop-blur-sm transition hover:bg-white hover:shadow-md hover:shadow-primary-900/5"
                >
                    <h3 class="text-sm font-semibold text-gray-900 group-hover:text-primary-600">
                        {{ category.name }}
                    </h3>
                    <p v-if="category.description" class="mt-2 flex-1 text-sm text-gray-600 line-clamp-3">
                        {{ category.description }}
                    </p>
                    <p class="mt-4 text-xs font-medium text-gray-500">
                        {{ category.articles_count }} {{ category.articles_count === 1 ? 'article' : 'articles' }}
                    </p>
                </Link>
            </div>
        </section>
    </DocumentationLayout>
</template>
