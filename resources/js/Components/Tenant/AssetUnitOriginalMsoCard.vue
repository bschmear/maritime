<script setup>
import { Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref } from 'vue';

const props = defineProps({
    parentId: { type: Number, required: true },
    parentType: { type: String, default: 'AssetUnit' },
    document: { type: Object, default: null },
    msoRecords: { type: Array, default: () => [] },
});

const uploading = ref(false);
const isDragging = ref(false);
const uploadError = ref('');
const fileInput = ref(null);

let dragLeaveTimer = null;

const attachableType = computed(() => `App\\Domain\\${props.parentType}\\Models\\${props.parentType}`);

const hasDocument = computed(() => Boolean(props.document?.id));

const formatFileSize = (bytes) => {
    const size = Number(bytes);
    if (!size) {
        return '';
    }
    if (size < 1024) {
        return `${size} B`;
    }
    if (size < 1024 * 1024) {
        return `${(size / 1024).toFixed(1)} KB`;
    }
    return `${(size / (1024 * 1024)).toFixed(1)} MB`;
};

function formatDate(value) {
    if (!value) {
        return '—';
    }
    try {
        return new Date(value).toLocaleString();
    } catch {
        return value;
    }
}

function statusBadgeClass(status) {
    if (status === 'submitted') {
        return 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200';
    }
    if (status === 'draft') {
        return 'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-200';
    }
    return 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200';
}

const onDragOver = () => {
    clearTimeout(dragLeaveTimer);
    isDragging.value = true;
};

const onDragLeave = () => {
    dragLeaveTimer = setTimeout(() => {
        isDragging.value = false;
    }, 50);
};

const openFilePicker = () => {
    if (!uploading.value) {
        fileInput.value?.click();
    }
};

const uploadFile = async (file) => {
    if (!file) {
        return;
    }

    if (file.type !== 'application/pdf' && !file.name.toLowerCase().endsWith('.pdf')) {
        uploadError.value = 'Please upload a PDF file.';
        return;
    }

    uploading.value = true;
    uploadError.value = '';

    const formData = new FormData();
    formData.append('file', file);
    formData.append('display_name', file.name);
    formData.append('description', 'Original MSO');
    formData.append('attach_to_type', attachableType.value);
    formData.append('attach_to_id', String(props.parentId));
    formData.append('role', 'mso');

    try {
        await axios.post(route('documents.upload-attach'), formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        router.reload({ only: ['record', 'msoRecords'], preserveScroll: true });
    } catch (error) {
        uploadError.value = error?.response?.data?.message || 'Failed to upload MSO. Please try again.';
    } finally {
        uploading.value = false;
        if (fileInput.value) {
            fileInput.value.value = '';
        }
        isDragging.value = false;
    }
};

const onFileSelected = (event) => {
    uploadFile(event.target.files?.[0]);
};

const onDrop = (event) => {
    isDragging.value = false;
    uploadFile(event.dataTransfer?.files?.[0]);
};
</script>

<template>
    <div class="space-y-4">
        <div
            class="relative overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
            @drop.prevent="onDrop"
            @dragover.prevent="onDragOver"
            @dragleave.prevent="onDragLeave"
        >
            <Transition name="fade">
                <div
                    v-if="isDragging"
                    class="absolute inset-0 z-10 flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-primary-500 bg-primary-50/95 backdrop-blur-sm dark:bg-primary-950/90"
                >
                    <span class="material-icons mb-2 text-4xl text-primary-500">cloud_upload</span>
                    <p class="text-sm font-semibold text-primary-700 dark:text-primary-300">Drop MSO PDF here</p>
                </div>
            </Transition>

            <div class="border-b border-gray-100 px-5 py-3.5 dark:border-gray-700">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Original MSO</h3>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Manufacturer&apos;s Statement of Origin for this unit.
                </p>
            </div>

            <div class="p-5">
                <div v-if="hasDocument" class="space-y-3">
                    <div class="flex items-start gap-3 rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-600 dark:bg-gray-900/50">
                        <span class="material-icons shrink-0 text-red-500">picture_as_pdf</span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-gray-900 dark:text-white">
                                {{ document.display_name }}
                            </p>
                            <p v-if="document.file_size" class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                {{ formatFileSize(document.file_size) }}
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <a
                            :href="route('documents.stream', document.id)"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-700"
                        >
                            <span class="material-icons text-base">visibility</span>
                            View
                        </a>
                        <a
                            :href="route('documents.download', document.id)"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-700"
                        >
                            <span class="material-icons text-base">download</span>
                            Download
                        </a>
                    </div>

                    <button
                        type="button"
                        class="text-sm font-medium text-primary-600 hover:underline disabled:opacity-50 dark:text-primary-400"
                        :disabled="uploading"
                        @click="openFilePicker"
                    >
                        {{ uploading ? 'Uploading…' : 'Replace MSO' }}
                    </button>
                </div>

                <button
                    v-else
                    type="button"
                    class="flex w-full flex-col items-center justify-center rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 px-4 py-8 text-center transition hover:border-primary-400 hover:bg-primary-50/50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-600 dark:bg-gray-900/40 dark:hover:border-primary-500 dark:hover:bg-primary-950/20"
                    :disabled="uploading"
                    @click="openFilePicker"
                >
                    <span class="material-icons mb-2 text-4xl text-gray-400 dark:text-gray-500">upload_file</span>
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-200">
                        {{ uploading ? 'Uploading…' : 'Click to upload or drag and drop MSO here' }}
                    </span>
                    <span class="mt-1 text-xs text-gray-500 dark:text-gray-400">PDF only</span>
                </button>

                <p v-if="uploadError" class="mt-3 text-sm text-red-600 dark:text-red-400">
                    {{ uploadError }}
                </p>
            </div>

            <input
                ref="fileInput"
                type="file"
                accept="application/pdf,.pdf"
                class="hidden"
                @change="onFileSelected"
            />
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="border-b border-gray-100 px-5 py-3.5 dark:border-gray-700">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Created MSOs</h3>
            </div>

            <div v-if="msoRecords.length" class="divide-y divide-gray-100 dark:divide-gray-700">
                <Link
                    v-for="record in msoRecords"
                    :key="record.id"
                    :href="record.show_url"
                    class="flex items-start justify-between gap-3 px-5 py-3.5 transition hover:bg-gray-50 dark:hover:bg-gray-900/40"
                >
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-gray-900 dark:text-white">
                            {{ record.display_name }}
                        </p>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                            Created {{ formatDate(record.created_at) }}
                        </p>
                        <p v-if="record.transaction_label" class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                            {{ record.transaction_label }}
                        </p>
                    </div>
                    <span
                        class="inline-flex shrink-0 rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="statusBadgeClass(record.status)"
                    >
                        {{ record.status_label }}
                    </span>
                </Link>
            </div>

            <p v-else class="px-5 py-4 text-sm text-gray-500 dark:text-gray-400">
                No filled MSOs have been created for this unit yet.
            </p>
        </div>
    </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.15s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
