<script setup>
defineProps({
    selectedField: {
        type: Object,
        default: null,
    },
    fieldTypeLabels: {
        type: Object,
        default: () => ({}),
    },
});

const emit = defineEmits(['set-address-layout']);
</script>

<template>
    <div
        data-mso-field-inspector
        class="min-h-[4.5rem] rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm dark:border-gray-700 dark:bg-gray-800"
    >
        <div
            v-if="!selectedField"
            class="flex min-h-[3rem] items-center justify-center gap-2 px-2 text-center"
        >
            <span class="material-icons text-2xl text-gray-300 dark:text-gray-600">touch_app</span>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Select a field on the document to edit its value, font size, and layout.
            </p>
        </div>

        <div v-else class="flex min-h-[3rem] flex-wrap items-center gap-3">
            <template v-if="selectedField.type === 'user_signature'">
                <span class="shrink-0 text-sm font-medium text-gray-900 dark:text-white">
                    {{ fieldTypeLabels[selectedField.type] || selectedField.type }}
                </span>
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    Pulled from the assigned user&apos;s saved signature. Resize on the form.
                </p>
                <p
                    v-if="!selectedField.signature_url && selectedField.signature_method !== 'type'"
                    class="text-xs text-amber-700 dark:text-amber-300"
                >
                    No saved signature on this user yet.
                </p>
            </template>

            <template v-else>
                <span class="shrink-0 text-sm font-medium text-gray-900 dark:text-white">
                    {{ fieldTypeLabels[selectedField.type] || selectedField.type }}
                </span>

                <input
                    v-if="!['customer_address', 'dealership_address', 'line_item', 'free_text'].includes(selectedField.type)"
                    v-model="selectedField.value"
                    type="text"
                    class="min-w-[10rem] flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                    placeholder="Value"
                />
                <textarea
                    v-else
                    v-model="selectedField.value"
                    rows="1"
                    class="min-w-[10rem] flex-1 resize-y rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                    placeholder="Value"
                />

                <div class="flex shrink-0 items-center gap-2">
                    <label class="text-xs font-medium text-gray-600 dark:text-gray-300" :for="`mso-font-size-${selectedField.id}`">
                        Size
                    </label>
                    <input
                        :id="`mso-font-size-${selectedField.id}`"
                        v-model.number="selectedField.font_size"
                        type="number"
                        min="6"
                        max="24"
                        class="w-16 rounded-lg border border-gray-300 px-2 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                    />
                </div>

                <label class="flex shrink-0 cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input
                        v-model="selectedField.font_bold"
                        type="checkbox"
                        class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600"
                    />
                    <span>Bold</span>
                </label>

                <div
                    v-if="['customer_address', 'dealership_address'].includes(selectedField.type)"
                    class="flex shrink-0 items-center gap-2"
                >
                    <button
                        type="button"
                        class="rounded-lg border px-3 py-1.5 text-sm"
                        :class="(selectedField.address_layout ?? 'multiline') === 'multiline'
                            ? 'border-primary-500 bg-primary-50 text-primary-800 dark:bg-primary-900/30 dark:text-primary-100'
                            : 'border-gray-300 text-gray-700 dark:border-gray-600 dark:text-gray-300'"
                        @click="emit('set-address-layout', 'multiline')"
                    >
                        Multi-line
                    </button>
                    <button
                        type="button"
                        class="rounded-lg border px-3 py-1.5 text-sm"
                        :class="selectedField.address_layout === 'single'
                            ? 'border-primary-500 bg-primary-50 text-primary-800 dark:bg-primary-900/30 dark:text-primary-100'
                            : 'border-gray-300 text-gray-700 dark:border-gray-600 dark:text-gray-300'"
                        @click="emit('set-address-layout', 'single')"
                    >
                        Single line
                    </button>
                </div>
            </template>
        </div>
    </div>
</template>
