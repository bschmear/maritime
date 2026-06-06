<script setup>
defineProps({
    fieldTypes: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['add']);
</script>

<template>
    <div class="space-y-2">
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
            Drag fields onto the MSO
        </p>
        <div class="flex flex-wrap gap-2">
            <button
                v-for="type in fieldTypes"
                :key="type.value"
                type="button"
                draggable="true"
                class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:border-primary-300 hover:bg-primary-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:border-primary-600 dark:hover:bg-primary-950/30"
                @dragstart="$event.dataTransfer?.setData('application/mso-field-type', type.value)"
                @click="emit('add', type.value)"
            >
                {{ type.label }}
            </button>
        </div>
        <p class="text-xs text-gray-500 dark:text-gray-400">
            Click or drag a field onto the document, then move and resize it.
        </p>
    </div>
</template>
