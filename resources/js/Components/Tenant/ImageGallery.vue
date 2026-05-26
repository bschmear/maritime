<template>
    <div
        class="relative"
        @drop.prevent="handleDrop"
        @dragover.prevent="onDragOver"
        @dragleave.prevent="onDragLeave"
    >
        <!-- Drag Overlay — only shown when dragging over the component -->
        <Transition name="fade">
            <div
                v-if="isDragging"
                class="absolute inset-0 z-30 flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-primary-500 bg-primary-50/90 dark:bg-primary-900/80 backdrop-blur-sm"
            >
                <span class="material-icons text-5xl text-primary-500 mb-3">cloud_upload</span>
                <p class="text-base font-semibold text-primary-700 dark:text-primary-300">Drop images here</p>
            </div>
        </Transition>

        <!-- Main Block -->
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-2xl shadow-sm overflow-hidden">

            <!-- Header Bar -->
            <div class="border-b border-gray-200 px-4 py-4 dark:border-gray-700 sm:px-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                    <div class="flex min-w-0 items-center gap-2">
                        <span class="material-icons shrink-0 text-gray-500 dark:text-gray-400">collections</span>
                        <div>
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Images</h3>
                            <p
                                v-if="showCustomerVisibility"
                                class="mt-0.5 text-xs text-gray-500 dark:text-gray-400"
                            >
                                Mark photos as customer-visible to show them in the portal; others stay internal.
                            </p>
                        </div>
                        <span
                            v-if="images.length > 0"
                            class="inline-flex shrink-0 items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-sm font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-300"
                        >
                            {{ images.length }}
                        </span>
                    </div>

                    <div class="flex w-full min-w-0 flex-col gap-3 sm:w-auto sm:items-end">
                        <label
                            v-if="showAlsoAttachToTicket"
                            class="flex w-full cursor-pointer select-none items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm text-gray-600 dark:border-gray-600 dark:bg-gray-800/60 dark:text-gray-300 sm:w-auto sm:border-0 sm:bg-transparent sm:px-0 sm:py-0"
                        >
                            <input
                                v-model="alsoAttachToServiceTicket"
                                type="checkbox"
                                class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                            />
                            <span>Also add to service ticket</span>
                        </label>

                        <label
                            v-if="showCustomerVisibility"
                            class="flex w-full cursor-pointer select-none items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm text-gray-600 dark:border-gray-600 dark:bg-gray-800/60 dark:text-gray-300 sm:w-auto sm:border-0 sm:bg-transparent sm:px-0 sm:py-0"
                        >
                            <input
                                v-model="visibleToCustomerOnUpload"
                                type="checkbox"
                                class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                            />
                            <span>Visible to customer on upload</span>
                        </label>

                        <p
                            v-if="images.length > 0"
                            class="flex items-center justify-center gap-1 text-xs text-gray-500 dark:text-gray-400 sm:justify-end sm:text-sm"
                        >
                            <span class="material-icons text-base">drag_indicator</span>
                            Drag to reorder
                        </p>

                        <div
                            class="grid w-full gap-2 sm:flex sm:w-auto sm:items-center sm:gap-3"
                            :class="showCameraCapture ? 'grid-cols-2' : 'grid-cols-1'"
                        >
                            <button
                                v-if="showCameraCapture"
                                type="button"
                                :disabled="uploading"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700 sm:w-auto sm:px-4 sm:py-2"
                                @click="openCamera"
                            >
                                <span class="material-icons text-base">photo_camera</span>
                                <span class="truncate">Take Picture</span>
                            </button>
                            <button
                                type="button"
                                :disabled="uploading"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-primary-600 px-3 py-2.5 text-sm font-medium text-white shadow-sm transition-colors hover:bg-primary-700 hover:shadow-md disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto sm:px-4 sm:py-2"
                                @click="openFilePicker"
                            >
                                <span class="material-icons text-base">add_photo_alternate</span>
                                <span class="truncate">Upload</span>
                            </button>
                        </div>
                    </div>
                </div>

                <input
                    ref="fileInput"
                    type="file"
                    multiple
                    accept="image/*"
                    class="hidden"
                    @change="handleFileSelect"
                />
                <input
                    v-if="showCameraCapture"
                    ref="cameraInput"
                    type="file"
                    accept="image/*"
                    capture="environment"
                    class="hidden"
                    @change="handleCameraCapture"
                />
            </div>

            <!-- Upload Progress -->
            <Transition name="slide-down">
                <div v-if="uploading" class="px-5 py-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                    <div class="flex items-center gap-3 mb-2">
                        <span class="material-icons text-primary-600 dark:text-primary-400 animate-spin text-base">sync</span>
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Uploading images...</span>
                        <span class="ml-auto text-sm font-semibold text-gray-900 dark:text-white">{{ uploadProgress }}%</span>
                    </div>
                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5 overflow-hidden">
                        <div
                            class="bg-primary-600 h-1.5 rounded-full transition-all duration-300 ease-out"
                            :style="{ width: uploadProgress + '%' }"
                        ></div>
                    </div>
                </div>
            </Transition>

            <!-- Upload Error -->
            <Transition name="slide-down">
                <div v-if="uploadError" class="mx-5 mt-4 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded-xl p-4">
                    <div class="flex items-center gap-3">
                        <span class="material-icons text-red-600 dark:text-red-400">error_outline</span>
                        <div class="flex-1">
                            <p class="text-sm font-semibold text-red-800 dark:text-red-300">Upload Failed</p>
                            <p class="text-sm text-red-700 dark:text-red-400">{{ uploadError }}</p>
                        </div>
                        <button @click="uploadError = ''" class="text-red-500 dark:text-red-400 hover:text-red-700 dark:hover:text-red-200">
                            <span class="material-icons text-base">close</span>
                        </button>
                    </div>
                </div>
            </Transition>

            <!-- Image Grid -->
            <div v-if="images.length > 0" class="p-5">
                <div
                    ref="imageGrid"
                    class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4 relative"
                    :class="{ 'pointer-events-none opacity-75': isReordering }"
                >
                    <!-- Reordering overlay -->
                    <div v-if="isReordering" class="absolute inset-0 flex items-center justify-center bg-white/80 dark:bg-gray-900/80 backdrop-blur-sm rounded-xl z-20">
                        <div class="flex items-center gap-2 bg-white dark:bg-gray-800 px-4 py-2.5 rounded-xl shadow-lg">
                            <span class="material-icons animate-spin text-primary-600 dark:text-primary-400">sync</span>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Saving order...</span>
                        </div>
                    </div>

                    <div
                        v-for="image in sortedImages"
                        :key="image.id"
                        :data-id="image.id"
                        class="relative group bg-gray-100 dark:bg-gray-800 rounded-xl overflow-hidden aspect-square shadow-sm hover:shadow-md transition-all duration-200"
                    >
                        <img
                            :src="getImageUrl(image)"
                            :alt="image.display_name"
                            class="w-full h-full object-cover"
                            loading="lazy"
                        />

                        <!-- Primary Badge -->
                        <div
                            v-if="image.is_primary"
                            class="absolute top-2.5 left-2.5 inline-flex items-center gap-1 bg-amber-500 text-white text-sm font-medium px-2 py-0.5 rounded-full shadow-lg z-10"
                        >
                            <span class="material-icons text-sm">star</span>
                            <span>Primary</span>
                        </div>

                        <!-- Customer visibility badge -->
                        <div
                            v-if="showCustomerVisibility"
                            class="absolute bottom-2.5 left-2.5 z-10 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide shadow-lg"
                            :class="image.visible_to_customer
                                ? 'bg-green-600 text-white'
                                : 'bg-gray-800/90 text-gray-100'"
                        >
                            <span class="material-icons text-xs">{{ image.visible_to_customer ? 'visibility' : 'lock' }}</span>
                            <span>{{ image.visible_to_customer ? 'Customer' : 'Internal' }}</span>
                        </div>

                        <!-- Drag Handle -->
                        <div class="drag-handle absolute top-2.5 right-2.5 flex items-center justify-center w-8 h-8 bg-white/95 dark:bg-gray-900/90 backdrop-blur-sm rounded-full cursor-move shadow-lg hover:scale-110 transition-all duration-200 z-10 opacity-0 group-hover:opacity-100">
                            <span class="material-icons text-gray-700 dark:text-gray-300 text-base">drag_indicator</span>
                        </div>

                        <!-- Hover Overlay -->
                        <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/0 to-black/20 opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none">
                            <div class="absolute bottom-2.5 left-2.5 right-2.5 flex items-center justify-center gap-2 pointer-events-auto">
                                <button
                                    v-if="showCustomerVisibility"
                                    @click.stop="toggleCustomerVisibility(image)"
                                    :disabled="togglingVisibilityId === image.id"
                                    type="button"
                                    :title="image.visible_to_customer ? 'Mark internal only' : 'Visible to customer'"
                                    class="flex items-center justify-center w-9 h-9 bg-white/95 dark:bg-gray-800/95 backdrop-blur-sm rounded-full hover:bg-white hover:scale-110 transition-all duration-200 shadow-lg disabled:opacity-50 disabled:cursor-not-allowed"
                                    :class="image.visible_to_customer ? 'text-green-600 dark:text-green-400' : 'text-gray-600 dark:text-gray-300'"
                                >
                                    <span v-if="togglingVisibilityId === image.id" class="material-icons animate-spin text-base">sync</span>
                                    <span v-else class="material-icons text-base">{{ image.visible_to_customer ? 'visibility' : 'visibility_off' }}</span>
                                </button>
                                <button
                                    v-if="!image.is_primary"
                                    @click.stop="setPrimary(image)"
                                    :disabled="settingPrimaryImageId === image.id"
                                    type="button"
                                    title="Set as Primary"
                                    class="flex items-center justify-center w-9 h-9 bg-white/95 dark:bg-gray-800/95 backdrop-blur-sm text-amber-600 dark:text-amber-400 rounded-full hover:bg-white hover:scale-110 transition-all duration-200 shadow-lg disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    <span v-if="settingPrimaryImageId === image.id" class="material-icons animate-spin text-base">sync</span>
                                    <span v-else class="material-icons text-base">star_border</span>
                                </button>
                                <button
                                    @click.stop="openEditModal(image)"
                                    type="button"
                                    title="Edit Image"
                                    class="flex items-center justify-center w-9 h-9 bg-white/95 dark:bg-gray-800/95 backdrop-blur-sm text-blue-600 dark:text-blue-400 rounded-full hover:bg-white hover:scale-110 transition-all duration-200 shadow-lg"
                                >
                                    <span class="material-icons text-base">edit</span>
                                </button>
                                <button
                                    @click.stop="deleteImage(image)"
                                    :disabled="deletingImageId === image.id"
                                    type="button"
                                    title="Delete Image"
                                    class="flex items-center justify-center w-9 h-9 bg-white/95 dark:bg-gray-800/95 backdrop-blur-sm text-red-600 dark:text-red-400 rounded-full hover:bg-white hover:scale-110 transition-all duration-200 shadow-lg disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    <span v-if="deletingImageId === image.id" class="material-icons animate-spin text-base">sync</span>
                                    <span v-else class="material-icons text-base">delete_outline</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else class="flex flex-col items-center justify-center py-16 px-5 text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 dark:bg-gray-800 mb-4">
                    <span class="material-icons text-4xl text-gray-400 dark:text-gray-500">image</span>
                </div>
                <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-1">No images yet</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-5">{{ emptyStateHint }}</p>
                <div class="flex flex-wrap items-center justify-center gap-3">
                    <button
                        v-if="showCameraCapture"
                        type="button"
                        :disabled="uploading"
                        @click="openCamera"
                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors shadow-sm disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <span class="material-icons text-base">photo_camera</span>
                        Take Picture
                    </button>
                    <button
                        type="button"
                        :disabled="uploading"
                        @click="openFilePicker"
                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary-600 text-white text-sm font-medium rounded-lg hover:bg-primary-700 transition-colors shadow-sm disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <span class="material-icons text-base">add_photo_alternate</span>
                        Browse Files
                    </button>
                </div>
                <p class="text-sm text-gray-400 dark:text-gray-500 mt-3">
                    Images are automatically resized to max 2000px
                </p>
            </div>

        </div>

        <!-- Edit Image Modal -->
        <Transition name="modal">
            <div v-if="showEditModal" class="fixed inset-0 z-50 overflow-y-auto" @keydown.escape="closeEditModal">
                <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" @click="closeEditModal"></div>
                <div class="flex items-center justify-center min-h-screen p-4">
                    <div class="relative bg-white dark:bg-gray-900 rounded-2xl shadow-2xl w-full max-w-2xl">
                        <!-- Header -->
                        <div class="relative">
                            <button
                                @click="closeEditModal"
                                type="button"
                                class="absolute top-4 right-4 flex items-center justify-center w-9 h-9 rounded-full text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-all z-10"
                            >
                                <span class="material-icons">close</span>
                            </button>
                            <div class="relative h-64 bg-gray-100 dark:bg-gray-800 rounded-t-2xl overflow-hidden">
                                <img
                                    :src="getImageUrl(editingImage)"
                                    :alt="editingImage?.display_name"
                                    class="w-full h-full object-cover"
                                />
                                <div
                                    v-if="editingImage?.is_primary"
                                    class="absolute top-4 left-4 inline-flex items-center gap-1 bg-amber-500 text-white text-sm font-medium px-3 py-1.5 rounded-full shadow-lg"
                                >
                                    <span class="material-icons text-sm">star</span>
                                    <span>Primary Image</span>
                                </div>
                            </div>
                        </div>

                        <!-- Content -->
                        <div class="p-6 space-y-5">
                            <div>
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                                    <span class="material-icons text-gray-500 dark:text-gray-400">edit</span>
                                    Edit Image Details
                                </h2>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Update the information for this image</p>
                            </div>

                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Image Name</label>
                                    <input
                                        v-model="editForm.display_name"
                                        type="text"
                                        class="w-full px-4 py-3 text-base border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all placeholder:text-gray-400 dark:placeholder:text-gray-500"
                                        placeholder="Enter a descriptive name"
                                    />
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Description
                                        <span class="text-gray-400 dark:text-gray-500 font-normal">(optional)</span>
                                    </label>
                                    <textarea
                                        v-model="editForm.description"
                                        rows="3"
                                        class="w-full px-4 py-3 text-base border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all resize-none placeholder:text-gray-400 dark:placeholder:text-gray-500"
                                        placeholder="Add details about this image..."
                                    ></textarea>
                                </div>

                                <div
                                    v-if="showCustomerVisibility"
                                    class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800"
                                >
                                    <label class="flex cursor-pointer items-start gap-3">
                                        <input
                                            v-model="editForm.visible_to_customer"
                                            type="checkbox"
                                            class="mt-0.5 h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                        />
                                        <span>
                                            <span class="block text-sm font-medium text-gray-900 dark:text-white">Visible to customer</span>
                                            <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">
                                                When enabled, this photo appears in the customer portal and approval link.
                                            </span>
                                        </span>
                                    </label>
                                </div>

                                <div class="bg-gray-50 dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700">
                                    <div class="flex items-start gap-3">
                                        <input
                                            v-model="editForm.is_primary"
                                            type="checkbox"
                                            :disabled="editingImage?.is_primary"
                                            class="mt-0.5 h-5 w-5 text-blue-600 focus:ring-blue-500 border-gray-300 dark:border-gray-600 rounded transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                                        />
                                        <div>
                                            <label class="block text-sm font-medium text-gray-900 dark:text-white">Set as primary image</label>
                                            <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">The primary image will be displayed as the main thumbnail</p>
                                        </div>
                                    </div>
                                    <div v-if="editingImage?.is_primary" class="mt-3 flex items-start gap-2 text-sm text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/30 px-3 py-2 rounded-lg border border-amber-200 dark:border-amber-800">
                                        <span class="material-icons text-base shrink-0">info</span>
                                        <span>This image is currently the primary. To change it, set another image as primary instead.</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="bg-gray-50 dark:bg-gray-800 px-6 py-4 rounded-b-2xl flex items-center justify-end gap-3 border-t border-gray-200 dark:border-gray-700">
                            <button
                                @click="closeEditModal"
                                type="button"
                                class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-all"
                            >
                                <span class="material-icons text-base">close</span>
                                Cancel
                            </button>
                            <button
                                @click="updateImage"
                                :disabled="isUpdatingImage"
                                type="button"
                                class="inline-flex items-center gap-2 px-6 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all disabled:opacity-50 disabled:cursor-not-allowed shadow-sm"
                            >
                                <span v-if="isUpdatingImage" class="material-icons text-base animate-spin">sync</span>
                                <span v-else class="material-icons text-base">check</span>
                                {{ isUpdatingImage ? 'Saving...' : 'Save Changes' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch, onUnmounted } from 'vue';
import axios from 'axios';
import Sortable from 'sortablejs';

const props = defineProps({
    parentId: { type: Number, required: true },
    parentType: { type: String, required: true },
    domain: { type: String, required: true },
    modelRelationship: { type: String, required: true },
    serviceTicketId: { type: Number, default: null },
});

const images = ref([]);
const isDragging = ref(false);
const uploading = ref(false);
const uploadProgress = ref(0);
const uploadError = ref('');
const fileInput = ref(null);
const cameraInput = ref(null);
const imageGrid = ref(null);
let sortableInstance = null;
let dragLeaveTimer = null;

const showEditModal = ref(false);
const editingImage = ref(null);
const editForm = ref({ display_name: '', description: '', is_primary: false, visible_to_customer: false });
const isUpdatingImage = ref(false);
const settingPrimaryImageId = ref(null);
const deletingImageId = ref(null);
const togglingVisibilityId = ref(null);
const isReordering = ref(false);
const alsoAttachToServiceTicket = ref(false);
const visibleToCustomerOnUpload = ref(false);
const showCameraCapture = ref(false);

let cameraCaptureMediaQuery = null;

/** Native camera capture (`capture` attribute) works on phones/tablets, not desktop file pickers. */
const detectCameraCaptureDevice = () => {
    if (typeof window === 'undefined') {
        return false;
    }
    if (window.matchMedia('(pointer: coarse) and (hover: none)').matches) {
        return true;
    }
    if (/Android|iPhone|iPad|iPod|Mobile|Tablet/i.test(navigator.userAgent)) {
        return true;
    }
    if (navigator.maxTouchPoints > 0 && window.matchMedia('(max-width: 1024px)').matches) {
        return true;
    }
    return false;
};

const syncCameraCaptureVisibility = () => {
    showCameraCapture.value = detectCameraCaptureDevice();
};

const attachableFqcn = computed(() => `App\\Domain\\${props.parentType}\\Models\\${props.parentType}`);

const useAttachmentLinks = computed(() =>
    ['ServiceTicket', 'WorkOrder', 'WarrantyClaim'].includes(props.parentType),
);

const showAlsoAttachToTicket = computed(
    () =>
        props.parentType === 'WorkOrder' &&
        props.serviceTicketId != null &&
        Number(props.serviceTicketId) > 0,
);

const showCustomerVisibility = computed(() => props.parentType === 'ServiceTicket');

const sortedImages = computed(() => {
    return [...images.value].sort((a, b) => a.sort_order - b.sort_order);
});

const emptyStateHint = computed(() =>
    showCameraCapture.value
        ? 'Take a photo, upload files, or drag and drop here'
        : 'Upload files or drag and drop here',
);

const linkContextParams = () => ({
    attachable_type: attachableFqcn.value,
    attachable_id: props.parentId,
});

const getImageUrl = (image) => image?.url || '';

const onDragOver = () => {
    clearTimeout(dragLeaveTimer);
    isDragging.value = true;
};

const onDragLeave = () => {
    // Debounce to avoid flickering when moving between child elements
    dragLeaveTimer = setTimeout(() => {
        isDragging.value = false;
    }, 50);
};

const fetchImages = async () => {
    try {
        const params = useAttachmentLinks.value
            ? {
                link_parent_type: attachableFqcn.value,
                link_parent_id: props.parentId,
                per_page: 100,
            }
            : {
                filters: JSON.stringify([
                    { field: 'imageable_type', operator: 'equals', value: attachableFqcn.value },
                    { field: 'imageable_id', operator: 'equals', value: props.parentId },
                ]),
                per_page: 100,
            };

        const response = await axios.get(route('inventoryimages.index'), {
            params,
            headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
        });
        images.value = response.data.records || [];
        setTimeout(() => {
            initializeSortable();
        }, 100);
    } catch (error) {
        console.error('Error fetching images:', error);
    }
};

const openFilePicker = () => {
    fileInput.value?.click();
};

const openCamera = () => {
    cameraInput.value?.click();
};

const handleFileSelect = (event) => {
    uploadFiles(Array.from(event.target.files));
    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

const handleCameraCapture = (event) => {
    const files = Array.from(event.target.files || []).filter((f) => f.type.startsWith('image/'));
    if (files.length) {
        uploadFiles(files);
    }
    if (cameraInput.value) {
        cameraInput.value.value = '';
    }
};

const handleDrop = (event) => {
    isDragging.value = false;
    const files = Array.from(event.dataTransfer.files).filter(f => f.type.startsWith('image/'));
    uploadFiles(files);
};

const uploadFiles = async (files) => {
    if (!files.length) return;
    uploading.value = true;
    uploadProgress.value = 0;
    uploadError.value = '';

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
            if (props.parentType === 'WorkOrder' && alsoAttachToServiceTicket.value) {
                formData.append('also_attach_to_service_ticket', '1');
            }
            if (showCustomerVisibility.value && visibleToCustomerOnUpload.value) {
                formData.append('visible_to_customer', '1');
            }

            await axios.post(route('inventoryimages.store'), formData, {
                headers: { 'Content-Type': 'multipart/form-data', 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
            });
            completedFiles++;
            uploadProgress.value = Math.round((completedFiles / totalFiles) * 100);
        } catch (error) {
            hasErrors = true;
            let errorMessage = 'Upload failed. Please try again.';
            if (error.response?.data?.message) {
                const msg = error.response.data.message;
                if (msg.includes('must not be greater than')) errorMessage = `"${file.name}" is too large. Maximum size is 50MB.`;
                else if (msg.includes('must be an image')) errorMessage = `"${file.name}" is not a valid image file.`;
                else errorMessage = `Upload failed for "${file.name}": ${msg}`;
            }
            if (!uploadError.value) uploadError.value = errorMessage;
        }
    }

    uploading.value = false;
    uploadProgress.value = 0;
    if (completedFiles > 0) await fetchImages();
    if (fileInput.value) fileInput.value.value = '';
    if (hasErrors && completedFiles > 0) setTimeout(() => { uploadError.value = ''; }, 5000);
};

const setPrimary = async (image) => {
    if (settingPrimaryImageId.value) return;
    settingPrimaryImageId.value = image.id;
    try {
        const body = useAttachmentLinks.value
            ? { is_primary: true, ...linkContextParams() }
            : { is_primary: true };
        await axios.patch(route('inventoryimages.update', image.id), body);
        await fetchImages();
    } catch (error) {
        console.error('Error setting primary image:', error);
    } finally {
        settingPrimaryImageId.value = null;
    }
};

const deleteImage = async (image) => {
    if (!confirm('Are you sure you want to delete this image?')) return;
    if (deletingImageId.value) return;
    deletingImageId.value = image.id;
    try {
        const config = useAttachmentLinks.value
            ? { params: linkContextParams() }
            : {};
        await axios.delete(route('inventoryimages.destroy', image.id), config);
        await fetchImages();
    } catch (error) {
        console.error('Error deleting image:', error);
    } finally {
        deletingImageId.value = null;
    }
};

const toggleCustomerVisibility = async (image) => {
    if (!showCustomerVisibility.value || togglingVisibilityId.value) {
        return;
    }
    togglingVisibilityId.value = image.id;
    try {
        const body = {
            visible_to_customer: !image.visible_to_customer,
            ...linkContextParams(),
        };
        await axios.patch(route('inventoryimages.update', image.id), body, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
        });
        await fetchImages();
    } catch (error) {
        console.error('Error updating image visibility:', error);
    } finally {
        togglingVisibilityId.value = null;
    }
};

