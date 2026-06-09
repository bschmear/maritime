<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    label: {
        type: String,
        required: true,
    },
    href: {
        type: String,
        default: null,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['click']);

const buttonClass = computed(() => {
    if (props.disabled) {
        return 'text-gray-300 dark:text-gray-600 cursor-not-allowed';
    }

    return 'text-[#8E8E93] active:opacity-50 dark:text-[#8E8E93]';
});
</script>

<template>
    <component
        :is="href ? Link : 'button'"
        :href="href"
        :type="href ? undefined : 'button'"
        :disabled="disabled"
        :aria-label="label"
        :title="label"
        class="flex min-h-[49px] flex-1 items-center justify-center px-2 py-1.5 transition-opacity"
        :class="buttonClass"
        @click="!href && $emit('click', $event)"
    >
        <span class="flex items-center justify-center [&>.material-icons]:text-[22px] [&>.material-icons]:leading-none [&>svg]:h-[22px] [&>svg]:w-[22px] [&>svg]:stroke-[1.75]">
            <slot />
        </span>
    </component>
</template>
