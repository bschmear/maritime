<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { resizeImageFileToMaxWidth } from '@/Utils/resizeImageFile.js';
import axios from 'axios';
import { computed, ref } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    existingUrl: { type: String, default: '' },
    uploadUrl: { type: String, required: true },
    label: { type: String, default: 'Image' },
    maxWidth: { type: Number, default: 500 },
    help: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

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
        const prepared = await resizeImageFileToMaxWidth(file, props.maxWidth);
        const formData = new FormData();
        formData.append('logo_file', prepared);

        const { data } = await axios.post(props.uploadUrl, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        emit('update:modelValue', data.logo_url ?? '');

        if (previewObjectUrl.value) {
            URL.revokeObjectURL(previewObjectUrl.value);
        }
        previewObjectUrl.value = URL.createObjectURL(prepared);
    } catch (error) {
        uploadError.value =
            error?.response?.data?.message ||
            error?.response?.data?.errors?.logo_file?.[0] ||
            'Could not upload image. Try a smaller file.';
        emit('update:modelValue', '');
    } finally {
        isUploading.value = false;
        if (fileInput.value) {
            fileInput.value.value = '';
        }
    }
};

const clearUpload = () => {
    emit('update:modelValue', '');
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

    const value = (props.modelValue || props.existingUrl || '').trim();
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
                alt="Image preview"
                class="max-h-32 w-full object-contain p-4"
                @error="$event.target.style.display = 'none'"
            />
        </div>

        <div>
            <InputLabel for="logo_file" :value="label" />
            <p v-if="help" class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ help }}</p>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                    :disabled="isUploading"
                    @click="fileInput?.click()"
                >
                    {{
                        isUploading
                            ? 'Uploading…'
                            : hasPreview
                              ? 'Replace image'
                              : 'Choose file'
                    }}
                </button>
                <button
                    v-if="modelValue || previewObjectUrl || existingUrl"
                    type="button"
                    class="text-sm font-medium text-gray-500 hover:text-gray-800 disabled:opacity-50 dark:text-gray-400 dark:hover:text-gray-200"
                    :disabled="isUploading"
                    @click="clearUpload"
                >
                    Remove
                </button>
            </div>
            <input
                id="logo_file"
                ref="fileInput"
                type="file"
                accept="image/jpeg,image/png,image/webp,image/gif"
                class="sr-only"
                :disabled="isUploading"
                @change="onFileChange"
            />
            <InputError class="mt-2" :message="uploadError" />
        </div>
    </div>
</template>
