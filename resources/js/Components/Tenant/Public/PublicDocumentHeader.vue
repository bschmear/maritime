<script setup>
defineProps({
    logoUrl: { type: String, default: null },
    /** Primary line (e.g. company name) */
    title: { type: String, required: true },
    /** Muted subtitle under title */
    subtitle: { type: String, default: '' },
    /** Small label above right column (e.g. "Agreement #") */
    rightLabel: { type: String, default: '' },
    /** Prominent right column value */
    rightValue: { type: String, default: '' },
});
</script>

<template>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="bg-gradient-to-r from-primary-600 to-primary-700 px-6 py-5 flex items-center justify-between">
            <div class="flex items-center gap-4 min-w-0">
                <img v-if="logoUrl" :src="logoUrl" alt="Logo" class="h-10 w-auto object-contain shrink-0" />
                <div class="min-w-0">
                    <p class="text-white font-bold text-lg leading-tight truncate">{{ title }}</p>
                    <p v-if="subtitle" class="text-primary-200 text-sm truncate">{{ subtitle }}</p>
                </div>
            </div>
            <div v-if="rightLabel || rightValue" class="text-right shrink-0 ml-4">
                <p v-if="rightLabel" class="text-primary-200 text-xs">{{ rightLabel }}</p>
                <p v-if="rightValue" class="text-white font-mono font-bold text-xl">{{ rightValue }}</p>
            </div>
        </div>
    </div>
</template>
