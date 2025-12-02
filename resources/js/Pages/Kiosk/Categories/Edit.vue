<script setup>
import KioskLayout from '@/Layouts/KioskLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    category: Object,
});

const form = useForm({
    name: props.category.name || '',
    description: props.category.description || '',
});

const submit = () => {
    form.put(route('kiosk.categories.update', props.category.id));
};
</script>

<template>
    <Head title="Edit Category" />

    <KioskLayout>
        <template #header>
            <div class="flex items-center gap-x-3">
                <Link
                    :href="route('kiosk.categories.index')"
                    class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                    </svg>
                </Link>
                <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Edit Category</h1>
            </div>
        </template>

        <form @submit.prevent="submit" class="space-y-8">
            <div class="grid grid-cols-1 gap-x-8 gap-y-8 md:grid-cols-3">
                <!-- Info Section -->
                <div class="px-4 sm:px-0">
                    <h2 class="text-base font-semibold leading-7 text-gray-900 dark:text-white">Category Information</h2>
                    <p class="mt-1 text-sm leading-6 text-gray-600 dark:text-gray-400">
                        Update the category details for organizing posts.
                    </p>
                </div>

                <!-- Form Section -->
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 shadow-sm rounded-xl md:col-span-2">
                    <div class="px-4 py-6 sm:p-8">
                        <div class="grid max-w-2xl grid-cols-1 gap-x-6 gap-y-8">
                            <!-- Name Field -->
                            <div>
                                <InputLabel for="name" value="Name" class="text-gray-900 dark:text-white" />
                                <TextInput
                                    id="name"
                                    v-model="form.name"
                                    type="text"
                                    class="mt-2 block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-sm focus:border-secondary-500 dark:focus:border-secondary-400 focus:ring-secondary-500 dark:focus:ring-secondary-400"
                                    placeholder="e.g., Technology, Lifestyle, Travel"
                                    required
                                    autofocus
                                />
                                <InputError class="mt-2" :message="form.errors.name" />
                            </div>

                            <!-- Description Field -->
                            <div>
                                <InputLabel for="description" value="Description" class="text-gray-900 dark:text-white" />
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    Optional description to help identify this category.
                                </p>
                                <textarea
                                    v-model="form.description"
                                    id="description"
                                    rows="4"
                                    class="mt-2 block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-sm focus:border-secondary-500 dark:focus:border-secondary-400 focus:ring-secondary-500 dark:focus:ring-secondary-400 placeholder:text-gray-400 dark:placeholder:text-gray-500"
                                    placeholder="Describe what this category is used for..."
                                />
                                <InputError class="mt-2" :message="form.errors.description" />
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="flex items-center justify-end gap-x-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 px-4 py-4 sm:px-8 rounded-b-xl">
                        <Link
                            :href="route('kiosk.categories.index')"
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
                            {{ form.processing ? 'Updating...' : 'Update Category' }}
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </KioskLayout>
</template>
