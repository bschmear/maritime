<script setup>
defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], default: '' },
    emphasize: { type: Boolean, default: false },
});
</script>

<template>
    <div class="flex items-start justify-between gap-3 text-sm">
        <span class="shrink-0 text-gray-500">{{ label }}</span>
        <span
            class="text-right text-gray-900"
            :class="emphasize ? 'font-semibold' : 'font-medium'"
        >
            <slot>{{ value }}</slot>
        </span>
    </div>
</template>
