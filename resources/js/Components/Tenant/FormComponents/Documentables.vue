<template>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between pb-2 sm:pb-5 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center gap-2">
                <span class="material-icons text-gray-600 dark:text-gray-400">folder_open</span>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Documents</h3>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">
                    {{ documents.length }}
                </span>
            </div>
            <button
                @click="showAttachModal = true"
                type="button"
                class="inline-flex items-center gap-2 px-4 py-1.5 text-sm font-medium text-white bg-primary-600 border border-transparent rounded-lg hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 dark:focus:ring-offset-gray-900 transition-all shadow-sm hover:shadow-md"
            >
                <span class="material-icons text-lg">add</span>
                Add Document
            </button>
        </div>

        <!-- Documents Grid -->
        <div v-if="documents.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div
                v-for="document in documents"
                :key="document.id"
                class="group relative bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 hover:shadow-lg transition-all duration-200 hover:border-primary-300 dark:hover:border-primary-600"
            >
                <!-- File Type Icon -->
                <div class="flex items-start gap-3 mb-3">
                    <div class="flex-shrink-0 w-12 h-12 bg-gradient-to-br from-primary-50 to-primary-100 dark:from-primary-900/30 dark:to-primary-800/30 rounded-lg flex items-center justify-center">
                        <span class="material-icons text-primary-600 dark:text-primary-400 text-2xl">
                            {{ getFileIcon(document.file_extension) }}
                        </span>
                    </div>

                    <!-- Document Info -->
                    <div class="flex-1 min-w-0">
                        <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate mb-1">
                            {{ document.display_name }}
                        </h4>
                        <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                            <span class="inline-flex items-center px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-700 font-medium">
                                {{ document.file_extension?.toUpperCase() }}
                            </span>
                            <span>{{ formatFileSize(document.file_size) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Description -->
                <p v-if="document.description" class="text-xs text-gray-600 dark:text-gray-400 mb-3 line-clamp-2">
                    {{ document.description }}
                </p>
                <p v-else class="text-xs text-gray-400 dark:text-gray-500 italic mb-3">
                    No description
                </p>

                <!-- Footer -->
                <div class="flex items-center justify-between pt-3 border-t border-gray-100 dark:border-gray-700">
                    <span class="text-xs text-gray-400 dark:text-gray-500 flex items-center gap-1">
                        <span class="material-icons text-xs">schedule</span>
                        {{ formatDate(document.created_at) }}
                    </span>

                    <!-- Actions -->
                    <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                        <button
                            @click="openEditModal(document)"
                            type="button"
                            title="Edit"
                            class="inline-flex items-center justify-center w-8 h-8 text-gray-600 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/30 rounded-lg transition-all"
                        >
                            <span class="material-icons text-lg">edit</span>
                        </button>

                        <button
                            @click="downloadDocument(document)"
                            type="button"
                            title="Download"
                            class="inline-flex items-center justify-center w-8 h-8 text-gray-600 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 hover:bg-primary-50 dark:hover:bg-primary-900/30 rounded-lg transition-all"
                        >
                            <span class="material-icons text-lg">download</span>
                        </button>

                        <button
                            @click="detachDocument(document)"
                            :disabled="detachingDocumentId === document.id"
                            type="button"
                            title="Remove"
                            class="inline-flex items-center justify-center w-8 h-8 text-gray-600 dark:text-gray-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30 rounded-lg transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <span v-if="detachingDocumentId === document.id" class="material-icons text-sm animate-spin">sync</span>
                            <span v-else class="material-icons text-lg">delete_outline</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div v-else class="text-center py-16">
            <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-gray-100 dark:bg-gray-800 mb-4">
                <span class="material-icons text-5xl text-gray-400 dark:text-gray-500">description</span>
            </div>
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-1">No documents yet</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Add your first document to get started</p>
            <button
                @click="showAttachModal = true"
                type="button"
                class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-900/30 rounded-lg hover:bg-primary-100 dark:hover:bg-primary-900/50 transition-colors"
            >
                <span class="material-icons text-sm">add</span>
                Add Document
            </button>
        </div>

        <!-- Attach Document Modal -->
        <div v-if="showAttachModal" class="fixed inset-0 z-50 overflow-y-auto" @keydown.escape="closeAttachModal">
            <!-- Background overlay with blur -->
            <div class="fixed inset-0 bg-black/50 dark:bg-black/70 backdrop-blur-sm transition-opacity" @click="closeAttachModal"></div>

            <!-- Modal container -->
            <div class="flex items-center justify-center min-h-screen p-4">
                <!-- Modal panel -->
                <div class="relative bg-white dark:bg-gray-800 rounded-2xl shadow-2xl transform transition-all w-full max-w-4xl max-h-[90vh] flex flex-col">
                    <!-- Header -->
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-primary-100 dark:bg-primary-900/30 rounded-lg flex items-center justify-center">
                                <span class="material-icons text-primary-600 dark:text-primary-400">attach_file</span>
                            </div>
                            <div>
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Add Document</h2>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Attach an existing document or upload a new one</p>
                            </div>
                        </div>
                        <button
                            @click="closeAttachModal"
                            type="button"
                            class="flex items-center justify-center w-10 h-10 rounded-full text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-all"
                        >
                            <span class="material-icons">close</span>
                        </button>
                    </div>

                    <!-- Tabs -->
                    <div class="px-6 pt-4 border-b border-gray-200 dark:border-gray-700">
                        <nav class="flex gap-1">
                            <button
                                @click="activeTab = 'existing'"
                                :class="[
                                    'flex items-center gap-2 px-4 py-2.5 text-sm font-medium rounded-t-lg transition-all',
                                    activeTab === 'existing'
                                        ? 'bg-white dark:bg-gray-800 text-primary-600 dark:text-primary-400 border-b-2 border-primary-600 dark:border-primary-400'
                                        : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700/50'
                                ]"
                            >
                                <span class="material-icons text-sm">link</span>
                                Attach Existing
                            </button>
                            <button
                                @click="activeTab = 'upload'"
                                :class="[
                                    'flex items-center gap-2 px-4 py-2.5 text-sm font-medium rounded-t-lg transition-all',
                                    activeTab === 'upload'
                                        ? 'bg-white dark:bg-gray-800 text-primary-600 dark:text-primary-400 border-b-2 border-primary-600 dark:border-primary-400'
                                        : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700/50'
                                ]"
                            >
                                <span class="material-icons text-sm">upload_file</span>
                                Upload New
                            </button>
                        </nav>
                    </div>

                    <!-- Content Area -->
                    <div class="flex-1 overflow-y-auto p-6">
                        <!-- Existing Documents Tab -->
                        <div v-if="activeTab === 'existing'" class="space-y-4">
                            <!-- Search -->
                            <div class="relative">
                                <span class="material-icons absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 dark:text-gray-500">search</span>
                                <input
                                    v-model="searchQuery"
                                    type="text"
                                    placeholder="Search documents by name..."
                                    class="w-full pl-10 pr-4 py-3 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 dark:focus:ring-primary-400 focus:border-transparent transition-all"
                                    @input="debouncedSearch"
                                />
                            </div>

                            <!-- Documents List -->
                            <div class="space-y-3 min-h-[300px]">
                                <div v-if="availableDocuments.length === 0 && !loadingSearch && searchQuery.trim()" class="text-center py-12">
                                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 dark:bg-gray-800 mb-3">
                                        <span class="material-icons text-gray-400 dark:text-gray-500 text-3xl">search_off</span>
                                    </div>
                                    <p class="text-gray-500 dark:text-gray-400 font-medium">No documents found</p>
                                    <p class="text-sm text-gray-400 dark:text-gray-500">Try a different search term</p>
                                </div>

                                <div v-else-if="!searchQuery.trim()" class="text-center py-12">
                                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 dark:bg-gray-800 mb-3">
                                        <span class="material-icons text-gray-400 dark:text-gray-500 text-3xl">search</span>
                                    </div>
                                    <p class="text-gray-500 dark:text-gray-400 font-medium">Start typing to search</p>
                                    <p class="text-sm text-gray-400 dark:text-gray-500">Search for documents by name</p>
                                </div>

                                <div v-else-if="loadingSearch" class="text-center py-12">
                                    <span class="material-icons animate-spin text-primary-600 dark:text-primary-400 text-4xl">sync</span>
                                    <p class="text-gray-500 dark:text-gray-400 mt-2">Searching...</p>
                                </div>

                                <div
                                    v-for="document in availableDocuments"
                                    :key="document.id"
                                    class="flex items-center justify-between p-4 border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 rounded-xl hover:border-primary-300 dark:hover:border-primary-600 hover:bg-primary-50/30 dark:hover:bg-primary-900/20 transition-all group"
                                >
                                    <div class="flex items-center gap-3 flex-1 min-w-0">
                                        <div class="w-10 h-10 bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-700 dark:to-gray-600 rounded-lg flex items-center justify-center flex-shrink-0">
                                            <span class="material-icons text-gray-600 dark:text-gray-300">
                                                {{ getFileIcon(document.file_extension) }}
                                            </span>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate">
                                                {{ document.display_name }}
                                            </h4>
                                            <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-700 font-medium">
                                                    {{ document.file_extension?.toUpperCase() }}
                                                </span>
                                                <span>{{ formatFileSize(document.file_size) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <button
                                        @click="attachDocument(document)"
                                        :disabled="attachingDocumentId === document.id"
                                        type="button"
                                        class="flex items-center gap-2 px-4 py-2 text-sm font-medium bg-primary-600 dark:bg-primary-600 text-white rounded-lg hover:bg-primary-700 dark:hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-sm hover:shadow-md"
                                    >
                                        <span v-if="attachingDocumentId === document.id" class="material-icons text-sm animate-spin">sync</span>
                                        <span v-else class="material-icons text-sm">add</span>
                                        <span>Attach</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Upload New Tab -->
                        <div v-if="activeTab === 'upload'" class="max-w-2xl mx-auto">
                            <form @submit.prevent="uploadDocument" class="space-y-6">
                                <!-- File Drop Zone -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        <span class="flex items-center gap-1">
                                            <span class="material-icons text-sm">attach_file</span>
                                            Select File
                                        </span>
                                    </label>
                                    <div class="relative">
                                        <input
                                            ref="fileInput"
                                            type="file"
                                            accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,.jpg,.jpeg,.png"
                                            @change="handleFileSelect"
                                            class="hidden"
                                        />
                                        <button
                                            type="button"
                                            @click="$refs.fileInput.click()"
                                            class="w-full border-2 border-dashed border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 rounded-xl p-8 hover:border-primary-400 dark:hover:border-primary-500 hover:bg-primary-50/30 dark:hover:bg-primary-900/20 transition-all group"
                                        >
                                            <div v-if="!selectedFile" class="text-center">
                                                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 dark:bg-gray-700 group-hover:bg-primary-100 dark:group-hover:bg-primary-900/30 mb-3 transition-colors">
                                                    <span class="material-icons text-gray-400 dark:text-gray-500 group-hover:text-primary-600 dark:group-hover:text-primary-400 text-3xl transition-colors">cloud_upload</span>
                                                </div>
                                                <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Click to select file</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">PDF, DOC, XLS, TXT, CSV, or Images</p>
                                            </div>
                                            <div v-else class="flex items-center gap-3">
                                                <div class="w-12 h-12 bg-primary-100 dark:bg-primary-900/30 rounded-lg flex items-center justify-center">
                                                    <span class="material-icons text-primary-600 dark:text-primary-400">description</span>
                                                </div>
                                                <div class="flex-1 text-left">
                                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ selectedFile.name }}</p>
                                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ formatFileSize(selectedFile.size) }}</p>
                                                </div>
                                                <span class="material-icons text-primary-600 dark:text-primary-400">check_circle</span>
                                            </div>
                                        </button>
                                    </div>
                                </div>

                                <!-- Document Name -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        <span class="flex items-center gap-1">
                                            <span class="material-icons text-sm">title</span>
                                            Document Name
                                        </span>
                                    </label>
                                    <input
                                        v-model="uploadForm.display_name"
                                        type="text"
                                        class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 dark:focus:ring-primary-400 focus:border-transparent transition-all"
                                        placeholder="Enter a descriptive name"
                                        required
                                    />
                                </div>

                                <!-- Description -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        <span class="flex items-center gap-1">
                                            <span class="material-icons text-sm">notes</span>
                                            Description
                                            <span class="text-gray-400 dark:text-gray-500 font-normal">(optional)</span>
                                        </span>
                                    </label>
                                    <textarea
                                        v-model="uploadForm.description"
                                        rows="3"
                                        class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 dark:focus:ring-primary-400 focus:border-transparent transition-all resize-none"
                                        placeholder="Add details about this document..."
                                    ></textarea>
                                </div>

                                <!-- Upload Button -->
                                <div class="flex justify-end gap-3 pt-4">
                                    <button
                                        type="button"
                                        @click="closeAttachModal"
                                        class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-all"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        :disabled="!selectedFile || uploading"
                                        class="inline-flex items-center gap-2 px-6 py-2.5 text-sm font-medium text-white bg-primary-600 dark:bg-primary-600 rounded-lg hover:bg-primary-700 dark:hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-sm hover:shadow-md"
                                    >
                                        <span v-if="uploading" class="material-icons text-sm animate-spin">sync</span>
                                        <span v-else class="material-icons text-sm">upload</span>
                                        {{ uploading ? 'Uploading...' : 'Upload & Attach' }}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Document Modal -->
        <div v-if="showEditModal" class="fixed inset-0 z-50 overflow-y-auto" @keydown.escape="closeEditModal">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <!-- Background overlay -->
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="closeEditModal"></div>

                <!-- Modal panel -->
                <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 dark:bg-blue-900/30 sm:mx-0 sm:h-10 sm:w-10">
                                <span class="material-icons text-blue-600 dark:text-blue-400">edit</span>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left flex-1">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100">
                                    Edit Document
                                </h3>
                                <div class="mt-4">
                                    <!-- Form Fields -->
                                    <div class="space-y-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                Document Name
                                            </label>
                                            <input
                                                v-model="editForm.display_name"
                                                type="text"
                                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                                placeholder="Enter document name"
                                            />
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                Description
                                            </label>
                                            <textarea
                                                v-model="editForm.description"
                                                rows="3"
                                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                                placeholder="Enter description"
                                            ></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-gray-50 dark:bg-gray-700/50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button
                            @click="updateDocument"
                            :disabled="isUpdatingDocument"
                            type="button"
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <span v-if="isUpdatingDocument" class="material-icons animate-spin mr-2 text-sm">sync</span>
                            {{ isUpdatingDocument ? 'Saving...' : 'Save Changes' }}
                        </button>
                        <button
                            @click="closeEditModal"
                            type="button"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-700 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import axios from 'axios';
