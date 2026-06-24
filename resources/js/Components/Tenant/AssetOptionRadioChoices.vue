<script setup>
defineProps({
    opt: { type: Object, required: true },
    inputName: { type: String, required: true },
    formatPrice: { type: Function, default: (value) => value },
    isSelected: { type: Function, required: true },
    hasAnySelection: { type: Function, required: true },
});

const emit = defineEmits(['select', 'clear']);
</script>

<template>
    <div class="mt-2 flex flex-wrap gap-x-4 gap-y-2">
        <label
            v-if="!opt.is_required"
            class="inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400"
        >
            <input
                type="radio"
                :name="inputName"
                :checked="!hasAnySelection()"
                @change="emit('clear')"
            />
            <span>None</span>
        </label>
        <label
            v-for="v in opt.values"
            :key="v.id"
            class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"
        >
            <input
                type="radio"
                :name="inputName"
                :checked="isSelected(v.id)"
                @change="emit('select', v.id)"
            />
            <span
                v-if="v.color_hex"
                class="inline-block h-4 w-4 rounded border border-gray-300"
                :style="{ backgroundColor: v.color_hex }"
            />
            <span>{{ v.label }}</span>
            <span v-if="v.price != null && v.price !== ''" class="text-gray-500 tabular-nums">
                {{ formatPrice(v.price) }}
            </span>
        </label>
    </div>
</template>
