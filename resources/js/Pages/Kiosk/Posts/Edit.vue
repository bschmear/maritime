<script setup>
import KioskLayout from '@/Layouts/KioskLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import Checkbox from '@/Components/Checkbox.vue';
import TipTapEditor from '@/Components/TipTapEditor.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

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
    published_at: props.post.published_at || '',
    tags: props.post.tags?.map(tag => tag.id) || [],
});

const submit = () => {
    form.put(route('kiosk.posts.update', props.post.id));
};
</script>

<template>
    <Head title="Edit Post" />

    <KioskLayout>
        <template #header>
            <div class="flex items-center gap-x-3">
                <Link
                    :href="route('kiosk.posts.index')"
                    class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                    </svg>
                </Link>
                <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Edit Post</h1>
            </div>
        </template>

        <form @submit.prevent="submit" class="space-y-8">
            <!-- Main Content -->
            <div class="grid grid-cols-1 gap-x-8 gap-y-8 md:grid-cols-3">
                <div class="px-4 sm:px-0">
                    <h2 class="text-base font-semibold leading-7 text-gray-900 dark:text-white">Post Information</h2>
                    <p class="mt-1 text-sm leading-6 text-gray-600 dark:text-gray-400">
                        Update your blog post with rich content and metadata.
                    </p>
                </div>

                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 shadow-sm rounded-xl md:col-span-2">
                    <div class="px-4 py-6 sm:p-8">
                        <div class="grid max-w-2xl grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6">
                            <!-- Title -->
                            <div class="sm:col-span-6">
                                <InputLabel for="title" value="Title" class="text-gray-900 dark:text-white" />
                                <TextInput
                                    id="title"
                                    v-model="form.title"
                                    type="text"
                                    class="mt-2 block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-sm focus:border-secondary-500 dark:focus:border-secondary-400 focus:ring-secondary-500 dark:focus:ring-secondary-400"
                                    placeholder="Enter an engaging post title..."
                                    required
                                    autofocus
                                />
                                <InputError class="mt-2" :message="form.errors.title" />
                            </div>

                            <!-- Short Description -->
                            <div class="sm:col-span-6">
                                <InputLabel for="short_description" value="Short Description" class="text-gray-900 dark:text-white" />
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Brief summary for previews and social media
                                </p>
                                <TextInput
                                    id="short_description"
                                    v-model="form.short_description"
                                    type="text"
                                    class="mt-2 block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-sm focus:border-secondary-500 dark:focus:border-secondary-400 focus:ring-secondary-500 dark:focus:ring-secondary-400"
                                    placeholder="Write a compelling summary..."
                                />
                                <InputError class="mt-2" :message="form.errors.short_description" />
                            </div>

                            <!-- Body -->
                            <div class="sm:col-span-6">
                                <TipTapEditor
                                    id="body"
                                    label="Content"
                                    v-model="form.body"
                                    :error="form.errors.body"
                                    :show-anchor="true"
                                />
                            </div>

                            <!-- Category & Cover Image Row -->
                            <div class="sm:col-span-3">
                                <InputLabel for="category_id" value="Category" class="text-gray-900 dark:text-white" />
                                <select
                                    v-model="form.category_id"
                                    id="category_id"
                                    class="mt-2 block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-sm focus:border-secondary-500 dark:focus:border-secondary-400 focus:ring-secondary-500 dark:focus:ring-secondary-400"
                                >
                                    <option value="">Select a category</option>
                                    <option v-for="category in categories" :key="category.id" :value="category.id">
                                        {{ category.name }}
                                    </option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.category_id" />
                            </div>

                            <div class="sm:col-span-3">
                                <InputLabel for="cover_image" value="Cover Image URL" class="text-gray-900 dark:text-white" />
                                <TextInput
                                    id="cover_image"
                                    v-model="form.cover_image"
                                    type="text"
                                    class="mt-2 block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-sm focus:border-secondary-500 dark:focus:border-secondary-400 focus:ring-secondary-500 dark:focus:ring-secondary-400"
                                    placeholder="https://..."
                                />
                                <InputError class="mt-2" :message="form.errors.cover_image" />
                            </div>

                            <!-- Tags -->
                            <div class="sm:col-span-6">
                                <InputLabel value="Tags" class="text-gray-900 dark:text-white" />
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Select relevant tags for this post
                                </p>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <label
                                        v-for="tag in tags"
                                        :key="tag.id"
                                        class="inline-flex items-center gap-x-2 rounded-lg bg-gray-100 dark:bg-gray-800 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 ring-1 ring-inset ring-gray-300/50 dark:ring-gray-600/50 cursor-pointer hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
                                        :class="{ 'bg-secondary-100 dark:bg-secondary-900/30 ring-secondary-500/50 dark:ring-secondary-500/50 text-secondary-700 dark:text-secondary-300': form.tags.includes(tag.id) }"
                                    >
                                        <Checkbox
                                            v-model:checked="form.tags"
                                            :value="tag.id"
                                        />
                                        {{ tag.name }}
                                    </label>
                                </div>
                                <InputError class="mt-2" :message="form.errors.tags" />
                            </div>

                            <!-- Publishing Options -->
                            <div class="sm:col-span-6">
                                <InputLabel value="Publishing Options" class="text-gray-900 dark:text-white" />
                                <div class="mt-3 space-y-3">
                                    <label class="flex items-center gap-x-3 p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800/50 cursor-pointer transition-colors">
                                        <Checkbox v-model:checked="form.featured" name="featured" />
                                        <div>
                                            <span class="text-sm font-medium text-gray-900 dark:text-white">Featured Post</span>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">Display prominently on the homepage</p>
                                        </div>
                                    </label>

                                    <label class="flex items-center gap-x-3 p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800/50 cursor-pointer transition-colors">
                                        <Checkbox v-model:checked="form.published" name="published" />
                                        <div>
                                            <span class="text-sm font-medium text-gray-900 dark:text-white">Publish Immediately</span>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">Make this post visible to readers</p>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <!-- Published At (conditional) -->
                            <div v-if="form.published" class="sm:col-span-6">
                                <InputLabel for="published_at" value="Publish Date & Time" class="text-gray-900 dark:text-white" />
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Leave empty to publish at current date and time
                                </p>
                                <input
                                    v-model="form.published_at"
                                    type="datetime-local"
                                    id="published_at"
                                    class="mt-2 block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-sm focus:border-secondary-500 dark:focus:border-secondary-400 focus:ring-secondary-500 dark:focus:ring-secondary-400"
                                />
                                <InputError class="mt-2" :message="form.errors.published_at" />
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="flex items-center justify-end gap-x-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 px-4 py-4 sm:px-8 rounded-b-xl">
                        <Link
                            :href="route('kiosk.posts.index')"
                            class="text-sm font-semibold text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white transition-colors"
                        >
                            Cancel
                        </Link>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="inline-flex items-center gap-x-2 rounded-lg bg-gradient-to-r from-primary-500 to-secondary-500 px-4 py-2.5 text-sm font-semibold text-white shadow-md hover:shadow-lg transition-all hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:scale-100"
                        >
                            <svg
                                v-if="form.processing"
                                class="animate-spin -ml-1 h-4 w-4 text-white"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                            >
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <svg
                                v-else
                                class="-ml-0.5 h-5 w-5"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            {{ form.processing ? 'Updating...' : 'Update Post' }}
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </KioskLayout>
</template>