import { debounce } from 'lodash-es';

// Props
const props = defineProps({
    modelValue: {
        type: Array,
        default: () => []
    },
    parentId: {
        type: Number,
        required: true
    },
    parentType: {
        type: String,
        required: true
    }
});

// Emits
const emit = defineEmits(['update:modelValue']);

// Reactive data
const documents = ref([...props.modelValue]);
const showAttachModal = ref(false);
const activeTab = ref('existing');
const searchQuery = ref('');
const availableDocuments = ref([]);
const loadingSearch = ref(false);
const attachingDocumentId = ref(null);
const selectedFile = ref(null);
const uploadForm = ref({
    display_name: '',
    description: ''
});
const uploading = ref(false);

// Edit modal state
const showEditModal = ref(false);
const editingDocument = ref(null);
const editForm = ref({
    display_name: '',
    description: ''
});
const isUpdatingDocument = ref(false);

// Detach loading state
const detachingDocumentId = ref(null);

// Watch for prop changes
watch(() => props.modelValue, (newValue) => {
    documents.value = [...newValue];
}, { deep: true });

// Methods
const getFileIcon = (extension) => {
    const icons = {
        'pdf': 'picture_as_pdf',
        'doc': 'description',
        'docx': 'description',
        'xls': 'table_chart',
        'xlsx': 'table_chart',
        'txt': 'text_snippet',
        'csv': 'table_chart',
        'jpg': 'image',
        'jpeg': 'image',
        'png': 'image'
    };
    return icons[extension?.toLowerCase()] || 'insert_drive_file';
};

