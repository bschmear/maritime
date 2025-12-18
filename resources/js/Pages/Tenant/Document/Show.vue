<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import Modal from '@/Components/Modal.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed, getCurrentInstance } from 'vue';

// Get access to global properties
const instance = getCurrentInstance();
const $formatDate = instance.appContext.config.globalProperties.$formatDate;

const props = defineProps({
    record: {
        type: Object,
        required: true,
    },
    recordType: {
        type: String,
        default: 'documents',
    },
    recordTitle: {
        type: String,
        default: 'Document',
    },
    previewUrl: {
        type: String,
        default: null,
    },
    downloadUrl: {
        type: String,
        default: null,
    },
    canPreview: {
        type: Boolean,
        default: false,
    },
    fileExtension: {
        type: String,
        default: '',
    },
    fileSize: {
        type: Number,
        default: 0,
    },
});

const isEditMode = ref(false);
const showDeleteModal = ref(false);
const isDeleting = ref(false);

const form = useForm({
    display_name: props.record.display_name || '',
    file: null,
    description: props.record.description || '',
});

const handleEdit = () => {
    isEditMode.value = true;
};

const handleCancelEdit = () => {
    isEditMode.value = false;
    // Reset form to original values
    form.display_name = props.record.display_name || '';
    form.file = null;
    form.description = props.record.description || '';
    form.clearErrors();
};

const breadcrumbItems = computed(() => {
    return [
        { label: 'Home', href: route('dashboard') },
        { label: 'Documents', href: route(`${props.recordType}.index`) },
        { label: props.record.display_name },
    ];
});

const isImageFile = computed(() => {
    const imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'svg'];
    return imageExtensions.includes(props.fileExtension.toLowerCase());
});

const formatFileSize = (bytes) => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
};

const handleFileChange = (event) => {
    form.file = event.target.files[0];
};

const handleSubmit = () => {
    form.transform((data) => ({
        ...data,
        _method: 'PUT'
    })).post(route(`${props.recordType}.update`, props.record.id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            isEditMode.value = false;
            form.file = null;
            router.reload({ only: ['record', 'previewUrl', 'downloadUrl', 'fileSize', 'fileExtension', 'canPreview'] });
        },
    });
};

const handleDelete = () => {
    showDeleteModal.value = true;
};

const confirmDelete = () => {
    isDeleting.value = true;
    router.delete(route(`${props.recordType}.destroy`, props.record.id), {
        onSuccess: () => {
            router.visit(route(`${props.recordType}.index`));
        },
        onError: () => {
            isDeleting.value = false;
        },
        onFinish: () => {
            isDeleting.value = false;
            showDeleteModal.value = false;
        },
    });
};

const cancelDelete = () => {
    showDeleteModal.value = false;
};
</script>

