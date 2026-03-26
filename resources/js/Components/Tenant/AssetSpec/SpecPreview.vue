<script setup>
import { computed } from 'vue';

const props = defineProps({
    specs: Array,
});

const groupedSpecs = computed(() => {
    const groups = {};
    props.specs.forEach(spec => {
        const group = spec.group || 'Other';
        if (!groups[group]) {
            groups[group] = [];
        }
        groups[group].push(spec);
    });
    return groups;
});

const getInputType = (spec) => {
    switch (spec.type) {
        case 'number':
            return 'number';
        case 'text':
            return 'text';
        case 'boolean':
            return 'checkbox';
        case 'select':
            return 'select';
        default:
            return 'text';
    }
};

const getPlaceholder = (spec) => {
    if (spec.type === 'number' && spec.unit) {
        return `Enter value (${spec.unit})`;
    }
    return `Enter ${spec.label.toLowerCase()}`;
};
</script>

<template>
    <div class="rounded-lg bg-white shadow-md dark:bg-gray-800">
        <!-- Header -->
        <div class="border-b border-gray-100 dark:border-gray-700 px-6 py-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                Form Preview
            </h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                How the form will look with selected specs
            </p>
        </div>

        <!-- Preview Form -->
        <div class="overflow-y-auto  p-6">
            <div v-if="specs.length === 0" class="text-center py-12">
                <span class="material-icons text-gray-300 dark:text-gray-600 text-5xl">preview</span>
                <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                    Preview will appear here
                </p>
                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                    Select specs to see the form preview
                </p>
            </div>

            <div v-else class="space-y-6">
                <div v-for="(groupSpecs, groupName) in groupedSpecs" :key="groupName">
                    <!-- Group Header -->
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide mb-4">
                        {{ groupName }}
                    </h3>

                    <!-- Fields in Group -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-4">
                        <div v-for="spec in groupSpecs" :key="spec.id">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                {{ spec.label }}
                                <span v-if="spec.is_required" class="text-red-500">*</span>
                            </label>

                            <!-- Number Input -->
                            <div v-if="spec.type === 'number'" class="flex items-center gap-2">
                                <input
                                    type="number"
                                    :placeholder="getPlaceholder(spec)"
                                    :step="spec.step || 'any'"
                                    class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-primary-500 dark:focus:border-primary-400 focus:ring-primary-500 dark:focus:ring-primary-400"
                                    disabled
                                />
                                <span v-if="spec.unit" class="text-sm text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                    {{ spec.unit }}
                                </span>
                            </div>

                            <!-- Text Input -->
                            <input
                                v-else-if="spec.type === 'text'"
                                type="text"
                                :placeholder="getPlaceholder(spec)"
                                class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-primary-500 dark:focus:border-primary-400 focus:ring-primary-500 dark:focus:ring-primary-400"
                                disabled
                            />

                            <!-- Select -->
                            <select
                                v-else-if="spec.type === 'select'"
                                class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-primary-500 dark:focus:border-primary-400 focus:ring-primary-500 dark:focus:ring-primary-400"
                                disabled
                            >
                                <option value="">Select {{ spec.label.toLowerCase() }}</option>
                                <option v-for="option in spec.options" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>

                            <!-- Boolean/Checkbox -->
                            <div v-else-if="spec.type === 'boolean'" class="flex items-center">
                                <input
                                    type="checkbox"
                                    class="rounded border-gray-300 dark:border-gray-600 text-primary-600 focus:ring-primary-500 dark:focus:ring-primary-400"
                                    disabled
                                />
                                <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">
                                    Yes
                                </span>
                            </div>

                            <!-- Help Text -->
                            <p v-if="spec.is_filterable" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                This field will be available as a marketplace filter
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>