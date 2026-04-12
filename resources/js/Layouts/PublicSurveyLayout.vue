<script setup>
defineProps({
    embed: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <div
        class="min-h-screen antialiased text-gray-900"
        :class="embed ? 'bg-white' : 'bg-gray-50 dark:bg-gray-900'"
    >
        <!-- Full-width slot for marketing hero + grid; embed uses a narrow readable column -->
        <div :class="embed ? 'mx-auto max-w-3xl px-3 py-4' : 'w-full'">
            <slot />
        </div>
    </div>
</template>
