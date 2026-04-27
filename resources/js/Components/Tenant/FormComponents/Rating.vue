<template>
    <div class="flex items-center gap-0.5" role="group" :aria-label="ariaLabel">
        <template v-for="star in 5" :key="star">
            <button
                type="button"
                :aria-pressed="star <= filledCount"
                :aria-label="`Rate ${star} out of 5`"
                @click="updateRating(star)"
                :disabled="disabled"
                class="rounded p-0.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-1 dark:focus-visible:ring-offset-gray-900 transition-colors duration-150 text-gray-300 dark:text-gray-600 hover:text-yellow-400 dark:hover:text-yellow-400"
                :class="{
                    'text-yellow-400 dark:text-yellow-400': star <= filledCount,
                    'cursor-pointer': !disabled,
                    'cursor-not-allowed opacity-50': disabled,
                }"
            >
                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                </svg>
            </button>
        </template>
        <span v-if="showValue && filledCount > 0" class="ml-2 text-sm text-gray-600 dark:text-gray-400">
            {{ filledCount }}/5
        </span>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: {
        type: [Number, String],
        default: null,
        validator: (v) => v == null || v === '' || typeof v === 'number' || typeof v === 'string',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    showValue: {
        type: Boolean,
        default: true,
    },
    ariaLabel: {
        type: String,
        default: 'Rating',
    },
});

const emit = defineEmits(['update:modelValue']);

const filledCount = computed(() => {
    const v = props.modelValue;
    if (v == null || v === '') {
        return 0;
    }
    const n = Number(v);
    if (!Number.isFinite(n)) {
        return 0;
    }
    return Math.min(5, Math.max(0, Math.round(n)));
});

const updateRating = (rating) => {
    if (!props.disabled) {
        emit('update:modelValue', rating);
    }
};
</script>