<script setup>
defineProps({
    title: { type: String, required: true },
    amount: { type: String, default: '' },
    muted: { type: Boolean, default: false },
    accent: {
        type: String,
        default: '',
        validator: (v) => ['', 'sky', 'gray'].includes(v),
    },
});
</script>

<template>
    <div
        class="rounded-lg border border-gray-200 p-4"
        :class="{
            'bg-white': !muted && accent !== 'sky',
            'bg-gray-50': muted && accent !== 'sky',
            'bg-sky-50/70': accent === 'sky',
        }"
    >
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0 flex-1 font-medium text-gray-900 break-words">
                <slot name="title">{{ title }}</slot>
            </div>
            <div v-if="amount || $slots.amount" class="shrink-0 font-semibold text-gray-900">
                <slot name="amount">{{ amount }}</slot>
            </div>
        </div>
        <div v-if="$slots.default" class="mt-3 space-y-2 border-t border-gray-200/80 pt-3">
            <slot />
        </div>
        <div v-if="$slots.children" class="mt-3 space-y-2">
            <slot name="children" />
        </div>
    </div>
</template>
