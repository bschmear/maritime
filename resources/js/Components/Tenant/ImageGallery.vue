<template>
    <div class="space-y-6">
        <!-- Upload Dropzone -->
        <div
            @drop.prevent="handleDrop"
            @dragover.prevent="isDragging = true"
            @dragleave.prevent="isDragging = false"
            :class="[
                'border-2 border-dashed rounded-xl p-12 text-center transition-all duration-200',
                isDragging
                    ? 'border-primary-500 bg-primary-50 scale-[1.02]'
                    : 'border-gray-300 hover:border-gray-400 hover:bg-gray-50'
            ]"
        >
            <input
                ref="fileInput"
                type="file"
                multiple
                accept="image/*"
                @change="handleFileSelect"
                class="hidden"
            />
            
            <div class="space-y-4">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100">
                    <span class="material-icons text-4xl text-gray-400">cloud_upload</span>
                </div>
                <div>
                    <p class="text-base font-medium text-gray-700 mb-1">
                        Drop images here to upload
                    </p>
                    <p class="text-sm text-gray-500">or</p>
                </div>
                <button
                    type="button"
                    @click="$refs.fileInput.click()"
                    class="inline-flex items-center gap-2 px-6 py-3 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors shadow-sm hover:shadow-md"
                >
                    <span class="material-icons text-xl">add_photo_alternate</span>
                    <span>Browse Files</span>
                </button>
                <p class="text-xs text-gray-500 mt-3">
                    <span class="material-icons text-sm align-middle">info</span>
                    Images will be automatically resized to max 2000px width
                </p>
            </div>
        </div>

        <!-- Upload Progress -->
        <div v-if="uploading" class="bg-white border border-gray-200 rounded-lg p-4 shadow-sm">
            <div class="flex items-center gap-3 mb-3">
                <span class="material-icons text-primary-600 animate-spin">sync</span>
                <span class="text-sm font-medium text-gray-700">Uploading images...</span>
                <span class="ml-auto text-sm font-semibold text-gray-900">{{ uploadProgress }}%</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2 overflow-hidden">
                <div
                    class="bg-primary-600 h-2 rounded-full transition-all duration-300 ease-out"
                    :style="{ width: uploadProgress + '%' }"
                ></div>
            </div>
        </div>

        <!-- Upload Error -->
        <div v-if="uploadError" class="bg-red-50 border border-red-200 rounded-lg p-4">
            <div class="flex items-center gap-3">
                <span class="material-icons text-red-600">error_outline</span>
                <div class="flex-1">
                    <p class="text-sm font-medium text-red-800">Upload Failed</p>
                    <p class="text-sm text-red-700">{{ uploadError }}</p>
                </div>
                <button
                    @click="uploadError = ''"
                    class="text-red-600 hover:text-red-800"
                >
                    <span class="material-icons text-lg">close</span>
                </button>
            </div>
        </div>

        <!-- Image Grid -->
        <div v-if="images.length > 0" class="space-y-4">
            <div class="flex items-center justify-between pb-2 border-b border-gray-200">
                <div class="flex items-center gap-2">
                    <span class="material-icons text-gray-600 dark:text-white">collections</span>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Images</h3>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                        {{ images.length }}
                    </span>
                </div>
                <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-white">
                    <span class="material-icons text-sm">drag_indicator</span>
                    <span>Drag to reorder</span>
                </div>
            </div>
            
            <div
                ref="imageGrid"
                class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4 relative"
                :class="{ 'pointer-events-none opacity-75': isReordering }"
            >
                <!-- Reordering overlay -->
                <div v-if="isReordering" class="absolute inset-0 flex items-center justify-center bg-white/80 backdrop-blur-sm rounded-lg z-20">
                    <div class="flex items-center gap-2 bg-white px-4 py-2 rounded-lg shadow-lg">
                        <span class="material-icons animate-spin text-primary-600">sync</span>
                        <span class="text-sm font-medium text-gray-700">Saving order...</span>
                    </div>
                </div>
                <div
                    v-for="image in sortedImages"
                    :key="image.id"
                    :data-id="image.id"
                    class="relative group bg-gray-100 rounded-xl overflow-hidden aspect-square shadow-sm hover:shadow-md transition-all duration-200"
                >
                    <!-- Image -->
                    <img
                        :src="getImageUrl(image)"
                        :alt="image.display_name"
                        class="w-full h-full object-cover"
                        loading="lazy"
                    />
                    
                    <!-- Primary Badge -->
                    <div
                        v-if="image.is_primary"
                        class="absolute top-3 left-3 inline-flex items-center gap-1 bg-amber-500 text-white text-xs font-medium px-2.5 py-1 rounded-full shadow-lg z-10"
                    >
                        <span class="material-icons text-sm">star</span>
                        <span>Primary</span>
                    </div>
                    
                    <!-- Drag Handle - Always visible on top -->
                    <div class="drag-handle absolute top-3 right-3 flex items-center justify-center w-8 h-8 bg-white/95 backdrop-blur-sm rounded-full cursor-move shadow-lg hover:scale-110 transition-all duration-200 z-10 opacity-0 group-hover:opacity-100">
                        <span class="material-icons text-gray-700 text-lg">drag_indicator</span>
                    </div>
                    
                    <!-- Hover Overlay -->
                    <div
                        class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/0 to-black/20 opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none"
                    >
                        <!-- Action Buttons -->
                        <div class="absolute bottom-3 left-3 right-3 flex items-center justify-center gap-2 pointer-events-auto">
                            <button
                                v-if="!image.is_primary"
                                @click.stop="setPrimary(image)"
                                :disabled="settingPrimaryImageId === image.id"
                                type="button"
                                title="Set as Primary"
                                class="flex items-center justify-center w-10 h-10 bg-white/95 backdrop-blur-sm text-amber-600 rounded-full hover:bg-white hover:scale-110 transition-all duration-200 shadow-lg disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <span v-if="settingPrimaryImageId === image.id" class="material-icons animate-spin text-lg">sync</span>
                                <span v-else class="material-icons text-xl">star_border</span>
                            </button>

                            <button
                                @click.stop="openEditModal(image)"
                                type="button"
                                title="Edit Image"
                                class="flex items-center justify-center w-10 h-10 bg-white/95 backdrop-blur-sm text-blue-600 rounded-full hover:bg-white hover:scale-110 transition-all duration-200 shadow-lg"
                            >
                                <span class="material-icons text-xl">edit</span>
                            </button>

                            <button
                                @click.stop="deleteImage(image)"
                                :disabled="deletingImageId === image.id"
                                type="button"
                                title="Delete Image"
                                class="flex items-center justify-center w-10 h-10 bg-white/95 backdrop-blur-sm text-red-600 rounded-full hover:bg-white hover:scale-110 transition-all duration-200 shadow-lg disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <span v-if="deletingImageId === image.id" class="material-icons animate-spin text-lg">sync</span>
                                <span v-else class="material-icons text-xl">delete_outline</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Empty State -->
        <div v-else class="text-center py-16">
            <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-gray-100 mb-4">
                <span class="material-icons text-5xl text-gray-400">image</span>
            </div>
            <h3 class="text-lg font-medium text-gray-900 mb-1">No images yet</h3>
            <p class="text-sm text-gray-500">Upload your first image to get started</p>
        </div>

        <!-- Edit Image Modal -->
        <div v-if="showEditModal" class="fixed inset-0 z-50 overflow-y-auto" @keydown.escape="closeEditModal">
            <!-- Background overlay with blur -->
            <div class="fixed inset-0 bg-black/50 backdrop-blur-sm transition-opacity" @click="closeEditModal"></div>

            <!-- Modal container -->
            <div class="flex items-center justify-center min-h-screen p-4">
                <!-- Modal panel -->
                <div class="relative bg-white rounded-2xl shadow-2xl transform transition-all w-full max-w-2xl">
                    <!-- Header -->
                    <div class="relative">
                        <!-- Close button -->
                        <button
                            @click="closeEditModal"
                            type="button"
                            class="absolute top-4 right-4 flex items-center justify-center w-10 h-10 rounded-full text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-all z-10"
                        >
                            <span class="material-icons">close</span>
                        </button>

                        <!-- Image Preview -->
                        <div class="relative h-64 bg-gradient-to-br from-gray-100 to-gray-200 rounded-t-2xl overflow-hidden">
                            <img
                                :src="getImageUrl(editingImage)"
                                :alt="editingImage?.display_name"
                                class="w-full h-full object-cover"
                            />
                            <!-- Primary badge on image -->
                            <div
                                v-if="editingImage?.is_primary"
                                class="absolute top-4 left-4 inline-flex items-center gap-1 bg-amber-500 text-white text-xs font-medium px-3 py-1.5 rounded-full shadow-lg"
                            >
                                <span class="material-icons text-sm">star</span>
                                <span>Primary Image</span>
                            </div>
                        </div>
                    </div>

                    <!-- Content -->
                    <div class="p-6 space-y-6">
                        <!-- Title -->
                        <div>
                            <h2 class="text-2xl font-semibold text-gray-900 flex items-center gap-2">
                                <span class="material-icons text-gray-600">edit</span>
                                Edit Image Details
                            </h2>
                            <p class="mt-1 text-sm text-gray-500">Update the information for this image</p>
                        </div>

                        <!-- Form Fields -->
                        <div class="space-y-5">
                            <!-- Image Name -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <span class="flex items-center gap-1">
                                        Image Name
                                    </span>
                                </label>
                                <input
                                    v-model="editForm.display_name"
                                    type="text"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                    placeholder="Enter a descriptive name"
                                />
                            </div>

                            <!-- Description -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <span class="flex items-center gap-1">
                                        Description
                                        <span class="text-gray-400 font-normal">(optional)</span>
                                    </span>
                                </label>
                                <textarea
                                    v-model="editForm.description"
                                    rows="3"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all resize-none"
                                    placeholder="Add details about this image..."
                                ></textarea>
                            </div>

                            <!-- Primary Image Toggle -->
                            <div class="bg-gray-50 rounded-xl p-4 border border-gray-200">
                                <div class="flex items-start gap-3">
                                    <div class="flex items-center h-6">
                                        <input
                                            v-model="editForm.is_primary"
                                            type="checkbox"
                                            :disabled="editingImage?.is_primary"
                                            class="h-5 w-5 text-blue-600 focus:ring-blue-500 border-gray-300 rounded transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                                        />
                                    </div>
                                    <div class="flex-1">
                                        <label class="block text-sm font-medium text-gray-900 cursor-pointer">
                                            Set as primary image
                                        </label>
                                        <p class="mt-1 text-xs text-gray-500">
                                            The primary image will be displayed as the main thumbnail
                                        </p>
                                    </div>
                                </div>
                                
                                <div v-if="editingImage?.is_primary" class="mt-3 flex items-start gap-2 text-xs text-amber-700 bg-amber-50 px-3 py-2 rounded-lg border border-amber-200">
                                    <span class="material-icons text-sm">info</span>
                                    <span>This image is currently the primary. To change it, set another image as primary instead.</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer Actions -->
                    <div class="bg-gray-50 px-6 py-4 rounded-b-2xl flex items-center justify-end gap-3">
                        <button
                            @click="closeEditModal"
                            type="button"
                            class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-all"
                        >
                            <span class="material-icons text-sm">close</span>
                            Cancel
                        </button>
                        <button
                            @click="updateImage"
                            :disabled="isUpdatingImage"
                            type="button"
                            class="inline-flex items-center gap-2 px-6 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all disabled:opacity-50 disabled:cursor-not-allowed shadow-sm hover:shadow-md"
                        >
                            <span v-if="isUpdatingImage" class="material-icons text-sm animate-spin">sync</span>
                            <span v-else class="material-icons text-sm">check</span>
                            {{ isUpdatingImage ? 'Saving...' : 'Save Changes' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch, onUnmounted } from 'vue';
import axios from 'axios';
import Sortable from 'sortablejs';

const props = defineProps({
    parentId: {
        type: Number,
        required: true
    },
    parentType: {
        type: String,
        required: true
    },
    domain: {
        type: String,
        required: true
    },
    modelRelationship: {
        type: String,
        required: true
    }
});

const images = ref([]);
const isDragging = ref(false);
const uploading = ref(false);
const uploadProgress = ref(0);
const uploadError = ref('');
const fileInput = ref(null);
const imageGrid = ref(null);
let sortableInstance = null;

// Edit modal state
const showEditModal = ref(false);
const editingImage = ref(null);
const editForm = ref({
    display_name: '',
    description: '',
    is_primary: false
});
const isUpdatingImage = ref(false);

// Primary image update loading
const settingPrimaryImageId = ref(null);

// Delete image loading
const deletingImageId = ref(null);

// Reordering loading
const isReordering = ref(false);

const sortedImages = computed(() => {
    return [...images.value].sort((a, b) => a.sort_order - b.sort_order);
});

const getImageUrl = (image) => {
    // The model provides a 'url' accessor that handles CDN/S3 signed URLs
    return image.url || '';
};

const fetchImages = async () => {
    try {
        const response = await axios.get(route('inventoryimages.index'), {
            params: {
                filters: JSON.stringify([
                    { field: 'imageable_type', operator: 'equals', value: `App\\Domain\\${props.parentType}\\Models\\${props.parentType}` },
                    { field: 'imageable_id', operator: 'equals', value: props.parentId }
                ]),
                per_page: 100 // Get all images for this record
            },
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        });
        
        images.value = response.data.records || [];

        // Reinitialize sortable after images are loaded
        setTimeout(() => {
            initializeSortable();
        }, 100);
    } catch (error) {
        console.error('Error fetching images:', error);
        if (error.response) {
            console.error('Response:', error.response.data);
        }
    }
};

const handleFileSelect = (event) => {
    const files = Array.from(event.target.files);
    uploadFiles(files);
};

const handleDrop = (event) => {
    isDragging.value = false;
    const files = Array.from(event.dataTransfer.files).filter(file => 
        file.type.startsWith('image/')
    );
    uploadFiles(files);
};

const uploadFiles = async (files) => {
    if (files.length === 0) return;

    uploading.value = true;
    uploadProgress.value = 0;
    uploadError.value = ''; // Clear any previous errors

    const totalFiles = files.length;
    let completedFiles = 0;
    let hasErrors = false;

    for (const file of files) {
        try {
            const formData = new FormData();
            formData.append('file', file);
            formData.append('imageable_type', `App\\Domain\\${props.parentType}\\Models\\${props.parentType}`);
            formData.append('imageable_id', props.parentId);
            formData.append('display_name', file.name);
            formData.append('sort_order', images.value.length + completedFiles);

            await axios.post(route('inventoryimages.store'), formData, {
                headers: {
                    'Content-Type': 'multipart/form-data',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });

            completedFiles++;
            uploadProgress.value = Math.round((completedFiles / totalFiles) * 100);
        } catch (error) {
            hasErrors = true;
            console.error('Error uploading file:', error);

            let errorMessage = 'Upload failed. Please try again.';

            if (error.response) {
                console.error('Response data:', error.response.data);
                console.error('Response status:', error.response.status);

                // Handle specific validation errors
                if (error.response.data && error.response.data.message) {
                    const message = error.response.data.message;

                    if (message.includes('must not be greater than')) {
                        errorMessage = `File "${file.name}" is too large. Maximum size is 50MB.`;
                    } else if (message.includes('must be an image')) {
                        errorMessage = `File "${file.name}" is not a valid image file.`;
                    } else if (message.includes('required')) {
                        errorMessage = `File "${file.name}" could not be processed.`;
                    } else {
                        errorMessage = `Upload failed for "${file.name}": ${message}`;
                    }
                }
            }

            // Show the first error encountered
            if (!uploadError.value) {
                uploadError.value = errorMessage;
            }
        }
    }

    uploading.value = false;
    uploadProgress.value = 0;

    // Refresh images if any uploads succeeded
    if (completedFiles > 0) {
        await fetchImages();
    }

    // Clear file input
    if (fileInput.value) {
        fileInput.value.value = '';
    }

    // Clear error after 5 seconds if there were successful uploads
    if (hasErrors && completedFiles > 0) {
        setTimeout(() => {
            uploadError.value = '';
        }, 5000);
    }
};

const setPrimary = async (image) => {
    if (settingPrimaryImageId.value) return; // Prevent multiple simultaneous requests

    settingPrimaryImageId.value = image.id;

    try {
        await axios.patch(route('inventoryimages.update', image.id), {
            is_primary: true
        });

        await fetchImages();
    } catch (error) {
        console.error('Error setting primary image:', error);
    } finally {
        settingPrimaryImageId.value = null;
    }
};

const deleteImage = async (image) => {
    if (!confirm('Are you sure you want to delete this image?')) {
        return;
    }

    if (deletingImageId.value) return; // Prevent multiple simultaneous deletions

    deletingImageId.value = image.id;

    try {
        await axios.delete(route('inventoryimages.destroy', image.id));
        await fetchImages();
    } catch (error) {
        console.error('Error deleting image:', error);
    } finally {
        deletingImageId.value = null;
    }
};

const openEditModal = (image) => {
    editingImage.value = image;
    editForm.value = {
        display_name: image.display_name || '',
        description: image.description || '',
        is_primary: image.is_primary || false
    };
    showEditModal.value = true;
};

const closeEditModal = () => {
    showEditModal.value = false;
    editingImage.value = null;
    editForm.value = {
        display_name: '',
        description: '',
        is_primary: false
    };
};

const updateImage = async () => {
    if (!editingImage.value) return;

    isUpdatingImage.value = true;

    try {
        await axios.patch(route('inventoryimages.update', editingImage.value.id), editForm.value, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        });

        await fetchImages();
        closeEditModal();
    } catch (error) {
        console.error('Error updating image:', error);
        // Could add error handling here
    } finally {
        isUpdatingImage.value = false;
    }
};

const initializeSortable = () => {
    if (!imageGrid.value || images.value.length === 0) return;

    // Destroy existing instance if it exists
    if (sortableInstance) {
        sortableInstance.destroy();
        sortableInstance = null;
    }

    sortableInstance = new Sortable(imageGrid.value, {
        animation: 200,
        easing: 'cubic-bezier(0.4, 0, 0.2, 1)',
        handle: '.drag-handle',
        ghostClass: 'sortable-ghost',
        chosenClass: 'sortable-chosen',
        dragClass: 'sortable-drag',
        forceFallback: true,
        onStart: (evt) => {
            // Add visual feedback when dragging starts
            evt.item.style.opacity = '0.8';
        },
        onEnd: async (evt) => {
            // Reset visual feedback
            evt.item.style.opacity = '1';

            if (isReordering.value) return; // Prevent multiple simultaneous reorder operations

            isReordering.value = true;

            const imageId = parseInt(evt.item.getAttribute('data-id'));
            const newIndex = evt.newIndex;

            // Update sort order for all images based on new DOM order
            const updates = [];
            const sortedImageElements = Array.from(imageGrid.value.children);

            for (let i = 0; i < sortedImageElements.length; i++) {
                const id = parseInt(sortedImageElements[i].getAttribute('data-id'));
                updates.push(
                    axios.patch(route('inventoryimages.update', id), {
                        sort_order: i
                    })
                );
            }

            try {
                await Promise.all(updates);
                // Don't refetch images here as it will cause sortable to reinitialize again
                // The sort_order updates should be sufficient
            } catch (error) {
                console.error('Error updating sort order:', error);
                // Refetch on error to reset the UI
                await fetchImages();
            } finally {
                isReordering.value = false;
            }
        }
    });
};

onMounted(async () => {
    await fetchImages();
    // initializeSortable is called in fetchImages after images are loaded
});

// Watch for images length changes to reinitialize sortable
watch(() => images.value.length, () => {
    setTimeout(() => {
        initializeSortable();
    }, 100);
});

onUnmounted(() => {
    if (sortableInstance) {
        sortableInstance.destroy();
    }
});
</script>

<style scoped>
/* Sortable.js visual feedback styles */
.sortable-ghost {
    opacity: 0.5 !important;
    background: rgba(59, 130, 246, 0.2) !important;
    border: 2px dashed rgb(59, 130, 246) !important;
    border-radius: 0.75rem !important;
}

.sortable-ghost * {
    opacity: 0.5 !important;
}

.sortable-chosen {
    opacity: 1;
}

.sortable-drag {
    opacity: 0.8 !important;
    transform: rotate(3deg) scale(1.05);
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3), 0 10px 10px -5px rgba(0, 0, 0, 0.2);
    border-radius: 0.75rem;
}

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
</style>