<template>
    <Head :title="`${recordTitle} - ${record.display_name}`" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="flex items-center justify-between mt-4">
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        {{ record.display_name }}
                    </h2>
                    <!-- View Mode Buttons (Mobile) -->
                    <div class="xl:hidden">
                        <div v-if="!isEditMode" class="flex items-center space-x-3">
                            <button
                                @click="handleEdit"
                                class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-primary-600 border border-transparent rounded-md hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500"
                            >
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                                Edit
                            </button>
                            <button
                                @click="handleDelete"
                                class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-red-600 border border-transparent rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                            >
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                                Delete
                            </button>
                        </div>

                        <!-- Edit Mode Buttons (Mobile) -->
                        <div v-else class="flex items-center space-x-3">
                            <button
                                @click="handleSubmit"
                                :disabled="form.processing"
                                class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-primary-600 border border-transparent rounded-md hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <svg v-if="form.processing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <svg v-else class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                {{ form.processing ? 'Saving...' : 'Save Changes' }}
                            </button>
                            <button
                                @click="handleCancelEdit"
                                :disabled="form.processing"
                                class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <div class="w-full grow flex flex-col">
            <div class="mb-4">
                <div class="flex flex-col lg:flex-row gap-6">
                    <!-- Left Column: Form -->
                    <div class="flex-1 bg-white rounded-lg shadow dark:bg-gray-800">
                        <form @submit.prevent="handleSubmit" enctype="multipart/form-data">
                            <div class="grid gap-6 sm:grid-cols-12 p-4 lg:p-6">
                                <div class="sm:col-span-12">
                                    <h5 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Primary Information</h5>
                                </div>

                                <div class="sm:col-span-6 lg:col-span-6 space-y-4 lg:space-y-6">
                                    <!-- Document Name -->
                                    <div>
                                        <label class="block mb-2 text-sm font-medium text-gray-900 dark:text-white" for="document_title">
                                            Name
                                        </label>
                                        <input
                                            type="text"
                                            v-model="form.display_name"
                                            id="document_title"
                                            :disabled="!isEditMode"
                                            :class="[
                                                'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500',
                                                !isEditMode ? 'opacity-75 cursor-not-allowed' : ''
                                            ]"
                                            required
                                        />
                                        <p v-if="form.errors.display_name" class="mt-2 text-sm text-red-600 dark:text-red-500">
                                            {{ form.errors.display_name }}
                                        </p>
                                    </div>

                                    <!-- Replace File -->
                                    <div v-if="isEditMode">
                                        <label class="block mb-2 text-sm font-medium text-gray-900 dark:text-white" for="file">
                                            Replace File
                                        </label>
                                        <input
                                            class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 dark:text-gray-400 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400"
                                            id="file"
                                            type="file"
                                            @change="handleFileChange"
                                        />
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-300">
                                            Upload PNG, JPG, GIF, SVG, PDF, DOC, DOCX, CSV, TXT, XLSX.
                                        </p>
                                        <p v-if="form.errors.file" class="mt-2 text-sm text-red-600 dark:text-red-500">
                                            {{ form.errors.file }}
                                        </p>
                                    </div>

                                    <!-- Description -->
                                    <div>
                                        <label class="block mb-2 text-sm font-medium text-gray-900 dark:text-white" for="description">
                                            Description (optional)
                                        </label>
                                        <textarea
                                            v-model="form.description"
                                            id="description"
                                            rows="6"
                                            :disabled="!isEditMode"
                                            :class="[
                                                'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500',
                                                !isEditMode ? 'opacity-75 cursor-not-allowed' : ''
                                            ]"
                                        ></textarea>
                                        <p v-if="form.errors.description" class="mt-2 text-sm text-red-600 dark:text-red-500">
                                            {{ form.errors.description }}
                                        </p>
                                    </div>
                                </div>

                                <!-- Right Column: Preview/File Info -->
                                <div class="sm:col-span-6 space-y-4">
                                    <!-- Image Preview -->
                                    <div v-if="canPreview && previewUrl && isImageFile">
                                        <label class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">
                                            Preview
                                        </label>
                                        <div class="rounded overflow-hidden border border-gray-200 dark:border-gray-600">
                                            <img
                                                :src="previewUrl"
                                                :alt="record.display_name"
                                                class="max-w-full object-contain"
                                                @error="$event.target.parentElement.innerHTML='<div class=\'text-center p-8 text-gray-600 dark:text-gray-300\'><p class=\'mb-3\'>Unable to load image preview.</p><a href=\'' + downloadUrl + '\' class=\'inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700\'>Download Image</a></div>'"
                                            />
                                        </div>
                                    </div>

                                    <!-- Non-Image File -->
                                    <div v-else>
                                        <label class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">
                                            File
                                        </label>
                                        <div class="text-center p-8 text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700">
                                            <div class="mb-4">
                                                <svg class="w-16 h-16 mx-auto text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"></path>
                                                </svg>
                                            </div>
                                            <p class="mb-3 text-sm">
                                                <strong>{{ record.display_name }}</strong>
                                            </p>
                                            <p class="mb-4 text-xs text-gray-500">
                                                <template v-if="['pdf', 'doc', 'docx', 'xlsx', 'txt', 'csv'].includes(fileExtension.toLowerCase())">
                                                    Click to download this {{ fileExtension.toUpperCase() }} file.
                                                </template>
                                                <template v-else>
                                                    This file type cannot be previewed in the browser.
                                                </template>
                                            </p>
                                            <div class="flex justify-center">
                                                <a
                                                    v-if="downloadUrl"
                                                    :href="downloadUrl"
                                                    class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-primary-600 border border-transparent rounded-lg hover:bg-primary-700 focus:outline-none focus:ring-4 focus:ring-primary-300"
                                                    download
                                                >
                                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                    </svg>
                                                    <span>Download File</span>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Sticky Submit Button (only in edit mode) -->
                            <div v-if="isEditMode" class="sticky bottom-0 shadow-[rgba(0,0,0,0.2)_0px_-5px_20px_0px]">
                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="w-full py-3 px-4 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-4 focus:ring-primary-300 disabled:opacity-50 disabled:cursor-not-allowed rounded-t-none"
                                >
                                    <svg v-if="form.processing" class="inline animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    {{ form.processing ? 'Saving...' : 'Save Updates' }}
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Right Column: Other Info -->
                    <div class="hidden lg:block w-full lg:max-w-md">
                        <div class="bg-white rounded-lg shadow dark:bg-gray-800 w-full overflow-hidden sticky top-5">
                            <div class="flex justify-between items-center p-4 sm:px-5 w-full font-semibold text-gray-900 bg-gray-100 dark:text-white dark:bg-gray-700">
                                Actions
                            </div>
                            <div class="p-4 sm:p-5 space-y-4">
                                <!-- Timestamp Information -->
                                <div class="space-y-3 pb-4 border-b border-gray-200 dark:border-gray-700">
                                    <div class="flex flex-col space-y-1">
                                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Created</span>
                                        <span class="text-sm text-gray-900 dark:text-gray-100">{{ $formatDate(record.created_at) }}</span>
                                    </div>
                                    <div class="flex flex-col space-y-1">
                                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Last Updated</span>
                                        <span class="text-sm text-gray-900 dark:text-gray-100">{{ $formatDate(record.updated_at) }}</span>
                                    </div>
                                    <div class="flex flex-col space-y-1">
                                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">File Size</span>
                                        <span class="text-sm text-gray-900 dark:text-gray-100">{{ formatFileSize(fileSize) }}</span>
                                    </div>
                                </div>

                                <!-- View Mode Buttons -->
                                <div v-if="!isEditMode" class="flex space-x-4">
                                    <button
                                        @click="handleEdit"
                                        class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-primary-600 border border-transparent rounded-md hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500"
                                    >
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                        Edit
                                    </button>
                                    <button
                                        @click="handleDelete"
                                        class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-red-600 border border-transparent rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                                    >
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                        Delete
                                    </button>
                                </div>

                                <!-- Edit Mode Buttons -->
                                <div v-else class="flex space-x-4">
                                    <button
                                        @click="handleSubmit"
                                        :disabled="form.processing"
                                        class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-primary-600 border border-transparent rounded-md hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        <svg v-if="form.processing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <svg v-else class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                        {{ form.processing ? 'Saving...' : 'Save Changes' }}
                                    </button>
                                    <button
                                        @click="handleCancelEdit"
                                        :disabled="form.processing"
                                        class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        Cancel
                                    </button>
                                </div>

                                <!-- Download Button -->
                                <div v-if="downloadUrl && !isEditMode" class="pt-4 border-t border-gray-200 dark:border-gray-700">
                                    <a
                                        :href="downloadUrl"
                                        class="w-full inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                        download
                                    >
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        Download File
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <Modal :show="showDeleteModal" @close="cancelDelete" max-width="md">
            <div class="p-6 text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 dark:bg-red-900">
                    <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-white">
                    Delete Document
                </h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Are you sure you want to delete "{{ record.display_name }}"? This action cannot be undone and will permanently remove this document.
                </p>
                <div class="mt-6 flex items-center justify-center space-x-3">
                    <button
                        @click="confirmDelete"
                        :disabled="isDeleting"
                        type="button"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-red-600 border border-transparent rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <svg v-if="isDeleting" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ isDeleting ? 'Deleting...' : 'Delete Document' }}
                    </button>
                    <button
                        @click="cancelDelete"
                        :disabled="isDeleting"
                        type="button"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </Modal>
    </TenantLayout>
</template>
