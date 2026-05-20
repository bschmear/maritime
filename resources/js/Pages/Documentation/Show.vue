<script setup>
import DocumentationLayout from '@/Layouts/DocumentationLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    article: Object,
    prev: Object,
    next: Object,
});
</script>

<template>
    <Head :title="article.title" />

    <DocumentationLayout>
        <nav class="mb-6 text-sm text-gray-500">
            <Link :href="route('docs.home')" class="hover:text-primary-600">Documentation</Link>
            <template v-if="article.category">
                <span class="mx-2">/</span>
                <Link
                    :href="route('docs.category', article.category.slug)"
                    class="hover:text-primary-600"
                >
                    {{ article.category.name }}
                </Link>
            </template>
        </nav>

        <article>
            <h1 class="text-3xl font-bold text-gray-900">{{ article.title }}</h1>
            <p v-if="article.excerpt" class="mt-3 text-lg text-gray-600">{{ article.excerpt }}</p>
            <p v-if="article.updated_at" class="mt-2 text-xs text-gray-400">
                Updated {{ new Date(article.updated_at).toLocaleDateString() }}
            </p>
            <div
                class="prose prose-gray mt-8 max-w-none prose-headings:font-semibold prose-a:text-primary-600"
                v-html="article.body"
            />
        </article>

        <nav v-if="prev || next" class="mt-12 flex justify-between gap-4 border-t border-gray-200 pt-8 text-sm">
            <div>
                <Link v-if="prev" :href="route('docs.article', prev.slug)" class="text-primary-600 hover:underline">
                    ← {{ prev.title }}
                </Link>
            </div>
            <div class="text-right">
                <Link v-if="next" :href="route('docs.article', next.slug)" class="text-primary-600 hover:underline">
                    {{ next.title }} →
                </Link>
            </div>
        </nav>
    </DocumentationLayout>
</template>
