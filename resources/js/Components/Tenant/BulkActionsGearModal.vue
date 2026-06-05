<script setup>
import Modal from '@/Components/Modal.vue';
import { ref } from 'vue';

const props = defineProps({
    actions: {
        type: Array,
        default: () => [],
    },
    title: {
        type: String,
        default: 'Actions',
    },
});

const emit = defineEmits(['action']);

const open = ref(false);

function openModal() {
    open.value = true;
}

function closeModal() {
    open.value = false;
}

function runAction(row) {
    closeModal();
    emit('action', row);
}
</script>

<template>
    <div v-if="actions.length" class="shrink-0">
        <button
            type="button"
            class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 shadow-sm transition-colors hover:bg-gray-50 hover:text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white"
            :aria-expanded="open"
            title="Actions"
            aria-label="Open actions"
            @click="openModal"
        >
            <span class="material-icons text-[22px]">settings</span>
        </button>

        <Modal :show="open" max-width="sm" @close="closeModal">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    {{ title }}
                </h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Choose an action to run on this list.
                </p>
                <ul class="mt-5 space-y-2">
                    <li v-for="(row, idx) in actions" :key="idx">
                        <button
                            type="button"
                            class="flex w-full items-center rounded-lg border border-gray-200 bg-white px-4 py-3 text-left text-sm font-medium text-gray-800 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 dark:hover:bg-gray-700/80"
                            @click="runAction(row)"
                        >
                            {{ row.label }}
                        </button>
                    </li>
                </ul>
                <div class="mt-5 flex justify-end">
                    <button
                        type="button"
                        class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                        @click="closeModal"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </Modal>
    </div>
</template>