const formatFileSize = (bytes) => {
    if (!bytes) return '0 B';
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    return Math.round(bytes / Math.pow(1024, i) * 100) / 100 + ' ' + sizes[i];
};

const formatDate = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString();
};

const downloadDocument = (document) => {
    // Open download URL in new window/tab
    window.open(route('documents.download', document.id), '_blank');
};

const detachDocument = async (document) => {
    if (!confirm('Are you sure you want to remove this document?')) {
        return;
    }

    if (detachingDocumentId.value) return; // Prevent multiple simultaneous detaches

    detachingDocumentId.value = document.id;

    try {
        await axios.delete(route('documentables.detach'), {
            params: {
                document_id: document.id,
                documentable_type: `App\\Domain\\${props.parentType}\\Models\\${props.parentType}`,
                documentable_id: props.parentId
            }
        });

        // Remove from local list
        documents.value = documents.value.filter(doc => doc.id !== document.id);
        emit('update:modelValue', documents.value);
    } catch (error) {
        console.error('Error detaching document:', error);
        alert('Failed to remove document. Please try again.');
    } finally {
        detachingDocumentId.value = null;
    }
};

const closeAttachModal = () => {
    showAttachModal.value = false;
    activeTab.value = 'existing';
    searchQuery.value = '';
    availableDocuments.value = [];
    selectedFile.value = null;
    uploadForm.value = {
        display_name: '',
        description: ''
    };
};

