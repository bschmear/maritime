<script setup>
import { computed } from 'vue';

const props = defineProps({
    name: {
        type: String,
        default: '',
    },
    small: {
        type: Boolean,
        default: false,
    },
});

const initials = computed(() => {
    const s = props.name?.trim();
    if (!s) {
        return '?';
    }
    const parts = s.split(/\s+/).filter(Boolean);
    if (parts.length >= 2) {
        return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    }

    return s.slice(0, 2).toUpperCase();
});

const boxClass = computed(() =>
    props.small
        ? 'h-8 w-8 text-[10px]'
        : 'h-10 w-10 text-xs',
);
</script>

<template>
    <span
        class="inline-flex shrink-0 items-center justify-center rounded-full bg-slate-200 font-semibold text-slate-700 dark:bg-slate-600 dark:text-slate-100"
        :class="boxClass"
        :title="name || undefined"
    >
        {{ initials }}
    </span>
</template>
