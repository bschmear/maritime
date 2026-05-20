<script setup>
import { computed } from 'vue';

const props = defineProps({
    name: { type: String, default: '?' },
    size: { type: String, default: 'md' },
});

const initial = computed(() => (props.name?.trim()?.[0] ?? '?').toUpperCase());

const color = computed(() => {
    const hash = [...(props.name ?? '')].reduce((acc, c) => acc + c.charCodeAt(0), 0);

    return `#${((hash * 997) % 0xffffff).toString(16).padStart(6, '0').slice(0, 6)}`;
});

const sizeClass = computed(() => (props.size === 'sm' ? 'h-8 w-8 text-sm' : 'h-10 w-10 text-base'));
</script>

<template>
    <div
        :class="[
            'flex shrink-0 items-center justify-center rounded-full font-semibold text-white',
            sizeClass,
        ]"
        :style="{ backgroundColor: color }"
    >
        {{ initial }}
    </div>
</template>
