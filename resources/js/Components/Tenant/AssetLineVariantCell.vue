<script setup>
/**
 * Read-only variant label for asset line tables (estimates, opportunities, …).
 * Wrap in <td> with your column classes.
 */
defineProps({
    label: { type: String, default: '' },
    hasVariants: { type: Boolean, default: false },
    pendingLabel: { type: String, default: 'Select variant' },
});
</script>

<template>
    <span v-if="label" class="font-medium text-gray-800 dark:text-gray-200">{{ label }}</span>
    <span v-else-if="hasVariants" class="text-amber-600 dark:text-amber-400 text-xs">{{ pendingLabel }}</span>
    <span v-else class="text-gray-400 dark:text-gray-500">—</span>
</template>
