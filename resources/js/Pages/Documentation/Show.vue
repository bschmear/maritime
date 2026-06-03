<script setup>
import DocumentationLayout from '@/Layouts/DocumentationLayout.vue';
import { useProseResponsiveTables } from '@/composables/useProseResponsiveTables';
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    article: Object,
    prev: Object,
    next: Object,
});

const articleEl = ref(null);

useProseResponsiveTables(articleEl);
</script>

<template>
    <Head :title="article.title" />

    <DocumentationLayout>
        <p class="text-sm text-gray-500">
            <Link :href="route('docs.home')" class="transition hover:text-gray-900">Documentation</Link>
            <template v-if="article.category">
                <span class="mx-2 text-gray-300">/</span>
                <Link
                    :href="route('docs.category', article.category.slug)"
                    class="transition hover:text-gray-900"
                >
                    {{ article.category.name }}
                </Link>
            </template>
        </p>

        <header class="mt-4">
            <h1 class="text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">{{ article.title }}</h1>
            <p v-if="article.excerpt" class="mt-4 text-lg text-gray-600">{{ article.excerpt }}</p>
            <p v-if="article.updated_at" class="mt-3 text-xs text-gray-500">
                Updated {{ new Date(article.updated_at).toLocaleDateString() }}
            </p>
        </header>

        <article
            ref="articleEl"
            class="documentation-prose prose prose-gray mt-10 max-w-none min-w-0 prose-headings:scroll-mt-24 prose-headings:font-semibold prose-headings:tracking-tight prose-a:font-medium prose-a:text-primary-600 prose-a:no-underline hover:prose-a:underline"
            v-html="article.body"
        />

        <nav
            v-if="prev || next"
            class="not-prose mt-16 grid grid-cols-1 gap-8 border-t border-gray-900/5 pt-10 sm:grid-cols-2"
        >
            <div v-if="prev" class="flex flex-col items-start gap-2 sm:col-start-1">
                <span class="text-xs font-medium text-gray-500">Previous</span>
                <Link
                    :href="route('docs.article', prev.slug)"
                    class="text-base font-semibold text-gray-900 transition hover:text-primary-600"
                >
                    ← {{ prev.title }}
                </Link>
            </div>
            <div
                v-if="next"
                class="flex flex-col gap-2"
                :class="prev ? 'items-end text-right sm:col-start-2' : 'items-start sm:col-start-1'"
            >
                <span class="text-xs font-medium text-gray-500">Next</span>
                <Link
                    :href="route('docs.article', next.slug)"
                    class="text-base font-semibold text-gray-900 transition hover:text-primary-600"
                >
                    {{ next.title }} →
                </Link>
            </div>
        </nav>
    </DocumentationLayout>
</template>
