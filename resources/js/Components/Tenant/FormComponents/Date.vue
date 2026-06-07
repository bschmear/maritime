<script setup>
const model = defineModel({
    type: String,
    default: '',
});

const props = defineProps({
    id: {
        type: String,
        default: '',
    },
    required: {
        type: Boolean,
        default: false,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    min: {
        type: String,
        default: '',
    },
    max: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <input
        :id="id"
        type="date"
        v-model="model"
        :required="required"
        :disabled="disabled"
        :min="min || undefined"
        :max="max || undefined"
        class="input-style w-full"
    />
</template>

