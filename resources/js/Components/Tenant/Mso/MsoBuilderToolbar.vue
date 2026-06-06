<script setup>
defineProps({
    saving: {
        type: Boolean,
        default: false,
    },
    generating: {
        type: Boolean,
        default: false,
    },
    submitting: {
        type: Boolean,
        default: false,
    },
    deleting: {
        type: Boolean,
        default: false,
    },
    canSubmit: {
        type: Boolean,
        default: false,
    },
    canDelete: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['save', 'generate', 'submit', 'delete']);
</script>

<template>
    <div
        class="fixed inset-x-0 bottom-0 z-50 hidden border-t border-gray-200 bg-white shadow-[0_-4px_24px_rgba(0,0,0,0.08)] dark:border-gray-700 dark:bg-gray-900 dark:shadow-[0_-4px_24px_rgba(0,0,0,0.35)] min-[1280px]:block"
    >
        <div class="flex w-full items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
            <button
                v-if="canDelete"
                type="button"
                class="inline-flex items-center rounded-lg border border-red-200 bg-white px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50 disabled:opacity-50 dark:border-red-900 dark:bg-gray-900 dark:text-red-400 dark:hover:bg-red-950/40"
                :disabled="deleting || saving || generating || submitting"
                @click="emit('delete')"
            >
                <svg
                    v-if="deleting"
                    class="mr-2 h-4 w-4 animate-spin"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                >
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                </svg>
                <span v-else class="material-icons mr-1.5 text-base leading-none" aria-hidden="true">delete</span>
                {{ deleting ? 'Deleting…' : 'Delete' }}
            </button>
            <div v-else />

            <div class="flex flex-wrap items-center justify-end gap-2">
                <button
                    type="button"
                    class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                    :disabled="saving || deleting"
                    @click="emit('save')"
                >
                    <svg
                        v-if="saving"
                        class="mr-2 h-4 w-4 animate-spin"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                    >
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                    </svg>
                    {{ saving ? 'Saving…' : 'Save draft' }}
                </button>
                <button
                    type="button"
                    class="inline-flex items-center rounded-lg border border-blue-300 bg-blue-50 px-4 py-2 text-sm font-medium text-blue-800 hover:bg-blue-100 disabled:opacity-50 dark:border-blue-800 dark:bg-blue-950/40 dark:text-blue-100 dark:hover:bg-blue-950/60"
                    :disabled="generating || !canSubmit || deleting"
                    @click="emit('generate')"
                >
                    {{ generating ? 'Generating…' : 'Preview PDF' }}
                </button>
                <button
                    type="button"
                    class="inline-flex items-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50 dark:bg-primary-500 dark:hover:bg-primary-600"
                    :disabled="submitting || !canSubmit || deleting"
                    @click="emit('submit')"
                >
                    {{ submitting ? 'Submitting…' : 'Submit MSO' }}
                </button>
            </div>
        </div>
    </div>
</template>
