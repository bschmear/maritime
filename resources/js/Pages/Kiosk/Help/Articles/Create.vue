<script setup>
import KioskLayout from '@/Layouts/KioskLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import Checkbox from '@/Components/Checkbox.vue';
import TipTapEditor from '@/Components/TipTapEditor.vue';
import HelpArticleCategoryOrder from '@/Components/Kiosk/HelpArticleCategoryOrder.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    categories: Array,
    articleTypes: {
        type: Array,
        default: () => [
            { value: 'guide', label: 'Guide' },
            { value: 'documentation', label: 'Documentation' },
        ],
    },
});

const form = useForm({
    title: '',
    body: '',
    category_id: '',
    excerpt: '',
    video_url: '',
    article_type: 'guide',
    sort_order: 0,
    featured: false,
    active: true,
    published_at: '',
});

const submit = () => {
    form.post(route('kiosk.help-articles.store'));
};

function youtubeEmbedUrl(url) {
    const match = url.match(/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([\w-]+)/i);

    return match ? `https://www.youtube.com/embed/${match[1]}` : null;
}

function vimeoEmbedUrl(url) {
    const match = url.match(/vimeo\.com\/(?:video\/)?(\d+)/i);

    return match ? `https://player.vimeo.com/video/${match[1]}` : null;
}

const videoEmbedUrl = computed(() => {
    const url = (form.video_url || '').trim();
    if (!url) {
        return null;
    }

    return youtubeEmbedUrl(url) || vimeoEmbedUrl(url);
});
</script>

