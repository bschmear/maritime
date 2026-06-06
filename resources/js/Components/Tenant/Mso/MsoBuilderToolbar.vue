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
    canSubmit: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['save', 'generate', 'submit']);
</script>

<template>
    <div class="flex flex-wrap gap-2">
        <button
            type="button"
            class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
            :disabled="saving"
            @click="emit('save')"
        >
            {{ saving ? 'Saving…' : 'Save draft' }}
        </button>
        <button
            type="button"
            class="inline-flex items-center rounded-lg border border-blue-300 bg-blue-50 px-4 py-2 text-sm font-medium text-blue-800 hover:bg-blue-100 disabled:opacity-50 dark:border-blue-800 dark:bg-blue-950/40 dark:text-blue-100"
            :disabled="generating || !canSubmit"
            @click="emit('generate')"
        >
            {{ generating ? 'Generating…' : 'Preview PDF' }}
        </button>
        <button
            type="button"
            class="inline-flex items-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
            :disabled="submitting || !canSubmit"
            @click="emit('submit')"
        >
            {{ submitting ? 'Submitting…' : 'Submit MSO' }}
        </button>
    </div>
</template>
