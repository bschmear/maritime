<template>
    <div class="px-5 py-4 border-t border-gray-100 dark:border-gray-700">
        <p class="text-[11px] text-gray-400 dark:text-gray-500 uppercase tracking-wide mb-3">Driver&apos;s license</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div v-for="slot in slots" :key="slot.side">
                <p class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-2">{{ slot.label }}</p>
                <button
                    type="button"
                    class="relative w-full aspect-[1.586/1] rounded-lg overflow-hidden transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800"
                    :class="slot.url
                        ? 'border border-gray-200 dark:border-gray-600'
                        : 'border-2 border-dashed border-gray-300 dark:border-gray-600 hover:border-primary-400 dark:hover:border-primary-500 bg-gray-50/50 dark:bg-gray-900/30'"
                    :disabled="uploadingSide === slot.side"
                    @click="openPicker(slot.side)"
                >
                    <img
                        v-if="slot.url"
                        :src="slot.url"
                        :alt="slot.label"
                        class="absolute inset-0 w-full h-full object-cover"
                    />
                    <div
                        v-else
                        class="absolute inset-0 flex flex-col items-center justify-center gap-2 text-gray-400 dark:text-gray-500"
                    >
                        <span class="material-icons text-3xl">add_photo_alternate</span>
                        <span class="text-xs font-medium">Click to upload</span>
                    </div>
                    <div
                        v-if="uploadingSide === slot.side"
                        class="absolute inset-0 flex items-center justify-center bg-white/70 dark:bg-gray-900/70"
                    >
                        <span class="material-icons text-3xl text-primary-600 animate-spin">sync</span>
                    </div>
                    <div
                        v-else-if="slot.url"
                        class="absolute inset-0 flex items-center justify-center bg-black/0 hover:bg-black/40 opacity-0 hover:opacity-100 transition-opacity"
                    >
                        <span class="text-xs font-medium text-white px-2 py-1 rounded bg-black/50">Replace</span>
                    </div>
                </button>
                <input
                    :ref="(el) => setFileInput(slot.side, el)"
                    type="file"
                    accept="image/*"
                    class="hidden"
                    @change="(e) => onFileChosen(slot.side, e)"
                />
            </div>
        </div>
        <p v-if="uploadError" class="mt-2 text-xs text-red-600 dark:text-red-400">{{ uploadError }}</p>
    </div>
</template>

<script setup>
import axios from 'axios';
import { computed, ref } from 'vue';

const props = defineProps({
    contactId: { type: Number, required: true },
    front: { type: Object, default: null },
    back: { type: Object, default: null },
});

const emit = defineEmits(['updated']);

const uploadingSide = ref(null);
const uploadError = ref('');

const fileInputs = ref({ front: null, back: null });

const slots = computed(() => [
    {
        side: 'front',
        label: 'Front',
        url: props.front?.url ?? null,
    },
    {
        side: 'back',
        label: 'Back',
        url: props.back?.url ?? null,
    },
]);

const setFileInput = (side, el) => {
    if (el) {
        fileInputs.value[side] = el;
    }
};

const openPicker = (side) => {
    uploadError.value = '';
    fileInputs.value[side]?.click();
};

const onFileChosen = async (side, event) => {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (!file) {
        return;
    }

    uploadingSide.value = side;
    uploadError.value = '';

    try {
        const formData = new FormData();
        formData.append('file', file);

        const response = await axios.post(
            route('contacts.driver-license.upload', { contact: props.contactId, side }),
            formData,
            { headers: { 'Content-Type': 'multipart/form-data' } },
        );

        emit('updated', { side, image: response.data.image });
    } catch (error) {
        uploadError.value = error.response?.data?.message
            ?? 'Upload failed. Please try again.';
    } finally {
        uploadingSide.value = null;
    }
};
</script>
