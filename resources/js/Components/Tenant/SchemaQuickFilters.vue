<script setup>
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue';

const props = defineProps({
    filterDefs: { type: Array, default: () => [] },
    fieldsSchema: { type: Object, default: () => ({}) },
    enumOptions: { type: Object, default: () => ({}) },
    modelValue: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:modelValue']);

const openQuickFilter = ref(null);
const enumQuickSearch = reactive({});

const activeFilters = computed({
    get: () => props.modelValue ?? [],
    set: (value) => emit('update:modelValue', value),
});

const quickFilterKey = (qf) => qf?.field ?? qf?.key ?? '';

const schemaFields = computed(() => props.fieldsSchema?.fields ?? props.fieldsSchema ?? {});

const quickFilterFieldDef = (qf) => {
    const key = quickFilterKey(qf);
    const base = key ? (schemaFields.value[key] ?? {}) : {};
    if (!qf || typeof qf !== 'object') {
        return base;
    }

    return {
        ...base,
        ...(qf.label ? { label: qf.label } : {}),
        ...(qf.type ? { type: qf.type } : {}),
        ...(qf.typeDomain ? { typeDomain: qf.typeDomain } : {}),
        ...(qf.enum ? { enum: qf.enum } : {}),
    };
};

const getQuickFieldDef = (fieldOrQf) => {
    if (fieldOrQf && typeof fieldOrQf === 'object' && (fieldOrQf.field || fieldOrQf.key)) {
        return quickFilterFieldDef(fieldOrQf);
    }

    return schemaFields.value[fieldOrQf] ?? {};
};

const getQuickFilterLabel = (qf) => qf?.label || getQuickFieldDef(quickFilterKey(qf)).label || quickFilterKey(qf);

const getQuickFilterOptions = (qf) => {
    const fieldKey = quickFilterKey(qf);
    if (qf?.enum && props.enumOptions[qf.enum]) {
        return props.enumOptions[qf.enum];
    }
    const fd = getQuickFieldDef(fieldKey);
    if (fd.enum && props.enumOptions[fd.enum]) {
        return props.enumOptions[fd.enum];
    }

    return [];
};

const getQuickActiveValues = (fieldKey) => {
    const f = activeFilters.value.find((x) => x.field === fieldKey && (x.operator === 'any_of' || x.operator === 'equals'));
    if (!f) {
        return [];
    }

    return Array.isArray(f.value) ? f.value.map(String) : (f.value ? [String(f.value)] : []);
};

const applyFilters = (filters) => {
    activeFilters.value = filters;
};

const toggleQuickValue = (fieldKey, optionId) => {
    const strId = String(optionId);
    const current = getQuickActiveValues(fieldKey);
    const next = current.includes(strId) ? current.filter((v) => v !== strId) : [...current, strId];
    const others = activeFilters.value.filter((f) => f.field !== fieldKey);

    applyFilters(next.length ? [...others, { id: Date.now(), field: fieldKey, operator: 'any_of', value: next }] : others);
};

const clearQuickFilter = (fieldKey) => {
    applyFilters(activeFilters.value.filter((f) => f.field !== fieldKey));
};

const isBooleanQuickFilter = (qf) => {
    const key = quickFilterKey(qf);
    const fd = getQuickFieldDef(key);

    return fd?.type === 'boolean' && getQuickFilterOptions(qf).length === 0;
};

const isEnumQuickFilter = (qf) => !isBooleanQuickFilter(qf) && getQuickFilterOptions(qf).length > 0;

const booleanQuickModes = (qf) => {
    const key = quickFilterKey(qf);
    const chipLabel = getQuickFilterLabel(qf);
    const fieldLabel = getQuickFieldDef(key).label || key;

    if (chipLabel && chipLabel !== fieldLabel) {
        return [
            { operator: 'is_false', name: chipLabel },
            { operator: 'is_true', name: fieldLabel },
        ];
    }

    return [
        { operator: 'is_false', name: `Not ${fieldLabel}` },
        { operator: 'is_true', name: fieldLabel },
    ];
};

const getQuickUnaryOperator = (fieldKey) => {
    const f = activeFilters.value.find((x) => x.field === fieldKey && ['is_true', 'is_false'].includes(x.operator));

    return f?.operator ?? null;
};

const setQuickUnaryOperator = (fieldKey, operator) => {
    const others = activeFilters.value.filter((x) => x.field !== fieldKey);
    applyFilters(operator ? [...others, { id: Date.now(), field: fieldKey, operator }] : others);
};

const quickFilterIsActive = (qf) => {
    const key = quickFilterKey(qf);

    return getQuickUnaryOperator(key) !== null || getQuickActiveValues(key).length > 0;
};

const toggleQuickFilterDropdown = (qf) => {
    const fieldKey = quickFilterKey(qf);
    const next = openQuickFilter.value === fieldKey ? null : fieldKey;
    openQuickFilter.value = next;
    if (next && enumQuickSearch[next] === undefined) {
        enumQuickSearch[next] = '';
    }
};

const shouldShowEnumQuickSearch = (qf) => qf.type === 'multi-select' || getQuickFilterOptions(qf).length > 12;

const filteredQuickEnumOptions = (qf) => {
    const opts = getQuickFilterOptions(qf);
    const key = quickFilterKey(qf);
    const q = String(enumQuickSearch[key] ?? '').trim().toLowerCase();
    if (!q) {
        return opts;
    }

    return opts.filter((o) => String(o.name ?? o.label ?? o.value ?? '').toLowerCase().includes(q));
};

const handleQuickFilterClickOutside = (e) => {
    if (!e.target.closest('[data-quick-filter]')) {
        openQuickFilter.value = null;
    }
};

onMounted(() => document.addEventListener('click', handleQuickFilterClickOutside));
onUnmounted(() => document.removeEventListener('click', handleQuickFilterClickOutside));
</script>

<template>
    <div v-if="filterDefs.length" class="flex min-w-0 flex-wrap items-center gap-2">
        <div
            v-for="qf in filterDefs"
            :key="quickFilterKey(qf)"
            class="relative shrink-0"
            data-quick-filter
        >
            <button
                type="button"
                :class="[
                    'inline-flex items-center gap-1.5 rounded-lg border px-3 py-2 text-sm font-medium transition-colors',
                    quickFilterIsActive(qf)
                        ? 'border-primary-400 bg-primary-50 text-primary-700 dark:bg-primary-900/20 dark:text-primary-300'
                        : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700',
                ]"
                @click.stop="toggleQuickFilterDropdown(qf)"
            >
                {{ getQuickFilterLabel(qf) }}
                <span
                    v-if="quickFilterIsActive(qf)"
                    class="rounded-full bg-primary-100 px-1.5 py-0.5 text-[10px] font-semibold text-primary-700 dark:bg-primary-900/30 dark:text-primary-400"
                >
                    {{ getQuickUnaryOperator(quickFilterKey(qf)) ? '1' : getQuickActiveValues(quickFilterKey(qf)).length }}
                </span>
                <svg
                    class="h-3.5 w-3.5 opacity-60"
                    :class="openQuickFilter === quickFilterKey(qf) ? 'rotate-180' : ''"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>

            <div
                v-if="openQuickFilter === quickFilterKey(qf) && isBooleanQuickFilter(qf)"
                class="absolute left-0 top-full z-[100] mt-1.5 min-w-[200px] overflow-hidden rounded-xl border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800"
            >
                <button
                    type="button"
                    class="flex w-full px-3 py-2.5 text-left text-sm transition-colors hover:bg-gray-50 dark:hover:bg-gray-700/50"
                    :class="!getQuickUnaryOperator(quickFilterKey(qf)) ? 'bg-primary-50/80 font-medium text-primary-800 dark:bg-primary-900/25 dark:text-primary-200' : 'text-gray-900 dark:text-white'"
                    @click="setQuickUnaryOperator(quickFilterKey(qf), null); openQuickFilter = null"
                >
                    All
                </button>
                <button
                    v-for="mode in booleanQuickModes(qf)"
                    :key="mode.operator"
                    type="button"
                    class="flex w-full px-3 py-2.5 text-left text-sm transition-colors hover:bg-gray-50 dark:hover:bg-gray-700/50"
                    :class="getQuickUnaryOperator(quickFilterKey(qf)) === mode.operator ? 'bg-primary-50/80 font-medium text-primary-800 dark:bg-primary-900/25 dark:text-primary-200' : 'text-gray-900 dark:text-white'"
                    @click="setQuickUnaryOperator(quickFilterKey(qf), mode.operator); openQuickFilter = null"
                >
                    {{ mode.name }}
                </button>
            </div>

            <div
                v-else-if="openQuickFilter === quickFilterKey(qf) && isEnumQuickFilter(qf)"
                class="absolute left-0 top-full z-[100] mt-1.5 min-w-[180px] overflow-hidden rounded-xl border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800"
            >
                <div
                    v-if="shouldShowEnumQuickSearch(qf)"
                    class="border-b border-gray-100 p-2 dark:border-gray-700/60"
                >
                    <input
                        v-model="enumQuickSearch[quickFilterKey(qf)]"
                        type="search"
                        placeholder="Search…"
                        class="input-style py-1.5 text-sm"
                        @click.stop
                    />
                </div>
                <div class="max-h-64 divide-y divide-gray-50 overflow-y-auto dark:divide-gray-700/60">
                    <label
                        v-for="opt in filteredQuickEnumOptions(qf)"
                        :key="opt.id ?? opt.value"
                        class="flex cursor-pointer items-center gap-2.5 px-3 py-2.5 transition-colors hover:bg-gray-50 dark:hover:bg-gray-700/50"
                    >
                        <input
                            type="checkbox"
                            :value="opt.id ?? opt.value"
                            :checked="getQuickActiveValues(quickFilterKey(qf)).includes(String(opt.id ?? opt.value))"
                            class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700"
                            @change="toggleQuickValue(quickFilterKey(qf), opt.id ?? opt.value)"
                        />
                        <span class="text-sm text-gray-900 dark:text-white">{{ opt.name ?? opt.label ?? opt.value }}</span>
                    </label>
                </div>
                <div v-if="getQuickActiveValues(quickFilterKey(qf)).length" class="border-t border-gray-100 px-3 py-2 dark:border-gray-700/60">
                    <button
                        type="button"
                        class="text-xs font-medium text-primary-600 hover:text-primary-800 dark:text-primary-400 dark:hover:text-primary-300"
                        @click="clearQuickFilter(quickFilterKey(qf)); openQuickFilter = null"
                    >
                        Clear
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
