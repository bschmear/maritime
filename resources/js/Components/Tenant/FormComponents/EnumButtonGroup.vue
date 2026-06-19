<script setup>
const model = defineModel({
    type: [Number, String],
    default: null,
});

const props = defineProps({
    id: {
        type: String,
        default: null,
    },
    options: {
        type: Array,
        default: () => [],
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const optionKey = (option) => option.id ?? option.value;

const isSelected = (option) => String(model.value) === String(optionKey(option));

const select = (value) => {
    if (props.disabled) {
        return;
    }

    model.value = value;
};

const baseButtonClass =
    'min-h-[2.5rem] rounded-lg border px-3 py-2 text-sm font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-1 disabled:cursor-not-allowed disabled:opacity-50 dark:focus:ring-offset-gray-900';

const unselectedClass =
    'border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700';

const selectedFallbackClass = 'border-primary-600 bg-primary-600 text-white';

const buttonClass = (option, selected) => {
    if (!selected) {
        return [baseButtonClass, unselectedClass];
    }

    if (option?.bgClass) {
        return [baseButtonClass, 'border-transparent', option.bgClass, 'ring-2 ring-primary-500 ring-offset-1 dark:ring-offset-gray-900'];
    }

    return [baseButtonClass, selectedFallbackClass];
};
</script>

<template>
    <div :id="id" class="w-full" role="radiogroup">
        <div class="grid w-full grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
            <button
                v-for="option in options"
                :key="optionKey(option)"
                type="button"
                :disabled="disabled"
                :class="buttonClass(option, isSelected(option))"
                :aria-checked="isSelected(option)"
                role="radio"
                @click="select(optionKey(option))"
            >
                {{ option.name }}
            </button>
        </div>
    </div>
</template>
