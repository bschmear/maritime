<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { BLOG_COVER_MAX_WIDTH, resizeImageFileToMaxWidth } from '@/Utils/resizeImageFile.js';
import axios from 'axios';
import { computed, ref } from 'vue';

const coverImage = defineModel('coverImage', { type: String, default: '' });

const props = defineProps({
    existingUrl: { type: String, default: '' },
    previousCover: { type: String, default: '' },
    fileError: { type: String, default: null },
    uploadUrl: { type: String, required: true },
});

const fileInput = ref(null);
const previewObjectUrl = ref(null);
const isUploading = ref(false);
const uploadError = ref(null);

const onFileChange = async (event) => {
    const file = event.target.files?.[0];
    if (!file) {
        return;
    }

    uploadError.value = null;
    isUploading.value = true;

    try {
        const prepared = await resizeImageFileToMaxWidth(file, BLOG_COVER_MAX_WIDTH);
        const formData = new FormData();
        formData.append('cover_image_file', prepared);
        if (props.previousCover || coverImage.value) {
            formData.append('previous_cover', props.previousCover || coverImage.value);
        }

        const { data } = await axios.post(props.uploadUrl, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        coverImage.value = data.cover_image ?? '';

        if (previewObjectUrl.value) {
            URL.revokeObjectURL(previewObjectUrl.value);
        }
        previewObjectUrl.value = URL.createObjectURL(prepared);
    } catch (error) {
        uploadError.value =
            error?.response?.data?.message ||
            error?.response?.data?.errors?.cover_image_file?.[0] ||
            'Could not upload image. Try a smaller file.';
        coverImage.value = '';
    } finally {
        isUploading.value = false;
        if (fileInput.value) {
            fileInput.value.value = '';
        }
    }
};

const clearUpload = () => {
    coverImage.value = '';
    uploadError.value = null;
    if (previewObjectUrl.value) {
        URL.revokeObjectURL(previewObjectUrl.value);
        previewObjectUrl.value = null;
    }
    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

const previewSrc = computed(() => {
    if (previewObjectUrl.value) {
        return previewObjectUrl.value;
    }

    const value = (coverImage.value || props.existingUrl || '').trim();
    if (!value) {
        return null;
    }

    if (value.startsWith('http://') || value.startsWith('https://') || value.startsWith('/')) {
        return value;
    }

    return `/${value}`;
});

const hasPreview = computed(() => Boolean(previewSrc.value));
</script>

<template>
    <div class="space-y-4">
        <div
            v-if="hasPreview"
            class="overflow-hidden rounded-lg border border-gray-200 bg-gray-50 dark:border-gray-600 dark:bg-gray-800"
        >
            <img
                :src="previewSrc"
                alt="Cover preview"
                class="max-h-48 w-full object-cover"
                @error="$event.target.style.display = 'none'"
            />
        </div>

        <div>
            <InputLabel for="cover_image_file" value="Hero image" />
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Uploads immediately; wide images are scaled to {{ BLOG_COVER_MAX_WIDTH }}px wide
            </p>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                    :disabled="isUploading"
                    @click="fileInput?.click()"
                >
                    <span class="material-icons text-base">{{ isUploading ? 'hourglass_empty' : 'upload' }}</span>
                    {{
                        isUploading
                            ? 'Uploading…'
                            : hasPreview
                              ? 'Replace image'
                              : 'Choose file'
                    }}
                </button>
                <button
                    v-if="coverImage || previewObjectUrl"
                    type="button"
                    class="text-sm font-medium text-gray-500 hover:text-gray-800 disabled:opacity-50 dark:text-gray-400 dark:hover:text-gray-200"
                    :disabled="isUploading"
                    @click="clearUpload"
                >
                    Remove
                </button>
            </div>
            <input
                id="cover_image_file"
                ref="fileInput"
                type="file"
                accept="image/jpeg,image/png,image/webp,image/gif"
                class="sr-only"
                :disabled="isUploading"
                @change="onFileChange"
            />
            <InputError class="mt-2" :message="fileError || uploadError" />
        </div>
    </div>
</template>
