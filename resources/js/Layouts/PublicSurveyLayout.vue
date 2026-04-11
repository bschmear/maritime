<script setup>
defineProps({
    surveyColor: {
        type: String,
        default: '#2563eb',
    },
    embed: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <div
        class="min-h-screen antialiased text-gray-900"
        :class="embed ? 'bg-white' : 'bg-gradient-to-b from-gray-50 to-gray-100'"
    >
        <div
            v-if="!embed"
            class="h-1.5 w-full"
            :style="{ background: `linear-gradient(90deg, ${surveyColor}, ${surveyColor}cc)` }"
        />
        <div :class="embed ? 'px-3 py-4' : 'mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8'">
            <slot />
        </div>
    </div>
</template>
