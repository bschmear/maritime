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
});
</script>

<template>
    <input
        :id="id"
        type="datetime-local"
        v-model="model"
        :required="required"
        :disabled="disabled"
        class="input-style w-full"
    />
</template>

