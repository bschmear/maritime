<script setup>
defineProps({
    dashed: { type: Boolean, default: false },
    vertical: { type: Boolean, default: false },
    label: { type: String, default: null },
});
</script>

<template>
    <div
        class="flex shrink-0 items-center justify-center text-gray-400 dark:text-gray-500"
        :class="vertical ? 'flex-col py-2 px-1' : 'flex-row px-1 py-4'"
        aria-hidden="true"
    >
        <template v-if="vertical">
            <div
                class="w-0.5 h-5"
                :class="dashed ? 'border-l-2 border-dashed border-gray-400 dark:border-gray-500' : 'bg-gray-400 dark:bg-gray-500'"
            />
            <span
                class="material-icons text-lg leading-none"
                :class="dashed ? 'opacity-60' : ''"
            >
                south
            </span>
            <div
                class="w-0.5 h-5"
                :class="dashed ? 'border-l-2 border-dashed border-gray-400 dark:border-gray-500' : 'bg-gray-400 dark:bg-gray-500'"
            />
        </template>
        <template v-else>
            <div
                class="h-0.5 w-6 sm:w-10"
                :class="dashed ? 'border-t-2 border-dashed border-gray-400 dark:border-gray-500' : 'bg-gray-400 dark:bg-gray-500'"
            />
            <span
                class="material-icons text-xl leading-none -mx-0.5"
                :class="dashed ? 'opacity-60' : ''"
            >
                arrow_forward
            </span>
            <div
                class="h-0.5 w-2"
                :class="dashed ? 'border-t-2 border-dashed border-gray-400 dark:border-gray-500' : 'bg-gray-400 dark:bg-gray-500'"
            />
        </template>
        <span
            v-if="label"
            class="absolute text-[10px] font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400"
        >
            {{ label }}
        </span>
    </div>
</template>
