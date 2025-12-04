<script setup>
import { ref, computed, watch } from 'vue';

const props = defineProps({
    fieldsSchema: {
        type: Object,
        default: () => ({}),
    },
    enumOptions: {
        type: Object,
        default: () => ({}),
    },
    columns: {
        type: Array,
        default: () => [],
    },
    activeFilters: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['apply', 'close']);

const operators = {
    text: [
        { key: 'contains', label: 'Contains' },
        { key: 'equals', label: 'Equals' },
        { key: 'starts_with', label: 'Starts with' },
        { key: 'ends_with', label: 'Ends with' },
        { key: 'is_empty', label: 'Is empty' },
        { key: 'is_not_empty', label: 'Is not empty' },
    ],
    email: [
        { key: 'contains', label: 'Contains' },
        { key: 'equals', label: 'Equals' },
        { key: 'starts_with', label: 'Starts with' },
        { key: 'ends_with', label: 'Ends with' },
        { key: 'is_empty', label: 'Is empty' },
        { key: 'is_not_empty', label: 'Is not empty' },
    ],
    tel: [
        { key: 'contains', label: 'Contains' },
        { key: 'equals', label: 'Equals' },
        { key: 'is_empty', label: 'Is empty' },
        { key: 'is_not_empty', label: 'Is not empty' },
    ],
    select: [
        { key: 'equals', label: 'Is' },
        { key: 'not_equals', label: 'Is not' },
        { key: 'any_of', label: 'Is any of' },
        { key: 'none_of', label: 'Is none of' },
    ],
    datetime: [
        { key: 'equals', label: 'Is' },
        { key: 'before', label: 'Before' },
        { key: 'after', label: 'After' },
        { key: 'between', label: 'Between' },
        { key: 'today', label: 'Is today' },
        { key: 'this_week', label: 'This week' },
        { key: 'this_month', label: 'This month' },
    ],
    date: [
        { key: 'equals', label: 'Is' },
        { key: 'before', label: 'Before' },
        { key: 'after', label: 'After' },
        { key: 'between', label: 'Between' },
        { key: 'today', label: 'Is today' },
        { key: 'this_week', label: 'This week' },
        { key: 'this_month', label: 'This month' },
    ],
    number: [
        { key: 'equals', label: 'Equals' },
        { key: 'not_equals', label: 'Not equals' },
        { key: 'greater_than', label: 'Greater than' },
        { key: 'less_than', label: 'Less than' },
        { key: 'between', label: 'Between' },
    ],
    boolean: [
        { key: 'is_true', label: 'Is checked' },
        { key: 'is_false', label: 'Is not checked' },
    ],
};

const filterFields = computed(() => {
    // Get filterable fields from columns (exclude id)
    return props.columns
        .filter(col => col.key !== 'id')
        .map(col => {
            const fieldDef = props.fieldsSchema[col.key] || {};
            return {
                key: col.key,
                label: col.label || fieldDef.label || col.key,
                type: fieldDef.type || 'text',
                enum: fieldDef.enum,
            };
        });
});

const activeFiltersLocal = ref([]);

const getFieldConfig = (fieldKey) => {
    return filterFields.value.find(f => f.key === fieldKey) || filterFields.value[0] || { type: 'text' };
};

const getFieldOptions = (fieldConfig) => {
    if (fieldConfig.enum && props.enumOptions[fieldConfig.enum]) {
        return props.enumOptions[fieldConfig.enum] || [];
    }
    return [];
};

const addFilter = () => {
    const firstField = filterFields.value[0];
    activeFiltersLocal.value.push({
        id: Date.now(),
        field: firstField?.key || '',
        operator: 'equals',
        value: '',
    });
};

// Initialize from props
watch(() => props.activeFilters, (newFilters) => {
    if (newFilters && newFilters.length > 0) {
        activeFiltersLocal.value = JSON.parse(JSON.stringify(newFilters));
    } else if (activeFiltersLocal.value.length === 0) {
        // Add initial empty filter if none exist
        addFilter();
    }
}, { immediate: true });

const removeFilter = (id) => {
    activeFiltersLocal.value = activeFiltersLocal.value.filter(f => f.id !== id);
    if (activeFiltersLocal.value.length === 0) {
        addFilter();
    }
};

const updateFilter = (id, updates) => {
    activeFiltersLocal.value = activeFiltersLocal.value.map(f =>
        f.id === id ? { ...f, ...updates } : f
    );
};

const clearAll = () => {
    activeFiltersLocal.value = [];
    addFilter();
};

const applyFilters = () => {
    // Filter out empty filters
    const validFilters = activeFiltersLocal.value.filter(f => {
        if (!f.field || !f.operator) return false;
        // Some operators don't need values
        if (['is_empty', 'is_not_empty', 'today', 'this_week', 'this_month', 'is_true', 'is_false'].includes(f.operator)) {
            return true;
        }
        // Between operators need object values
        if (f.operator === 'between') {
            return f.value && (typeof f.value === 'object' ? (f.value.start || f.value.min) : false);
        }
        return f.value !== '' && f.value !== null && f.value !== undefined;
    });
    emit('apply', validFilters);
};

const getFieldType = (fieldKey) => {
    const fieldConfig = getFieldConfig(fieldKey);
    return fieldConfig.type || 'text';
};

const needsValueInput = (operator) => {
    return !['is_empty', 'is_not_empty', 'today', 'this_week', 'this_month', 'is_true', 'is_false'].includes(operator);
};
</script>

<template>
    <div class=" bg-white ">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                </svg>
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Filters</h3>
                <span v-if="activeFiltersLocal.filter(f => f.value && f.field).length > 0" class="px-2 py-0.5 text-xs font-medium bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400 rounded-full">
                    {{ activeFiltersLocal.filter(f => f.value && f.field).length }} active
                </span>
            </div>
            <button
                @click="$emit('close')"
                class="p-1.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Filter Rows -->
        <div class="space-y-3">
            <div v-for="(filter, index) in activeFiltersLocal" :key="filter.id" class="flex items-center gap-3 flex-wrap sm:flex-nowrap">
                <!-- Connector -->
                <span v-if="index > 0" class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase w-10 shrink-0">
                    And
                </span>
                <div v-if="index === 0" class="w-10 shrink-0" />

                <!-- Field Select -->
                <select
                    :value="filter.field"
                    @change="updateFilter(filter.id, { field: $event.target.value, value: '', operator: 'equals' })"
                    class="flex-1 min-w-[140px] px-3 py-2 text-sm bg-white border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 dark:bg-gray-900 dark:border-gray-700 dark:text-white transition-colors"
                >
                    <option value="">Select field...</option>
                    <option v-for="field in filterFields" :key="field.key" :value="field.key">
                        {{ field.label }}
                    </option>
                </select>

                <!-- Operator Select -->
                <select
                    v-if="filter.field"
                    :value="filter.operator"
                    @change="updateFilter(filter.id, { operator: $event.target.value })"
                    class="w-36 px-3 py-2 text-sm bg-white border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 dark:bg-gray-900 dark:border-gray-700 dark:text-white transition-colors"
                >
                    <option v-for="op in (operators[getFieldType(filter.field)] || operators.text)" :key="op.key" :value="op.key">
                        {{ op.label }}
                    </option>
                </select>

                <!-- Value Input -->
                <template v-if="filter.field && needsValueInput(filter.operator)">
                    <!-- Select field -->
                    <select
                        v-if="getFieldType(filter.field) === 'select'"
                        :value="filter.value"
                        @change="updateFilter(filter.id, { value: $event.target.value })"
                        class="flex-1 min-w-[160px] px-3 py-2 text-sm bg-white border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 dark:bg-gray-900 dark:border-gray-700 dark:text-white transition-colors"
                    >
                        <option value="">Select...</option>
                        <option v-for="opt in getFieldOptions(getFieldConfig(filter.field))" :key="opt.id || opt.value" :value="opt.id || opt.value">
                            {{ opt.name || opt.label || opt.value }}
                        </option>
                    </select>

                    <!-- Date field -->
                    <template v-else-if="getFieldType(filter.field) === 'date' || getFieldType(filter.field) === 'datetime'">
                        <div v-if="filter.operator === 'between'" class="flex items-center gap-2 flex-1 min-w-[280px]">
                            <input
                                type="date"
                                :value="filter.value?.start || ''"
                                @change="updateFilter(filter.id, { value: { ...filter.value, start: $event.target.value } })"
                                class="flex-1 min-w-0 px-3 py-2 text-sm bg-white border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 dark:bg-gray-900 dark:border-gray-700 dark:text-white transition-colors"
                            />
                            <span class="text-gray-400 text-sm">to</span>
                            <input
                                type="date"
                                :value="filter.value?.end || ''"
                                @change="updateFilter(filter.id, { value: { ...filter.value, end: $event.target.value } })"
                                class="flex-1 min-w-0 px-3 py-2 text-sm bg-white border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 dark:bg-gray-900 dark:border-gray-700 dark:text-white transition-colors"
                            />
                        </div>
                        <input
                            v-else
                            type="date"
                            :value="filter.value"
                            @change="updateFilter(filter.id, { value: $event.target.value })"
                            class="flex-1 min-w-[160px] px-3 py-2 text-sm bg-white border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 dark:bg-gray-900 dark:border-gray-700 dark:text-white transition-colors"
                        />
                    </template>

                    <!-- Number field -->
                    <template v-else-if="getFieldType(filter.field) === 'number'">
                        <div v-if="filter.operator === 'between'" class="flex items-center gap-2 flex-1 min-w-[240px]">
                            <input
                                type="number"
                                :value="filter.value?.min || ''"
                                @change="updateFilter(filter.id, { value: { ...filter.value, min: $event.target.value } })"
                                placeholder="Min"
                                class="flex-1 min-w-0 px-3 py-2 text-sm bg-white border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 dark:bg-gray-900 dark:border-gray-700 dark:text-white transition-colors"
                            />
                            <span class="text-gray-400 text-sm">to</span>
                            <input
                                type="number"
                                :value="filter.value?.max || ''"
                                @change="updateFilter(filter.id, { value: { ...filter.value, max: $event.target.value } })"
                                placeholder="Max"
                                class="flex-1 min-w-0 px-3 py-2 text-sm bg-white border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 dark:bg-gray-900 dark:border-gray-700 dark:text-white transition-colors"
                            />
                        </div>
                        <input
                            v-else
                            type="number"
                            :value="filter.value"
                            @change="updateFilter(filter.id, { value: $event.target.value })"
                            placeholder="Enter value..."
                            class="flex-1 min-w-[160px] px-3 py-2 text-sm bg-white border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 dark:bg-gray-900 dark:border-gray-700 dark:text-white transition-colors"
                        />
                    </template>

                    <!-- Text/Email/Tel field -->
                    <input
                        v-else
                        :type="getFieldType(filter.field) === 'email' ? 'email' : 'text'"
                        :value="filter.value"
                        @input="updateFilter(filter.id, { value: $event.target.value })"
                        placeholder="Enter value..."
                        class="flex-1 min-w-[160px] px-3 py-2 text-sm bg-white border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 dark:bg-gray-900 dark:border-gray-700 dark:text-white transition-colors"
                    />
                </template>

                <!-- Empty space for operators that don't need values -->
                <div v-else-if="filter.field" class="flex-1 min-w-[160px]" />

                <!-- Remove Button -->
                <button
                    @click="removeFilter(filter.id)"
                    class="p-2 text-gray-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-900/20 rounded-lg transition-colors shrink-0"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-between mt-5 pt-4 border-t border-gray-100 dark:border-gray-700/50">
            <button
                @click="addFilter"
                class="inline-flex items-center gap-1.5 text-sm font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300 transition-colors"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Add filter
            </button>
            <div class="flex items-center gap-3">
                <button
                    v-if="activeFiltersLocal.length > 0"
                    @click="clearAll"
                    class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200 transition-colors"
                >
                    Clear all
                </button>
                <button
                    @click="applyFilters"
                    class="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 focus:ring-4 focus:ring-primary-300/50 dark:focus:ring-primary-800/50 transition-all duration-200 shadow-sm"
                >
                    Apply Filters
                </button>
            </div>
        </div>
    </div>
</template>