const debouncedSearch = debounce(async () => {
    if (!searchQuery.value.trim()) {
        availableDocuments.value = [];
        return;
    }

    loadingSearch.value = true;
    try {
        const response = await axios.get(route('documents.search'), {
            params: {
                q: searchQuery.value.toLowerCase(), // Convert search query to lowercase
                exclude_attached_to: `${props.parentType}:${props.parentId}`,
                limit: 20
            }
        });
        availableDocuments.value = response.data.documents || [];
    } catch (error) {
        console.error('Error searching documents:', error);
        availableDocuments.value = [];
    } finally {
        loadingSearch.value = false;
    }
}, 300);

const attachDocument = async (document) => {
    attachingDocumentId.value = document.id;

    try {
        await axios.post(route('documentables.attach'), {
            document_id: document.id,
            documentable_type: `App\\Domain\\${props.parentType}\\Models\\${props.parentType}`,
            documentable_id: props.parentId
        });

        // Add to local list
        documents.value.push(document);
        emit('update:modelValue', documents.value);

        // Close modal and reset
        closeAttachModal();
    } catch (error) {
        console.error('Error attaching document:', error);
        alert('Failed to attach document. Please try again.');
    } finally {
        attachingDocumentId.value = null;
    }
};

const handleFileSelect = (event) => {
    const file = event.target.files[0];
    if (file) {
        selectedFile.value = file;
        if (!uploadForm.value.display_name) {
            uploadForm.value.display_name = file.name;
        }
    }
};

