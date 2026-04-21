<script setup>
import KioskLayout from '@/Layouts/KioskLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import Checkbox from '@/Components/Checkbox.vue';
import TipTapEditor from '@/Components/TipTapEditor.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Laravel sends published_at as ISO 8601 (e.g. …T12:00:00.000000Z). Browsers only accept
 * datetime-local values as local YYYY-MM-DDTHH:mm with no timezone — otherwise the field stays blank.
 */
function toDateTimeLocalValue(value) {
    if (value == null || value === '') {
        return '';
    }
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) {
        return '';
    }
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

const props = defineProps({
    post: Object,
    categories: Array,
    tags: Array,
});

const form = useForm({
    title: props.post.title || '',
    body: props.post.body || '',
    category_id: props.post.category_id || '',
    short_description: props.post.short_description || '',
    cover_image: props.post.cover_image || '',
    featured: props.post.featured || false,
    published: props.post.published || false,
    published_at: toDateTimeLocalValue(props.post.published_at),
    tags: props.post.tags?.map((tag) => tag.id) || [],
});

const submit = () => {
    form.put(route('kiosk.posts.update', props.post.id));
};

const showCoverPreview = computed(() => {
    const u = (form.cover_image || '').trim();
    return u.startsWith('http://') || u.startsWith('https://') || u.startsWith('/');
});
</script>

<template>
    <Head title="Edit Post" />

    <KioskLayout>
        <template #header>
            <div class="flex items-center gap-x-3">
                <Link
                    :href="route('kiosk.posts.index')"
                    class="text-gray-400 transition-colors hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                    </svg>
                </Link>
                <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Edit Post</h1>
            </div>
        </template>

        <form @submit.prevent="submit" class="mx-auto max-w-7xl">
            <p class="mb-6 text-sm text-gray-600 dark:text-gray-400">
                Edit the main content on the left; use the sidebar for publishing, cover image, category, and tags.
            </p>

            <div class="grid grid-cols-1 gap-8 lg:grid-cols-12 lg:items-start">
                <!-- Main column: content -->
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
                                    placeholder="Enter an engaging post title..."
                                    required
                                    autofocus
                                />
                                <InputError class="mt-2" :message="form.errors.title" />
                            </div>

                            <div>
                                <InputLabel for="short_description" value="Short description" class="text-gray-900 dark:text-white" />
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Brief summary for previews and social media
                                </p>
                                <TextInput
                                    id="short_description"
                                    v-model="form.short_description"
                                    type="text"
                                    class="mt-2 block w-full rounded-lg border-gray-300 bg-white text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:border-primary-400 dark:focus:ring-primary-400"
                                    placeholder="Write a compelling summary..."
                                />
                                <InputError class="mt-2" :message="form.errors.short_description" />
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
                </div>

                <!-- Sidebar -->
                <aside class="space-y-6 lg:col-span-4">
                    <div
                        class="lg:sticky lg:top-6 lg:max-h-[calc(100vh-5rem)] lg:space-y-6 lg:overflow-y-auto lg:pb-4"
                    >
                        <!-- Publishing -->
                        <div
                            class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900"
                        >
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Publishing</h3>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Visibility and schedule</p>

                            <div class="mt-4 space-y-3">
                                <label
                                    class="flex cursor-pointer items-start gap-3 rounded-lg p-2 transition-colors hover:bg-gray-50 dark:hover:bg-gray-800/50"
                                >
                                    <Checkbox v-model:checked="form.published" name="published" class="mt-0.5" />
                                    <div>
                                        <span class="text-sm font-medium text-gray-900 dark:text-white">Published</span>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Visible on the public blog</p>
                                    </div>
                                </label>

                                <label
                                    class="flex cursor-pointer items-start gap-3 rounded-lg p-2 transition-colors hover:bg-gray-50 dark:hover:bg-gray-800/50"
                                >
                                    <Checkbox v-model:checked="form.featured" name="featured" class="mt-0.5" />
                                    <div>
                                        <span class="text-sm font-medium text-gray-900 dark:text-white">Featured</span>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Eligible for homepage featured posts</p>
                                    </div>
                                </label>
                            </div>

                            <div v-if="form.published" class="mt-4 border-t border-gray-100 pt-4 dark:border-gray-700">
                                <InputLabel for="published_at" value="Publish date & time" class="text-gray-900 dark:text-white" />
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Leave empty to keep the current publish time when updating
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

                        <!-- Cover image -->
                        <div
                            class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900"
                        >
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Cover image</h3>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">URL used in blog cards and headers</p>

                            <div class="mt-4">
                                <InputLabel for="cover_image" value="Image URL" class="sr-only" />
                                <TextInput
                                    id="cover_image"
                                    v-model="form.cover_image"
                                    type="text"
                                    class="block w-full rounded-lg border-gray-300 bg-white text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:border-primary-400 dark:focus:ring-primary-400"
                                    placeholder="https://..."
                                />
                                <InputError class="mt-2" :message="form.errors.cover_image" />
                            </div>

                            <div
                                v-if="showCoverPreview"
                                class="mt-4 overflow-hidden rounded-lg border border-gray-200 bg-gray-50 dark:border-gray-600 dark:bg-gray-800"
                            >
                                <img
                                    :src="form.cover_image.trim()"
                                    alt="Cover preview"
                                    class="max-h-40 w-full object-cover"
                                    @error="$event.target.style.display = 'none'"
                                />
                            </div>
                        </div>

                        <!-- Category -->
                        <div
                            class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900"
                        >
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Category</h3>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Organize under one category</p>

                            <div class="mt-4">
                                <label for="category_id" class="sr-only">Category</label>
                                <select
                                    id="category_id"
                                    v-model="form.category_id"
                                    class="block w-full rounded-lg border-gray-300 bg-white text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:border-primary-400 dark:focus:ring-primary-400"
                                >
                                    <option value="">Select a category</option>
                                    <option v-for="category in categories" :key="category.id" :value="category.id">
                                        {{ category.name }}
                                    </option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.category_id" />
                            </div>
                        </div>

                        <!-- Tags -->
                        <div
                            class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900"
                        >
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Tags</h3>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Select all that apply</p>

                            <div class="mt-4 flex flex-wrap gap-2">
                                <label
                                    v-for="tag in tags"
                                    :key="tag.id"
                                    class="inline-flex cursor-pointer items-center gap-x-2 rounded-lg bg-gray-100 px-3 py-2 text-sm font-medium text-gray-700 ring-1 ring-inset ring-gray-300/50 transition-colors hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:ring-gray-600/50 dark:hover:bg-gray-700"
                                    :class="{
                                        'bg-primary-100 text-primary-800 ring-primary-500/50 dark:bg-primary-900/40 dark:text-primary-300 dark:ring-primary-500/50':
                                            form.tags.includes(tag.id),
                                    }"
                                >
                                    <Checkbox v-model:checked="form.tags" :value="tag.id" />
                                    {{ tag.name }}
                                </label>
                            </div>
                            <InputError class="mt-2" :message="form.errors.tags" />
                        </div>
                    </div>
                </aside>
            </div>

            <!-- Actions -->
            <div
                class="mt-8 flex flex-col-reverse items-stretch justify-end gap-3 border-t border-gray-200 pt-6 dark:border-gray-700 sm:flex-row sm:items-center"
            >
                <Link
                    :href="route('kiosk.posts.index')"
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
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path
                            class="opacity-75"
                            fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                        ></path>
                    </svg>
                    <svg v-else class="-ml-0.5 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    {{ form.processing ? 'Updating...' : 'Update post' }}
                </button>
            </div>
        </form>
    </KioskLayout>
</template>
