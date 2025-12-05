<template>
    <div class="flex items-center space-x-1">
        <template v-for="star in 5" :key="star">
            <button
                type="button"
                @click="updateRating(star)"
                :disabled="disabled"
                class="text-gray-300 hover:text-yellow-400 focus:outline-none focus:text-yellow-400 transition-colors duration-150"
                :class="{
                    'text-yellow-400': star <= modelValue,
                    'cursor-pointer': !disabled,
                    'cursor-not-allowed opacity-50': disabled
                }"
            >
                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                </svg>
            </button>
        </template>
        <!-- <span v-if="!disabled && showValue" class="ml-2 text-sm text-gray-600 dark:text-gray-400">
            {{ modelValue }}/5
        </span> -->
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: {
        type: [Number, String],
        default: 0,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    showValue: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['update:modelValue']);

const updateRating = (rating) => {
    if (!props.disabled) {
        emit('update:modelValue', rating);
    }
};
</script>