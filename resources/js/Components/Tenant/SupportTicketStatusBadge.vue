<script setup>
import { computed } from 'vue';

const props = defineProps({
    label: { type: String, required: true },
    color: { type: String, default: 'gray' },
});

const classes = computed(() => {
    const map = {
        blue: 'bg-blue-100 text-blue-800 border-blue-400 dark:bg-gray-700 dark:text-blue-400',
        yellow: 'bg-yellow-100 text-yellow-800 border-yellow-400 dark:bg-gray-700 dark:text-yellow-400',
        green: 'bg-green-100 text-green-800 border-green-400 dark:bg-gray-700 dark:text-green-400',
        red: 'bg-red-100 text-red-800 border-red-400 dark:bg-gray-700 dark:text-red-400',
        gray: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300',
    };

    return map[props.color] ?? map.gray;
});
</script>

<template>
    <span
        :class="[
            'inline-flex items-center rounded-sm px-2.5 py-0.5 text-xs font-medium border',
            classes,
        ]"
    >
        <svg class="me-1.5 h-3 w-3 shrink-0" aria-hidden="true" fill="currentColor" viewBox="0 0 24 24">
            <path
                fill-rule="evenodd"
                clip-rule="evenodd"
                d="M5.5 3a1 1 0 0 0 0 2H7v2.3c0 .7.2 1.3.6 1.8L9 11.9l.1.1v.1L7.5 15a3 3 0 0 0-.6 1.8V19H5.5a1 1 0 1 0 0 2h13a1 1 0 1 0 0-2H17v-2.3a3 3 0 0 0-.6-1.8l-1.6-2.8v-.2l1.6-2.8a3 3 0 0 0 .6-1.8V5h1.5a1 1 0 1 0 0-2h-13Z"
            />
        </svg>
        {{ label }}
    </span>
</template>
