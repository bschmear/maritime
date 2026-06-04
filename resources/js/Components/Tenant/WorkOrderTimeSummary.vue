<script setup>
import { computed } from 'vue';

const props = defineProps({
    estimatedHours: { type: Number, default: 0 },
    actualHours: { type: Number, default: 0 },
    compact: { type: Boolean, default: false },
});

const estimated = computed(() => Math.max(0, Number(props.estimatedHours) || 0));
const actual = computed(() => Math.max(0, Number(props.actualHours) || 0));

const percentUsed = computed(() => {
    if (estimated.value <= 0) {
        return actual.value > 0 ? 100 : 0;
    }
    return Math.min(100, Math.round((actual.value / estimated.value) * 100));
});

const barClass = computed(() => {
    if (estimated.value <= 0) {
        return 'bg-gray-400';
    }
    if (actual.value > estimated.value) {
        return 'bg-amber-500';
    }
    if (percentUsed.value >= 90) {
        return 'bg-amber-400';
    }
    return 'bg-blue-600';
});

const formatHours = (n) => {
    const v = Number(n) || 0;
    return Number.isInteger(v) ? String(v) : v.toFixed(2);
};
</script>

<template>
    <div :class="compact ? 'space-y-2' : 'space-y-3'">
        <div class="flex items-baseline justify-between gap-2">
            <span class="text-sm text-gray-600 dark:text-gray-400">Actual / estimated</span>
            <span class="font-semibold text-gray-900 dark:text-white tabular-nums">
                {{ formatHours(actual) }}
                <span class="font-normal text-gray-500 dark:text-gray-400">/ {{ formatHours(estimated) }} hrs</span>
            </span>
        </div>
        <div
            class="h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700"
            role="progressbar"
            :aria-valuenow="percentUsed"
            aria-valuemin="0"
            aria-valuemax="100"
        >
            <div
                class="h-full rounded-full transition-all duration-300"
                :class="barClass"
                :style="{ width: `${percentUsed}%` }"
            />
        </div>
        <p v-if="actual > estimated && estimated > 0" class="text-xs text-amber-700 dark:text-amber-300">
            {{ formatHours(actual - estimated) }} hr over estimate
        </p>
    </div>
</template>
