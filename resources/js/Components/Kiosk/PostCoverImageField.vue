<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { computed, ref } from 'vue';

const coverImageFile = defineModel('coverImageFile', { type: [Object, null], default: null });

const props = defineProps({
    existingUrl: { type: String, default: '' },
    fileError: { type: String, default: null },
});

const fileInput = ref(null);
const previewObjectUrl = ref(null);

const onFileChange = (event) => {
    const file = event.target.files?.[0];
    if (!file) {
        return;
    }

    coverImageFile.value = file;

    if (previewObjectUrl.value) {
        URL.revokeObjectURL(previewObjectUrl.value);
    }
    previewObjectUrl.value = URL.createObjectURL(file);
};

const clearUpload = () => {
    coverImageFile.value = null;
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

    const value = (props.existingUrl || '').trim();
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
                JPEG, PNG, WebP, or GIF — saved to /posts/ (max 10 MB)
            </p>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                    @click="fileInput?.click()"
                >
                    <span class="material-icons text-base">upload</span>
                    {{ existingUrl && !coverImageFile ? 'Replace image' : 'Choose file' }}
                </button>
                <button
                    v-if="coverImageFile"
                    type="button"
                    class="text-sm font-medium text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200"
                    @click="clearUpload"
                >
                    Clear selection
                </button>
            </div>
            <input
                id="cover_image_file"
                ref="fileInput"
                type="file"
                accept="image/jpeg,image/png,image/webp,image/gif"
                class="sr-only"
                @change="onFileChange"
            />
            <InputError class="mt-2" :message="fileError" />
        </div>
    </div>
</template>
