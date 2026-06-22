<script setup>
import Modal from '@/Components/Modal.vue';

defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: 'Sync to Google Sheet?' },
    description: {
        type: String,
        default: 'The linked Google Sheet will be overwritten with your current data from Helmful. Any changes in the spreadsheet that have not been imported back into Helmful will be lost.',
    },
});

const emit = defineEmits(['close', 'confirm']);
</script>

<template>
    <Modal :show="show" max-width="md" @close="emit('close')">
        <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                {{ title }}
            </h3>
            <p class="mt-3 text-sm leading-relaxed text-gray-600 dark:text-gray-300">
                {{ description }}
            </p>
            <div class="mt-6 flex flex-wrap justify-end gap-2">
                <button
                    type="button"
                    class="inline-flex rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                    @click="emit('close')"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    class="inline-flex rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700"
                    @click="emit('confirm')"
                >
                    Yes, continue
                </button>
            </div>
        </div>
    </Modal>
</template>