const openEditModal = (image) => {
    editingImage.value = image;
    editForm.value = {
        display_name: image.display_name || '',
        description: image.description || '',
        is_primary: image.is_primary || false,
        visible_to_customer: Boolean(image.visible_to_customer),
    };
    showEditModal.value = true;
};

const closeEditModal = () => {
    showEditModal.value = false;
    editingImage.value = null;
    editForm.value = { display_name: '', description: '', is_primary: false, visible_to_customer: false };
};

const updateImage = async () => {
    if (!editingImage.value) return;
    isUpdatingImage.value = true;
    try {
        const body = useAttachmentLinks.value
            ? { ...editForm.value, ...linkContextParams() }
            : editForm.value;
        await axios.patch(route('inventoryimages.update', editingImage.value.id), body, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
        });
        await fetchImages();
        closeEditModal();
    } catch (error) {
        console.error('Error updating image:', error);
    } finally {
        isUpdatingImage.value = false;
    }
};

const initializeSortable = () => {
    if (!imageGrid.value || images.value.length === 0) return;
    if (sortableInstance) { sortableInstance.destroy(); sortableInstance = null; }

    sortableInstance = new Sortable(imageGrid.value, {
        animation: 200,
        easing: 'cubic-bezier(0.4, 0, 0.2, 1)',
        handle: '.drag-handle',
        ghostClass: 'sortable-ghost',
        chosenClass: 'sortable-chosen',
        dragClass: 'sortable-drag',
        forceFallback: true,
        onStart: (evt) => { evt.item.style.opacity = '0.8'; },
        onEnd: async (evt) => {
            evt.item.style.opacity = '1';
            if (isReordering.value) return;
            isReordering.value = true;
            const sortedImageElements = Array.from(imageGrid.value.children);
            const updates = sortedImageElements.map((el, i) => {
                const inventoryImageId = parseInt(el.getAttribute('data-id'), 10);
                const payload = useAttachmentLinks.value
                    ? { sort_order: i, ...linkContextParams() }
                    : { sort_order: i };
                return axios.patch(route('inventoryimages.update', inventoryImageId), payload);
            });
            try {
                await Promise.all(updates);
            } catch (error) {
                console.error('Error updating sort order:', error);
                await fetchImages();
            } finally {
                isReordering.value = false;
            }
        }
    });
};

