<script setup>
import { ref, computed, watch } from 'vue';
import axios from 'axios';

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
        { key: 'is_empty', label: 'Is empty' },
        { key: 'is_not_empty', label: 'Is not empty' },
    ],
    record: [
        { key: 'equals', label: 'Is' },
        { key: 'not_equals', label: 'Is not' },
        { key: 'is_empty', label: 'Is empty' },
        { key: 'is_not_empty', label: 'Is not empty' },
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
        { key: 'is_empty', label: 'Is empty' },
        { key: 'is_not_empty', label: 'Is not empty' },
    ],
    boolean: [
        { key: 'is_true', label: 'Is checked' },
        { key: 'is_false', label: 'Is not checked' },
    ],
};

// Build filterable fields from ALL fieldsSchema entries, excluding textarea and readOnly
const filterFields = computed(() => {
    const schema = props.fieldsSchema?.fields ?? props.fieldsSchema ?? {};
    return Object.entries(schema)
        .filter(([, def]) => {
            const type = def.type || 'text';
            if (type === 'textarea') return false;
            if (def.readOnly) return false;
            return true;
        })
        .map(([key, def]) => ({
            key,
            label: def.label || key,
            type: def.type || 'text',
            enum: def.enum ?? null,
            typeDomain: def.typeDomain ?? null,
        }));
});

const activeFiltersLocal = ref([]);

const getFieldConfig = (fieldKey) =>
    filterFields.value.find(f => f.key === fieldKey) ?? filterFields.value[0] ?? { type: 'text' };

const getFieldType = (fieldKey) => getFieldConfig(fieldKey).type || 'text';

const getFieldOptions = (fieldConfig) => {
    if (fieldConfig.enum && props.enumOptions[fieldConfig.enum]) {
        return props.enumOptions[fieldConfig.enum] ?? [];
    }
    return [];
};

const noValueOperators = ['is_empty', 'is_not_empty', 'today', 'this_week', 'this_month', 'is_true', 'is_false'];
const multiValueOperators = ['any_of', 'none_of'];

const needsValueInput = (operator) => !noValueOperators.includes(operator);

const getDefaultOperatorForField = (fieldKey) => {
    const type = getFieldType(fieldKey);
    if (type === 'boolean' || type === 'checkbox') return 'is_true';
    return 'equals';
};

const addFilter = () => {
    const first = filterFields.value[0];
    activeFiltersLocal.value.push({
        id: Date.now(),
        field: first?.key || '',
        operator: first ? getDefaultOperatorForField(first.key) : 'equals',
        value: '',
        valueText: '',
    });
};

// Initialize / sync from props
watch(() => props.activeFilters, (newFilters) => {
    if (newFilters && newFilters.length > 0) {
        activeFiltersLocal.value = JSON.parse(JSON.stringify(newFilters)).map(f => ({
            ...f,
            id: f.id || Date.now() + Math.random(),
        }));
    } else if (activeFiltersLocal.value.length === 0) {
        addFilter();
    }
}, { immediate: true });

const removeFilter = (id) => {
    activeFiltersLocal.value = activeFiltersLocal.value.filter(f => f.id !== id);
    if (activeFiltersLocal.value.length === 0) addFilter();
};

const updateFilter = (id, updates) => {
    activeFiltersLocal.value = activeFiltersLocal.value.map(f =>
        f.id === id ? { ...f, ...updates } : f,
    );
};

const clearAll = () => {
    activeFiltersLocal.value = [];
    addFilter();
};

const applyFilters = () => {
    const valid = activeFiltersLocal.value.filter(f => {
        if (!f.field || !f.operator) return false;
        if (noValueOperators.includes(f.operator)) return true;
        if (f.operator === 'between') {
            return f.value && typeof f.value === 'object' && (f.value.start || f.value.min);
        }
        if (multiValueOperators.includes(f.operator)) {
            return Array.isArray(f.value) && f.value.length > 0;
        }
        return f.value !== '' && f.value !== null && f.value !== undefined;
    });
    emit('apply', valid);
};

// ── Multi-select helper (for select any_of/none_of) ──────────────────────────
const isMultiSelectOperator = (op) => multiValueOperators.includes(op);

const getMultiValues = (filter) => Array.isArray(filter.value) ? filter.value : (filter.value ? [filter.value] : []);

const toggleMultiValue = (filterId, optionValue) => {
    const filter = activeFiltersLocal.value.find(f => f.id === filterId);
    if (!filter) return;
    const current = getMultiValues(filter);
    const next = current.includes(optionValue)
        ? current.filter(v => v !== optionValue)
        : [...current, optionValue];
    updateFilter(filterId, { value: next });
};

// ── Inline record search ──────────────────────────────────────────────────────
const recordSearchQueries = ref({});
const recordSearchResults = ref({});
const recordSearchOpen = ref({});
let recordSearchTimers = {};