<template>
    <Head title="Create Article" />

    <KioskLayout>
        <template #header>
            <div class="flex items-center gap-x-3">
                <Link
                    :href="route('kiosk.help-articles.index')"
                    class="text-gray-400 transition-colors hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                    </svg>
                </Link>
                <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Create Article</h1>
            </div>
        </template>

        <form class="mx-auto max-w-7xl" @submit.prevent="submit">
            <p class="mb-6 text-sm text-gray-600 dark:text-gray-400">
                Add content and set article order on the left; use the sidebar for video, category, and publishing.
            </p>

            <div class="grid grid-cols-1 gap-8 lg:grid-cols-12 lg:items-start">
                <div class="space-y-6 lg:col-span-8">
                    <div
                        class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900"
                    >
                        <div class="border-b border-gray-200 px-4 py-4 dark:border-gray-700 sm:px-6">
                            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Content</h2>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Title, summary, and body</p>
                        </div>
                        <div class="space-y-6 px-4 py-6 sm:px-6">
                            <div>
                                <InputLabel for="title" value="Title" class="text-gray-900 dark:text-white" />
                                <TextInput
                                    id="title"
                                    v-model="form.title"
                                    type="text"
                                    class="mt-2 block w-full rounded-lg border-gray-300 bg-white text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:border-primary-400 dark:focus:ring-primary-400"
                                    placeholder="Article title..."
                                    required
                                    autofocus
                                />
                                <InputError class="mt-2" :message="form.errors.title" />
                            </div>

                            <div>
                                <InputLabel for="excerpt" value="Excerpt" class="text-gray-900 dark:text-white" />
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Brief summary shown in article lists and search results
                                </p>
                                <textarea
                                    id="excerpt"
                                    v-model="form.excerpt"
                                    rows="3"
                                    class="mt-2 block w-full rounded-lg border-gray-300 bg-white text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:border-primary-400 dark:focus:ring-primary-400"
                                    placeholder="Short description..."
                                />
                                <InputError class="mt-2" :message="form.errors.excerpt" />
                            </div>

                            <div>
                                <TipTapEditor
                                    id="body"
                                    label="Content"
                                    v-model="form.body"
                                    :error="form.errors.body"
                                    :show-anchor="true"
                                />
                            </div>
                        </div>
                    </div>

                    <div
                        class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900"
                    >
                        <div class="border-b border-gray-200 px-4 py-4 dark:border-gray-700 sm:px-6">
                            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Article order</h2>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                Drag to reorder all articles in the selected category
                            </p>
                        </div>
                        <div class="px-4 py-6 sm:px-6">
                            <HelpArticleCategoryOrder
                                :category-id="form.category_id"
                                :article-title="form.title"
                                v-model:sort-order="form.sort_order"
                            />
                            <InputError class="mt-2" :message="form.errors.sort_order" />
                        </div>
                    </div>
                </div>

                <aside class="space-y-6 lg:col-span-4">
                    <div
                        class="lg:sticky lg:top-6 lg:max-h-[calc(100vh-5rem)] lg:space-y-6 lg:overflow-y-auto lg:pb-4"
                    >
                        <div
                            class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900"
                        >
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Publishing</h3>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Visibility and schedule</p>

                            <div class="mt-4 space-y-3">
                                <label
                                    class="flex cursor-pointer items-start gap-3 rounded-lg p-2 transition-colors hover:bg-gray-50 dark:hover:bg-gray-800/50"
                                >
                                    <Checkbox v-model:checked="form.active" name="active" class="mt-0.5" />
                                    <div>
                                        <span class="text-sm font-medium text-gray-900 dark:text-white">Published</span>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Visible on the help portal</p>
                                    </div>
                                </label>

                                <label
                                    class="flex cursor-pointer items-start gap-3 rounded-lg p-2 transition-colors hover:bg-gray-50 dark:hover:bg-gray-800/50"
                                >
                                    <Checkbox v-model:checked="form.featured" name="featured" class="mt-0.5" />
                                    <div>
                                        <span class="text-sm font-medium text-gray-900 dark:text-white">Featured</span>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Highlight on the help portal home</p>
                                    </div>
                                </label>
                            </div>

                            <div v-if="form.active" class="mt-4 border-t border-gray-100 pt-4 dark:border-gray-700">
                                <InputLabel for="published_at" value="Publish date & time" class="text-gray-900 dark:text-white" />
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Leave empty to publish at the current date and time
                                </p>
                                <input
                                    id="published_at"
                                    v-model="form.published_at"
                                    type="datetime-local"
                                    class="mt-2 block w-full rounded-lg border-gray-300 bg-white text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:border-primary-400 dark:focus:ring-primary-400"
                                />
                                <InputError class="mt-2" :message="form.errors.published_at" />
                            </div>
                        </div>

                        <div
                            class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900"
                        >
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Video</h3>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">YouTube or Vimeo URL (optional)</p>

                            <div class="mt-4">
                                <InputLabel for="video_url" value="Video URL" class="sr-only" />
                                <TextInput
                                    id="video_url"
                                    v-model="form.video_url"
                                    type="url"
                                    class="block w-full rounded-lg border-gray-300 bg-white text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:border-primary-400 dark:focus:ring-primary-400"
                                    placeholder="https://www.youtube.com/watch?v=..."
                                />
                                <InputError class="mt-2" :message="form.errors.video_url" />
                            </div>

                            <div
                                v-if="videoEmbedUrl"
                                class="mt-4 overflow-hidden rounded-lg border border-gray-200 bg-gray-50 dark:border-gray-600 dark:bg-gray-800"
                            >
                                <div class="aspect-video w-full">
                                    <iframe
                                        :src="videoEmbedUrl"
                                        class="h-full w-full"
                                        title="Video preview"
                                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                        allowfullscreen
                                    />
                                </div>
                            </div>
                        </div>

                        <div
                            class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900"
                        >
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Organization</h3>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Category and article type</p>

                            <div class="mt-4 space-y-4">
                                <div>
                                    <label for="category_id" class="text-sm font-medium text-gray-900 dark:text-white">Category</label>
                                    <select
                                        id="category_id"
                                        v-model="form.category_id"
                                        class="mt-2 block w-full rounded-lg border-gray-300 bg-white text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:border-primary-400 dark:focus:ring-primary-400"
                                    >
                                        <option value="">Select a category</option>
                                        <option v-for="category in categories" :key="category.id" :value="category.id">
                                            {{ category.name }}
                                        </option>
                                    </select>
                                    <InputError class="mt-2" :message="form.errors.category_id" />
                                </div>

                                <div>
                                    <label for="article_type" class="text-sm font-medium text-gray-900 dark:text-white">Article type</label>
                                    <select
                                        id="article_type"
                                        v-model="form.article_type"
                                        class="mt-2 block w-full rounded-lg border-gray-300 bg-white text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:border-primary-400 dark:focus:ring-primary-400"
                                    >
                                        <option v-for="type in articleTypes" :key="type.value" :value="type.value">
                                            {{ type.label }}
                                        </option>
                                    </select>
                                    <InputError class="mt-2" :message="form.errors.article_type" />
                                </div>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>

            <div
                class="mt-8 flex flex-col-reverse items-stretch justify-end gap-3 border-t border-gray-200 pt-6 dark:border-gray-700 sm:flex-row sm:items-center"
            >
                <Link
                    :href="route('kiosk.help-articles.index')"
                    class="text-center text-sm font-semibold text-gray-700 transition-colors hover:text-gray-900 dark:text-gray-300 dark:hover:text-white"
                >
                    Cancel
                </Link>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="gradient-btn gap-x-2 rounded-lg px-5 py-2.5 text-sm disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:scale-100"
                >
                    <svg
                        v-if="form.processing"
                        class="-ml-1 h-4 w-4 animate-spin text-white"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                    >
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path
                            class="opacity-75"
                            fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                        />
                    </svg>
                    <svg v-else class="-ml-0.5 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    {{ form.processing ? 'Creating...' : 'Create article' }}
                </button>
            </div>
        </form>
    </KioskLayout>
</template>
