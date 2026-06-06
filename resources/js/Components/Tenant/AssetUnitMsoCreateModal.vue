<script setup>
import Modal from '@/Components/Modal.vue';
import { Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    parentId: { type: Number, required: true },
    parentType: { type: String, default: 'AssetUnit' },
    builderLinks: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'uploaded']);

const uploading = ref(false);
const uploadError = ref('');
const fileInput = ref(null);

const attachableType = computed(() => `App\\Domain\\${props.parentType}\\Models\\${props.parentType}`);

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

        emit('uploaded');
        emit('close');
        router.reload({ only: ['record'], preserveScroll: true });
    } catch (error) {
        uploadError.value = error?.response?.data?.message || 'Failed to upload MSO. Please try again.';
    } finally {
        uploading.value = false;
        if (fileInput.value) {
            fileInput.value.value = '';
        }
    }
};

const onFileSelected = (event) => {
    uploadFile(event.target.files?.[0]);
};
</script>

<template>
    <Modal :show="show" max-width="md" @close="emit('close')">
        <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">New MSO</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Upload the manufacturer&apos;s original MSO for this unit, or create a completed MSO from a deal line.
            </p>

            <div class="mt-6 space-y-4">
                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Upload MSO</h4>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Attach the manufacturer PDF to this unit (saved as the original MSO document).
                    </p>
                    <button
                        type="button"
                        class="mt-3 inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                        :disabled="uploading"
                        @click="openFilePicker"
                    >
                        <span class="material-icons text-base">upload_file</span>
                        {{ uploading ? 'Uploading…' : 'Upload PDF' }}
                    </button>
                    <p v-if="uploadError" class="mt-2 text-sm text-red-600 dark:text-red-400">
                        {{ uploadError }}
                    </p>
                </div>

                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Create new</h4>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Open the MSO builder to fill out and submit an MSO for a deal that includes this unit.
                    </p>

                    <div v-if="builderLinks.length === 0" class="mt-3 text-sm text-amber-700 dark:text-amber-300">
                        This unit is not on a deal line yet. Add it to a transaction before creating an MSO in the builder.
                    </div>

                    <div v-else-if="builderLinks.length === 1" class="mt-3">
                        <Link
                            :href="builderLinks[0].builder_url"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-primary-700"
                            @click="emit('close')"
                        >
                            <span class="material-icons text-base">edit_document</span>
                            Create MSO for {{ builderLinks[0].transaction_label }}
                        </Link>
                    </div>

                    <ul v-else class="mt-3 space-y-2">
                        <li v-for="link in builderLinks" :key="`${link.transaction_id}-${link.line_item_id}`">
                            <Link
                                :href="link.builder_url"
                                class="flex w-full items-center justify-between gap-3 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm font-medium text-gray-800 hover:bg-gray-100 dark:border-gray-600 dark:bg-gray-900/50 dark:text-gray-100 dark:hover:bg-gray-800"
                                @click="emit('close')"
                            >
                                <span>{{ link.transaction_label }}</span>
                                <span class="material-icons text-base text-primary-600 dark:text-primary-400">arrow_forward</span>
                            </Link>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="mt-6 flex justify-end">
                <button
                    type="button"
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                    @click="emit('close')"
                >
                    Cancel
                </button>
            </div>

            <input
                ref="fileInput"
                type="file"
                accept="application/pdf,.pdf"
                class="hidden"
                @change="onFileSelected"
            />
        </div>
    </Modal>
</template>
