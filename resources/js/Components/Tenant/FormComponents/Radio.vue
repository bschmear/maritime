<script setup>
const model = defineModel({
    type: String,
    default: null,
});

const props = defineProps({
    value: {
        type: String,
        required: true,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <input
        type="radio"
        :value="value"
        v-model="model"
        :disabled="disabled"
        class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500"
    />
</template>