onMounted(async () => {
    syncCameraCaptureVisibility();
    cameraCaptureMediaQuery = window.matchMedia('(pointer: coarse), (hover: none), (max-width: 1024px)');
    if (cameraCaptureMediaQuery.addEventListener) {
        cameraCaptureMediaQuery.addEventListener('change', syncCameraCaptureVisibility);
    } else if (cameraCaptureMediaQuery.addListener) {
        cameraCaptureMediaQuery.addListener(syncCameraCaptureVisibility);
    }
    await fetchImages();
});
watch(() => [props.parentId, props.parentType], () => { fetchImages(); });
watch(() => images.value.length, () => { setTimeout(() => { initializeSortable(); }, 100); });
onUnmounted(() => {
    if (cameraCaptureMediaQuery) {
        if (cameraCaptureMediaQuery.removeEventListener) {
            cameraCaptureMediaQuery.removeEventListener('change', syncCameraCaptureVisibility);
        } else if (cameraCaptureMediaQuery.removeListener) {
            cameraCaptureMediaQuery.removeListener(syncCameraCaptureVisibility);
        }
    }
    if (sortableInstance) sortableInstance.destroy();
    clearTimeout(dragLeaveTimer);
});
</script>

<style scoped>
.sortable-ghost {
    opacity: 0.4 !important;
    background: rgba(59, 130, 246, 0.15) !important;
    border: 2px dashed rgb(59, 130, 246) !important;
    border-radius: 0.75rem !important;
}
.sortable-ghost * { opacity: 0.4 !important; }
.sortable-chosen { opacity: 1; }
.sortable-drag {
    opacity: 0.85 !important;
    transform: rotate(2deg) scale(1.04);
    box-shadow: 0 20px 25px -5px rgba(0,0,0,0.25), 0 10px 10px -5px rgba(0,0,0,0.15);
    border-radius: 0.75rem;
}

/* Transitions */
.fade-enter-active, .fade-leave-active { transition: opacity 0.15s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

.slide-down-enter-active, .slide-down-leave-active { transition: all 0.2s ease; }
.slide-down-enter-from, .slide-down-leave-to { opacity: 0; transform: translateY(-6px); }

.modal-enter-active, .modal-leave-active { transition: opacity 0.2s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
.animate-spin { animation: spin 1s linear infinite; }
</style>