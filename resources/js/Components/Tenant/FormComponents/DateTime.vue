<script setup>
const model = defineModel({
    type: String,
    default: '',
});

const props = defineProps({
    required: {
        type: Boolean,
        default: false,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <input
        type="datetime-local"
        v-model="model"
        :required="required"
        :disabled="disabled"
        class="rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white w-full"
    />
</template>