const searchRecords = async (filterId, typeDomain, q) => {
    recordSearchQueries.value[filterId] = q;
    clearTimeout(recordSearchTimers[filterId]);
    if (!q || q.length < 1) {
        recordSearchResults.value[filterId] = [];
        return;
    }
    recordSearchTimers[filterId] = setTimeout(async () => {
        try {
            const { data } = await axios.get(route('records.lookup'), {
                params: { type: typeDomain, search: q, per_page: 8 },
            });
            recordSearchResults.value[filterId] = data.records ?? data.data ?? [];
        } catch {
            recordSearchResults.value[filterId] = [];
        }
    }, 280);
};

const selectRecordResult = (filterId, record) => {
    updateFilter(filterId, {
        value: record.id,
        valueText: record.display_name ?? String(record.id),
    });
    recordSearchOpen.value[filterId] = false;
    recordSearchQueries.value[filterId] = record.display_name ?? String(record.id);
};

const openRecordSearch = (filterId) => {
    recordSearchOpen.value[filterId] = true;
};

const clearRecordFilter = (filterId) => {
    updateFilter(filterId, { value: '', valueText: '' });
    recordSearchQueries.value[filterId] = '';
    recordSearchResults.value[filterId] = [];
};

// When a record filter's field changes, reset the record search state
watch(activeFiltersLocal, (filters) => {
    filters.forEach(f => {
        if (getFieldType(f.field) !== 'record' && recordSearchOpen.value[f.id]) {
            recordSearchOpen.value[f.id] = false;
        }
    });
}, { deep: true });
</script>