const uploadDocument = async () => {
    if (!selectedFile.value) return;

    uploading.value = true;

    try {
        const formData = new FormData();
        formData.append('file', selectedFile.value);
        formData.append('display_name', uploadForm.value.display_name);
        formData.append('description', uploadForm.value.description || '');
        formData.append('attach_to_type', `App\\Domain\\${props.parentType}\\Models\\${props.parentType}`);
        formData.append('attach_to_id', props.parentId);

        const response = await axios.post(route('documents.upload-attach'), formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        });

        // Add to local list
        documents.value.push(response.data.document);
        emit('update:modelValue', documents.value);

        closeAttachModal();
    } catch (error) {
        console.error('Error uploading document:', error);
        alert('Failed to upload document. Please try again.');
    } finally {
        uploading.value = false;
    }
};

const openEditModal = (document) => {
    editingDocument.value = document;
    editForm.value = {
        display_name: document.display_name || '',
        description: document.description || ''
    };
    showEditModal.value = true;
};

const closeEditModal = () => {
    showEditModal.value = false;
    editingDocument.value = null;
    editForm.value = {
        display_name: '',
        description: ''
    };
};

const updateDocument = async () => {
    if (!editingDocument.value) return;

    isUpdatingDocument.value = true;

    try {
        const response = await axios.patch(route('documents.update', editingDocument.value.id), {
            display_name: editForm.value.display_name,
            description: editForm.value.description
        }, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        });

        // Update local list
        const index = documents.value.findIndex(doc => doc.id === editingDocument.value.id);
        if (index !== -1) {
            documents.value[index] = { ...documents.value[index], ...response.data.record };
            emit('update:modelValue', documents.value);
        }

        closeEditModal();
    } catch (error) {
        console.error('Error updating document:', error);
        alert('Failed to update document. Please try again.');
    } finally {
        isUpdatingDocument.value = false;
    }
};

// Initialize search when modal opens
watch(showAttachModal, (newValue) => {
    if (newValue && activeTab.value === 'existing' && searchQuery.value.trim()) {
        debouncedSearch();
    }
});
</script>

<style scoped>
/* Smooth animations */
@keyframes spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

.animate-spin {
    animation: spin 1s linear infinite;
}

.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>