<script setup>
import { computed } from 'vue';

const props = defineProps({
    field: {
        type: Object,
        required: true,
    },
    selected: {
        type: Boolean,
        default: false,
    },
    pageWidth: {
        type: Number,
        required: true,
    },
    pageHeight: {
        type: Number,
        required: true,
    },
    label: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['select', 'update-value', 'delete']);

const isMultiline = computed(() =>
    ['free_text', 'line_item', 'customer_address'].includes(props.field.type),
);

const isReadOnly = computed(() => props.field.type === 'user_signature');

const inputValue = computed(() => {
    const value = String(props.field.value ?? '');
    if (value) {
        return value;
    }
    if (props.field.type === 'user_signature') {
        return '[Signature]';
    }
    return '';
});

const containerStyle = computed(() => ({
    left: `${props.field.x * props.pageWidth}px`,
    top: `${props.field.y * props.pageHeight}px`,
    width: `${props.field.width * props.pageWidth}px`,
    height: `${props.field.height * props.pageHeight}px`,
}));

const inputStyle = computed(() => ({
    fontSize: `${props.field.font_size || 10}px`,
    lineHeight: 1.2,
    color: '#000000',
}));
</script>

<template>
    <div
        class="absolute z-20 flex overflow-visible bg-transparent"
        :class="selected ? 'ring-1 ring-blue-500/80' : ''"
        :style="containerStyle"
        :data-field-id="field.id"
        :title="label"
        @mousedown.stop="emit('select', field.id)"
    >
        <div
            v-if="selected"
            class="drag-handle z-10 w-2 shrink-0 cursor-move bg-blue-500/25"
            aria-label="Drag to move"
        />

        <textarea
            v-if="isMultiline"
            :value="inputValue"
            :readonly="isReadOnly"
            class="field-input pointer-events-auto m-0 min-h-0 min-w-0 flex-1 resize-none border-0 bg-transparent p-0 font-sans font-normal text-black shadow-none focus:outline-none focus:ring-0"
            :style="inputStyle"
            @input="emit('update-value', field.id, $event.target.value)"
            @mousedown.stop
            @click.stop="emit('select', field.id)"
        />
        <input
            v-else
            :value="inputValue"
            :readonly="isReadOnly"
            type="text"
            class="field-input pointer-events-auto m-0 min-h-0 min-w-0 flex-1 border-0 bg-transparent p-0 font-sans font-normal text-black shadow-none focus:outline-none focus:ring-0"
            :style="inputStyle"
            @input="emit('update-value', field.id, $event.target.value)"
            @mousedown.stop
            @click.stop="emit('select', field.id)"
        />

        <div
            v-if="selected"
            class="resize-handle-e z-10 w-2 shrink-0 cursor-ew-resize bg-blue-500/25"
            aria-label="Drag to resize width"
        />

        <div
            v-if="selected"
            class="resize-handle-s absolute bottom-0 left-0 right-0 z-10 h-1.5 cursor-ns-resize"
            aria-label="Drag to resize height"
        />
        <div
            v-if="selected"
            class="resize-handle-se absolute bottom-0 right-0 z-20 h-3 w-3 cursor-nwse-resize rounded-sm bg-blue-600 shadow"
            aria-label="Drag to resize"
        />

        <button
            v-if="selected"
            type="button"
            class="absolute -right-2 -top-2 z-30 flex h-5 w-5 items-center justify-center rounded-full bg-red-600 text-xs font-bold text-white shadow hover:bg-red-700"
            title="Remove field"
            @mousedown.stop
            @click.stop="emit('delete', field.id)"
        >
            ×
        </button>
    </div>
</template>

<style scoped>
.field-input {
    -webkit-text-fill-color: #000000;
}

.field-input::placeholder {
    color: rgba(0, 0, 0, 0.45);
}
</style>
