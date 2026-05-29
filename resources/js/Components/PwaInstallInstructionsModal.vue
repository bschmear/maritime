<script setup>
import Modal from '@/Components/Modal.vue';
import { usePwaInstall } from '@/composables/usePwaInstall';

const { showInstructions, dismiss, closeInstructions } = usePwaInstall();
</script>

<template>
    <Modal :show="showInstructions" max-width="md" @close="closeInstructions">
        <div class="p-6">
            <div class="mb-4 flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Install on your iPhone
                    </h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Add this app to your home screen for quick access.
                    </p>
                </div>
                <button
                    type="button"
                    class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-200"
                    aria-label="Close"
                    @click="closeInstructions"
                >
                    <span class="material-icons text-xl">close</span>
                </button>
            </div>

            <ol class="space-y-4 text-sm text-gray-700 dark:text-gray-300">
                <li class="flex gap-3">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary-100 text-xs font-semibold text-primary-700 dark:bg-primary-900/40 dark:text-primary-300">1</span>
                    <span>
                        Tap the
                        <span class="inline-flex items-center align-middle">
                            <span class="material-icons mx-0.5 text-base leading-none">ios_share</span>
                        </span>
                        Share button in Safari's toolbar.
                    </span>
                </li>
                <li class="flex gap-3">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary-100 text-xs font-semibold text-primary-700 dark:bg-primary-900/40 dark:text-primary-300">2</span>
                    <span>Scroll down and tap <strong>Add to Home Screen</strong>.</span>
                </li>
                <li class="flex gap-3">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary-100 text-xs font-semibold text-primary-700 dark:bg-primary-900/40 dark:text-primary-300">3</span>
                    <span>Tap <strong>Add</strong> in the top-right corner.</span>
                </li>
            </ol>

            <div class="mt-6 flex justify-end gap-2">
                <button
                    type="button"
                    class="rounded-lg px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"
                    @click="dismiss"
                >
                    Don't show again
                </button>
                <button
                    type="button"
                    class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700"
                    @click="closeInstructions"
                >
                    Got it
                </button>
            </div>
        </div>
    </Modal>
</template>
