<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    specs: Array,
    groups: Array,
    types: Array,
    selectedSpecs: Array,
    assetType: Number,
});

const emit = defineEmits(['add-spec']);

const searchQuery = ref('');
const selectedGroup = ref('');
const selectedType = ref('');

const filteredSpecs = computed(() => {
    let result = props.specs;

    // Filter by asset type
    if (props.assetType) {
        result = result.filter(spec =>
            spec.asset_types && spec.asset_types.includes(props.assetType)
        );
    }

    // Search filter
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        result = result.filter(spec =>
            spec.label.toLowerCase().includes(query) ||
            spec.key.toLowerCase().includes(query) ||
            (spec.group && spec.group.toLowerCase().includes(query))
        );
    }

    // Group filter
    if (selectedGroup.value) {
        result = result.filter(spec => spec.group === selectedGroup.value);
    }

    // Type filter
    if (selectedType.value) {
        result = result.filter(spec => spec.type === selectedType.value);
    }

    return result;
});

const groupedSpecs = computed(() => {
    const groups = {};
    filteredSpecs.value.forEach(spec => {
        const group = spec.group || 'Other';
        if (!groups[group]) {
            groups[group] = [];
        }
        groups[group].push(spec);
    });
    return groups;
});

const isSelected = (specId) => {
    // Check local selected state OR the spec's own is_visible flag
    return props.selectedSpecs.some(s => s.id === specId)
        || (props.specs || []).some(s => s.id === specId && s.is_visible);
};

const clearFilters = () => {
    searchQuery.value = '';
    selectedGroup.value = '';
    selectedType.value = '';
};
</script>

<template>
    <div class="rounded-lg bg-white shadow-md dark:bg-gray-800">
        <!-- Header -->
        <div class="border-b border-gray-100 dark:border-gray-700 px-6 py-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                Spec Library
            </h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ filteredSpecs.length }} available specs
            </p>
        </div>

        <!-- Filters -->
        <div class="border-b border-gray-100 dark:border-gray-700 px-6 py-4 space-y-3">
            <!-- Search -->
            <div>
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Search specs..."
                    class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm shadow-sm focus:border-primary-500 dark:focus:border-primary-400 focus:ring-primary-500 dark:focus:ring-primary-400"
                />
            </div>

            <!-- Group Filter -->
            <div>
                <select
                    v-model="selectedGroup"
                    class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm shadow-sm focus:border-primary-500 dark:focus:border-primary-400 focus:ring-primary-500 dark:focus:ring-primary-400"
                >
                    <option value="">All groups</option>
                    <option v-for="group in groups" :key="group" :value="group">
                        {{ group }}
                    </option>
                </select>
            </div>

            <!-- Type Filter -->
            <div>
                <select
                    v-model="selectedType"
                    class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm shadow-sm focus:border-primary-500 dark:focus:border-primary-400 focus:ring-primary-500 dark:focus:ring-primary-400"
                >
                    <option value="">All types</option>
                    <option v-for="type in types" :key="type" :value="type">
                        {{ type }}
                    </option>
                </select>
            </div>

            <!-- Clear Filters -->
            <button
                v-if="searchQuery || selectedGroup || selectedType"
                @click="clearFilters"
                class="text-sm text-primary-600 hover:text-primary-700 dark:text-primary-400 font-medium"
            >
                Clear filters
            </button>
        </div>

        <!-- Spec List -->
        <div class="overflow-y-auto max-h-[600px]">
            <div v-for="(specs, groupName) in groupedSpecs" :key="groupName" class="border-b border-gray-100 dark:border-gray-700 last:border-b-0">
                <!-- Group Header -->
                <div class="bg-gray-50 dark:bg-gray-700/50 px-6 py-2">
                    <h3 class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">
                        {{ groupName }}
                    </h3>
                </div>

                <!-- Specs in Group -->
                <div>
                    <div
                        v-for="spec in specs"
                        :key="spec.id"
                        class="px-6 py-3 hover:bg-gray-50 dark:hover:bg-gray-700/50 border-b border-gray-50 dark:border-gray-700/50 last:border-b-0"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <h4 class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ spec.label }}
                                    </h4>
                                    <span
                                        v-if="spec.is_required"
                                        class="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800 dark:bg-red-900/40 dark:text-red-300"
                                    >
                                        Required
                                    </span>
                                    <span
                                        v-if="spec.is_filterable"
                                        class="inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900/40 dark:text-blue-300"
                                    >
                                        Filterable
                                    </span>
                                </div>
                                <div class="mt-1 flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                    <span class="font-mono">{{ spec.key }}</span>
                                    <span>•</span>
                                    <span class="capitalize">{{ spec.type }}</span>
                                    <span v-if="spec.unit">• {{ spec.unit }}</span>
                                </div>
                            </div>

                            <button
                                @click="emit('add-spec', spec)"
                                :disabled="isSelected(spec.id)"
                                :class="[
                                    'flex-shrink-0 inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium rounded-lg transition-colors',
                                    isSelected(spec.id)
                                        ? 'bg-gray-100 text-gray-400 cursor-not-allowed dark:bg-gray-700 dark:text-gray-500'
                                        : 'bg-primary-600 text-white hover:bg-primary-700'
                                ]"
                            >
                                <span class="material-icons text-[14px]">
                                    {{ isSelected(spec.id) ? 'check' : 'add' }}
                                </span>
                                {{ isSelected(spec.id) ? 'Added' : 'Add' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="filteredSpecs.length === 0" class="px-6 py-12 text-center">
                <span class="material-icons text-gray-300 dark:text-gray-600 text-5xl">search_off</span>
                <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                    No specs found matching your filters
                </p>
                <button
                    @click="clearFilters"
                    class="mt-3 text-sm text-primary-600 hover:text-primary-700 dark:text-primary-400 font-medium"
                >
                    Clear filters
                </button>
            </div>
        </div>
    </div>
</template>