<template>
    <div class="bg-white dark:bg-gray-800">
        <!-- Header -->
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                </svg>
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Filters</h3>
                <span
                    v-if="activeFiltersLocal.filter(f => f.field && (noValueOperators.includes(f.operator) || (Array.isArray(f.value) ? f.value.length > 0 : f.value))).length > 0"
                    class="px-2 py-0.5 text-xs font-medium bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400 rounded-full"
                >
                    {{ activeFiltersLocal.filter(f => f.field && (noValueOperators.includes(f.operator) || (Array.isArray(f.value) ? f.value.length > 0 : f.value))).length }} active
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
            <div
                v-for="(filter, index) in activeFiltersLocal"
                :key="filter.id"
                class="flex items-start gap-3 flex-wrap sm:flex-nowrap"
            >
                <!-- Connector label -->
                <span v-if="index > 0" class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase w-10 shrink-0 pt-2.5">And</span>
                <div v-else class="w-10 shrink-0" />

                <!-- Field Select -->
                <select
                    :value="filter.field"
                    @change="updateFilter(filter.id, { field: $event.target.value, value: '', valueText: '', operator: getDefaultOperatorForField($event.target.value) })"
                    class="flex-1 min-w-[140px] px-3 py-2 text-sm bg-white border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 dark:bg-gray-900 dark:border-gray-700 dark:text-white transition-colors"
                >
                    <option value="">Select field…</option>
                    <option v-for="field in filterFields" :key="field.key" :value="field.key">
                        {{ field.label }}
                    </option>
                </select>

                <!-- Operator Select -->
                <select
                    v-if="filter.field"
                    :value="filter.operator"
                    @change="updateFilter(filter.id, { operator: $event.target.value, value: isMultiSelectOperator($event.target.value) ? [] : '' })"
                    class="w-36 px-3 py-2 text-sm bg-white border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 dark:bg-gray-900 dark:border-gray-700 dark:text-white transition-colors"
                >
                    <option
                        v-for="op in (operators[getFieldType(filter.field)] || operators.text)"
                        :key="op.key"
                        :value="op.key"
                    >
                        {{ op.label }}
                    </option>
                </select>

                <!-- Value input area -->
                <template v-if="filter.field && needsValueInput(filter.operator)">

                    <!-- ── SELECT: single ── -->
                    <select
                        v-if="getFieldType(filter.field) === 'select' && !isMultiSelectOperator(filter.operator)"
                        :value="filter.value"
                        @change="updateFilter(filter.id, { value: $event.target.value })"
                        class="flex-1 min-w-[160px] px-3 py-2 text-sm bg-white border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 dark:bg-gray-900 dark:border-gray-700 dark:text-white transition-colors"
                    >
                        <option value="">Select…</option>
                        <option
                            v-for="opt in getFieldOptions(getFieldConfig(filter.field))"
                            :key="opt.id ?? opt.value"
                            :value="opt.id ?? opt.value"
                        >
                            {{ opt.name ?? opt.label ?? opt.value }}
                        </option>
                    </select>

                    <!-- ── SELECT: multi (any_of / none_of) ── -->
                    <div
                        v-else-if="getFieldType(filter.field) === 'select' && isMultiSelectOperator(filter.operator)"
                        class="flex-1 min-w-[200px]"
                    >
                        <div class="flex flex-wrap gap-1.5 p-2 border border-gray-200 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-900 min-h-[38px]">
                            <span
                                v-for="optVal in getMultiValues(filter)"
                                :key="optVal"
                                class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400 rounded-md"
                            >
                                {{ getFieldOptions(getFieldConfig(filter.field)).find(o => String(o.id ?? o.value) === String(optVal))?.name ?? optVal }}
                                <button type="button" @click="toggleMultiValue(filter.id, optVal)" class="hover:text-primary-900 dark:hover:text-primary-200">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </span>
                            <span v-if="getMultiValues(filter).length === 0" class="text-xs text-gray-400 py-0.5">Select options…</span>
                        </div>
                        <!-- Checkbox list -->
                        <div class="mt-1 border border-gray-200 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-900 divide-y divide-gray-100 dark:divide-gray-700/60 max-h-44 overflow-y-auto">
                            <label
                                v-for="opt in getFieldOptions(getFieldConfig(filter.field))"
                                :key="opt.id ?? opt.value"
                                class="flex items-center gap-2.5 px-3 py-2 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800"
                            >
                                <input
                                    type="checkbox"
                                    :value="opt.id ?? opt.value"
                                    :checked="getMultiValues(filter).map(String).includes(String(opt.id ?? opt.value))"
                                    @change="toggleMultiValue(filter.id, opt.id ?? opt.value)"
                                    class="w-4 h-4 rounded text-primary-600 border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-primary-500"
                                />
                                <div v-if="opt.color" class="flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full shrink-0" :class="`bg-${opt.color}-500`"></span>
                                    <span class="text-sm text-gray-900 dark:text-white">{{ opt.name ?? opt.label }}</span>
                                </div>
                                <span v-else class="text-sm text-gray-900 dark:text-white">{{ opt.name ?? opt.label ?? opt.value }}</span>
                            </label>
                        </div>
                    </div>

                    <!-- ── RECORD: inline search ── -->
                    <div
                        v-else-if="getFieldType(filter.field) === 'record'"
                        class="flex-1 min-w-[200px] relative"
                    >
                        <div class="relative">
                            <input
                                type="text"
                                :value="recordSearchQueries[filter.id] ?? filter.valueText ?? ''"
                                @focus="openRecordSearch(filter.id)"
                                @input="searchRecords(filter.id, getFieldConfig(filter.field).typeDomain, $event.target.value)"
                                :placeholder="`Search ${getFieldConfig(filter.field).label}…`"
                                class="w-full pl-3 pr-8 py-2 text-sm bg-white border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 dark:bg-gray-900 dark:border-gray-700 dark:text-white transition-colors"
                            />
                            <button
                                v-if="filter.value"
                                type="button"
                                @click="clearRecordFilter(filter.id)"
                                class="absolute inset-y-0 right-0 flex items-center pr-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <!-- Search results dropdown -->
                        <div
                            v-if="recordSearchOpen[filter.id] && recordSearchResults[filter.id]?.length"
                            class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg max-h-52 overflow-y-auto"
                        >
                            <button
                                v-for="rec in recordSearchResults[filter.id]"
                                :key="rec.id"
                                type="button"
                                @click="selectRecordResult(filter.id, rec)"
                                class="w-full text-left px-3 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-900 dark:text-white transition-colors"
                            >
                                {{ rec.display_name ?? rec.name ?? rec.id }}
                            </button>
                        </div>
                        <!-- Selected record chip -->
                        <div v-if="filter.value && !recordSearchOpen[filter.id]" class="mt-1">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400 rounded-md">
                                {{ filter.valueText || filter.value }}
                            </span>
                        </div>
                    </div>

                    <!-- ── DATE / DATETIME ── -->
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

                    <!-- ── NUMBER ── -->
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
                            placeholder="Enter value…"
                            class="flex-1 min-w-[160px] px-3 py-2 text-sm bg-white border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 dark:bg-gray-900 dark:border-gray-700 dark:text-white transition-colors"
                        />
                    </template>

                    <!-- ── BOOLEAN: no extra input needed (handled by operator) ── -->
                    <div v-else-if="getFieldType(filter.field) === 'boolean'" class="flex-1 min-w-[160px]" />

                    <!-- ── TEXT / EMAIL / TEL / fallback ── -->
                    <input
                        v-else
                        :type="getFieldType(filter.field) === 'email' ? 'email' : 'text'"
                        :value="filter.value"
                        @input="updateFilter(filter.id, { value: $event.target.value })"
                        placeholder="Enter value…"
                        class="flex-1 min-w-[160px] px-3 py-2 text-sm bg-white border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 dark:bg-gray-900 dark:border-gray-700 dark:text-white transition-colors"
                    />
                </template>

                <!-- Empty spacer for no-value operators -->
                <div v-else-if="filter.field" class="flex-1 min-w-[160px]" />

                <!-- Remove Button -->
                <button
                    @click="removeFilter(filter.id)"
                    class="p-2 mt-0.5 text-gray-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-900/20 rounded-lg transition-colors shrink-0"
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
