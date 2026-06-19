<script setup>
defineProps({
    formId: {
        type: String,
        required: true,
    },
    processing: {
        type: Boolean,
        default: false,
    },
    submitLabel: {
        type: String,
        required: true,
    },
    processingLabel: {
        type: String,
        default: 'Saving…',
    },
    cancelLabel: {
        type: String,
        default: 'Cancel',
    },
    show: {
        type: Boolean,
        default: true,
    },
});

defineEmits(['cancel']);
</script>

<template>
    <Teleport v-if="show" to="body">
        <div
            class="fixed inset-x-0 bottom-0 z-50 border-t border-gray-200 bg-white/95 px-4 py-3 shadow-[0_-4px_24px_rgba(0,0,0,0.08)] backdrop-blur supports-[backdrop-filter]:bg-white/90 dark:border-gray-700 dark:bg-gray-900/95 dark:supports-[backdrop-filter]:bg-gray-900/90"
        >
            <div class="flex w-full items-center justify-end gap-3">
                <button
                    type="button"
                    :disabled="processing"
                    class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                    @click="$emit('cancel')"
                >
                    {{ cancelLabel }}
                </button>
                <button
                    type="submit"
                    :form="formId"
                    :disabled="processing"
                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <svg
                        v-if="processing"
                        class="h-4 w-4 animate-spin"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path
                            class="opacity-75"
                            fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                        />
                    </svg>
                    <svg
                        v-else
                        class="h-4 w-4"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    {{ processing ? processingLabel : submitLabel }}
                </button>
            </div>
        </div>
    </Teleport>
</template>
