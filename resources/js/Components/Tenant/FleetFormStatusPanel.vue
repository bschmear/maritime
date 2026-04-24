<script setup>
const model = defineModel({ type: String, required: true });

defineProps({
    statuses: { type: Array, default: () => [] },
    error: { type: String, default: '' },
    /** 'card' = full sidebar card; 'section' = in-form block above other fields */
    variant: { type: String, default: 'section' },
});

const iconFor = (value) => {
    const m = {
        active: 'check_circle',
        inactive: 'block',
        maintenance: 'build',
    };
    return m[value] ?? 'radio_button_checked';
};

const optionButtonClass = (value, isSection) => {
    const base = isSection
        ? 'flex flex-col items-center justify-center gap-1.5 min-h-[5.25rem] py-3 px-2 text-center sm:min-h-[5.5rem]'
        : 'flex w-full min-h-0 items-center gap-2 px-3 py-2.5 text-left';

    return [
        base,
        'rounded-lg border text-sm font-medium transition-colors',
        model.value === value
            ? 'border-primary-600 bg-primary-50 text-primary-900 ring-1 ring-inset ring-primary-600/20 dark:border-primary-500 dark:bg-primary-900/30 dark:text-primary-100'
            : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800/80 dark:text-gray-200 dark:hover:bg-gray-800',
    ];
};
</script>

<template>
    <!-- In-form: own section, 3-tile row on sm+ -->
    <section
        v-if="variant === 'section'"
        class="rounded-lg border border-gray-200 bg-gray-50/80 p-4 dark:border-gray-600 dark:bg-gray-900/20"
    >
        <h3
            class="mb-1 border-b border-gray-200 pb-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-600 dark:text-gray-400"
        >
            Status <span class="text-red-500">*</span>
        </h3>
        <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">
            Whether this unit is in service, offline, or in the shop.
        </p>
        <fieldset>
            <legend class="sr-only">Fleet status</legend>
            <div
                class="grid grid-cols-1 gap-2 sm:grid-cols-3"
                role="radiogroup"
                aria-label="Status"
            >
                <button
                    v-for="o in statuses"
                    :key="o.value"
                    type="button"
                    :aria-pressed="model === o.value"
                    :class="optionButtonClass(o.value, true)"
                    @click="model = o.value"
                >
                    <span
                        class="material-icons shrink-0 sm:text-[24px]"
                        :class="[
                            model === o.value ? 'text-primary-600 dark:text-primary-400' : 'text-gray-400',
                        ]"
                    >{{ iconFor(o.value) }}</span>
                    <span class="w-full min-w-0 break-words leading-tight sm:text-sm">{{ o.label }}</span>
                    <span
                        v-if="model === o.value"
                        class="material-icons shrink-0 text-[16px] text-primary-600 dark:text-primary-400"
                    >check</span>
                </button>
            </div>
            <p v-if="error" class="mt-2 text-sm text-red-600 dark:text-red-400">
                {{ error }}
            </p>
        </fieldset>
    </section>

    <!-- Optional legacy sidebar card (same 3 button behavior, stacked) -->
    <div
        v-else
        class="overflow-hidden bg-white shadow-lg sm:rounded-lg dark:bg-gray-800"
    >
        <div class="border-b border-gray-200 bg-gray-700 px-5 py-4 dark:border-gray-600">
            <span class="text-sm font-semibold text-white">Status</span>
        </div>
        <div class="p-5">
            <fieldset>
                <legend class="sr-only">Fleet status</legend>
                <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">
                    Quick set whether this unit is in service, offline, or in the shop.
                </p>
                <div class="flex flex-col gap-2" role="radiogroup" aria-label="Status">
                    <button
                        v-for="o in statuses"
                        :key="o.value"
                        type="button"
                        :aria-pressed="model === o.value"
                        :class="optionButtonClass(o.value, false)"
                        @click="model = o.value"
                    >
                        <span
                            class="material-icons shrink-0 text-[20px] opacity-90"
                            :class="model === o.value ? 'text-primary-600 dark:text-primary-400' : 'text-gray-400'"
                        >{{ iconFor(o.value) }}</span>
                        <span class="min-w-0 flex-1">{{ o.label }}</span>
                        <span
                            v-if="model === o.value"
                            class="material-icons shrink-0 text-[18px] text-primary-600 dark:text-primary-400"
                        >check</span>
                    </button>
                </div>
                <p v-if="error" class="mt-2 text-sm text-red-600 dark:text-red-400">
                    {{ error }}
                </p>
            </fieldset>
        </div>
    </div>
</template>
