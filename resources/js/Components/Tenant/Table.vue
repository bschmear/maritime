<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch, onMounted, onUnmounted, getCurrentInstance } from 'vue';
import axios from 'axios';
import Modal from '@/Components/Modal.vue';
import Form from '@/Components/Tenant/Form.vue';
import AssetForm from '@/Components/Tenant/AssetForm.vue';
import FiltersModal from '@/Components/Tenant/FiltersModal.vue';
import { buildResourceRouteParams } from '@/utils/resourceRoutes.js';

const props = defineProps({
    records:             { type: Object, required: true },
    schema:              { type: Object, default: null },
    formSchema:          { type: Object, default: null },
    fieldsSchema:        { type: Object, default: () => ({}) },
    enumOptions:         { type: Object, default: () => ({}) },
    recordType:          { type: String, default: '' },
    pluralTitle:         { type: String, default: '' },
    recordTitle:         { type: String, default: '' },
    createModal:         { type: Boolean, default: true },
    extraRouteParams:    { type: Object, default: () => ({}) },
    initialCreateData:   { type: Object, default: () => ({}) },
    createAvailableSpecs:{ type: Array,  default: () => [] },
    /** Merged with page.props.stats; used with schema.stats for optional stat cards */
    stats:               { type: Object, default: () => ({}) },
});

const showCreateModal  = ref(false);
const showSuccessModal = ref(false);
const showViewModal    = ref(false);
const showFiltersModal = ref(false);
const createdRecordId  = ref(null);
const selectedRecords  = ref(new Set());
const selectAll        = ref(false);
const selectedRecord   = ref(null);
const selectedRecordImageUrls = ref({});
const activeFilters    = ref([]);
const isLoadingRecord  = ref(false);
const searchQuery      = ref('');
const sortKey          = ref('');
const sortDir          = ref('asc');

const page = usePage();

const { $formatCurrency } = getCurrentInstance().appContext.config.globalProperties;

const recordFormComponent = computed(() => props.recordType === 'assets' ? AssetForm : Form);
const columns     = computed(() => props.schema?.columns ?? []);
/** All stat defs (including `hidden: true` used only for subtitle_key / backend values). */
const statCardDefsAll = computed(() => {
    const raw = props.schema?.stats;
    return Array.isArray(raw) ? raw : [];
});

/** Cards rendered in the grid (hidden defs still contribute to resolvedStats). */
const statCardDefs = computed(() => statCardDefsAll.value.filter((s) => s.hidden !== true));

const resolvedStats = computed(() => ({
    ...(page.props.stats ?? {}),
    ...props.stats,
}));

const statNumericValue = (key) => {
    const v = resolvedStats.value?.[key];
    const n = Number(v);
    return Number.isFinite(n) ? n : 0;
};

/**
 * Renders a stat value using schema: aggregate count vs sum, format currency/number.
 */
const displayStatValue = (st) => {
    const raw = resolvedStats.value?.[st.key];
    const aggregate = (st.aggregate || 'count').toString().toLowerCase();
    if (aggregate === 'sum') {
        const n = raw == null || raw === '' ? NaN : Number(raw);
        const num = Number.isFinite(n) ? n : 0;
        const fmt = (st.format || 'currency').toString().toLowerCase();
        if (fmt === 'currency' && typeof $formatCurrency === 'function') {
            return $formatCurrency(num);
        }
        if (fmt === 'number') {
            return num.toLocaleString('en-US', { maximumFractionDigits: 2 });
        }
        return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    return String(Math.round(statNumericValue(st.key)));
};

const quickFilterKey = (qf) => (qf?.field ?? qf?.key ?? '');

/** Pill/badge on stat cards (Flowbite-style). */
const statBadgeClass = (color) => {
    const c = (color || 'gray').toString().toLowerCase();
    const m = {
        green: 'rounded-sm bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900 dark:text-green-300',
        red: 'rounded-sm bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800 dark:bg-red-900 dark:text-red-300',
        yellow: 'rounded-sm bg-yellow-100 px-2.5 py-0.5 text-xs font-medium text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
        amber: 'rounded-sm bg-yellow-100 px-2.5 py-0.5 text-xs font-medium text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
        gray: 'rounded-sm bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800 dark:bg-gray-700 dark:text-gray-300',
        blue: 'rounded-sm bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900 dark:text-blue-300',
        primary: 'rounded-sm bg-primary-100 px-2.5 py-0.5 text-xs font-medium text-primary-800 dark:bg-primary-900 dark:text-primary-300',
        teal: 'rounded-sm bg-teal-100 px-2.5 py-0.5 text-xs font-medium text-teal-800 dark:bg-teal-900 dark:text-teal-300',
        purple: 'rounded-sm bg-purple-100 px-2.5 py-0.5 text-xs font-medium text-purple-800 dark:bg-purple-900 dark:text-purple-300',
        orange: 'rounded-sm bg-orange-100 px-2.5 py-0.5 text-xs font-medium text-orange-800 dark:bg-orange-900 dark:text-orange-300',
    };
    return m[c] ?? m.gray;
};

/** Optional second line, e.g. "350 invoices", driven by `subtitle_key` → another stat key. */
const statSubtitleLine = (st) => {
    const sk = st.subtitle_key;
    if (!sk || typeof sk !== 'string') {
        return null;
    }
    const n = Math.round(statNumericValue(sk));
    const unit = (st.subtitle_unit || 'invoices').toString().toLowerCase();
    if (unit === 'invoice' || unit === 'invoices') {
        const word = n === 1 ? 'invoice' : 'invoices';
        return `${n.toLocaleString('en-US')} ${word}`;
    }
    return `${n.toLocaleString('en-US')} ${unit}`;
};
const modalMaxWidth = computed(() => props.formSchema?.settings?.max_width ?? '4xl');
const hasRecords  = computed(() => props.records.data?.length > 0);
const showEmptyState = computed(() => !hasRecords.value && !activeFilters.value.length && !searchQuery.value);
const showFilteredOutState = computed(() => !hasRecords.value && (activeFilters.value.length > 0 || searchQuery.value));

// ── Enum helpers ──────────────────────────────────────────────────────────────
const getEnumOption = (fieldKey, value) => {
    const fieldDef = props.fieldsSchema[fieldKey];
    if (!fieldDef?.enum || !value) return null;
    const opts = props.enumOptions[fieldDef.enum];
    if (!Array.isArray(opts)) return null;
    return opts.find(o => o.id === value || o.value === value) ?? null;
};

const getEnumLabel = (fieldKey, value) => getEnumOption(fieldKey, value)?.name ?? value;

const getColorClass = (color) => {
    if (!color) return '';
    const map = { blue:'bg-blue-500', green:'bg-green-500', teal:'bg-teal-500', gray:'bg-gray-500',
                  purple:'bg-purple-500', yellow:'bg-yellow-500', orange:'bg-orange-500',
                  red:'bg-red-500', pink:'bg-pink-500', primary:'bg-primary-500' };
    return map[color] || `bg-${color}-500`;
};

// ── Formatters ────────────────────────────────────────────────────────────────
const formatPhoneNumber = (v) => {
    if (!v) return '';
    const n = v.replace(/\D/g, '');
    if (n.length <= 3) return n;
    if (n.length <= 6) return `(${n.slice(0,3)}) ${n.slice(3)}`;
    return `(${n.slice(0,3)}) ${n.slice(3,6)}-${n.slice(6,10)}`;
};

const formatDate = (v) => {
    if (!v) return '—';
    try {
        const d = /^\d{4}-\d{2}-\d{2}$/.test(v) ? new Date(v + 'T00:00:00') : new Date(v);
        if (isNaN(d.getTime())) return v;
        return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
    } catch { return v; }
};

const formatDateTime = (v) => {
    if (!v) return '—';
    try {
        const d = new Date(v);
        if (isNaN(d.getTime())) return v;
        return d.toLocaleString('en-US', { year:'numeric', month:'short', day:'numeric', hour:'numeric', minute:'2-digit', hour12:true });
    } catch { return v; }
};

const getRecordValue = (record, column) => {
    const key = typeof column === 'string' ? column : column.key;
    const raw = record[key] ?? '';
    if (raw === '' || raw === null || raw === undefined) return '—';
    const fd = props.fieldsSchema[key];
    if (fd?.enum && raw !== '') return getEnumLabel(key, raw);
    if (fd) {
        const t = fd.type || 'text';
        if (t === 'tel')      return formatPhoneNumber(raw);
        if (t === 'date')     return formatDate(raw);
        if (t === 'datetime') return formatDateTime(raw);
        if (t === 'rating')   return `${raw || 0}/5`;
        if (t === 'currency') return $formatCurrency(raw);
        if (t === 'record' && fd.typeDomain) {
            const candidates = [];
            if (fd.relationship) candidates.push(fd.relationship);
            let inf = key;
            if (inf.endsWith('_id')) inf = inf.slice(0,-3);
            if (inf.startsWith('current_')) inf = inf.slice(8);
            if (inf.endsWith('s')) inf = inf.slice(0,-1);
            candidates.push(inf, key.replace('_id',''), key.replace('current_',''), fd.typeDomain.toLowerCase(), key);
            for (const c of candidates) {
                const rel = record[c];
                if (rel && typeof rel === 'object' && rel.display_name) return rel.display_name;
            }
            return raw;
        }
    }
    if (key.endsWith('_at') && typeof raw === 'string' && raw.includes('T')) return formatDateTime(raw);
    if (typeof raw === 'string') {
        if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/.test(raw)) return formatDateTime(raw);
        if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) return formatDate(raw);
    }
    return raw;
};

const getRelationshipLink = (record, column) => {
    const fd = props.fieldsSchema[column.key];
    if (!fd || fd.type !== 'record' || !fd.typeDomain) return null;
    const candidates = [];
    if (fd.relationship) candidates.push(fd.relationship);
    let inf = column.key;
    if (inf.endsWith('_id')) inf = inf.slice(0,-3);
    if (inf.startsWith('current_')) inf = inf.slice(8);
    if (inf.endsWith('s')) inf = inf.slice(0,-1);
    candidates.push(inf, column.key.replace('_id',''), fd.typeDomain.toLowerCase(), column.key);
    let rel = null;
    for (const k of candidates) {
        if (record[k] && typeof record[k] === 'object' && record[k].id) { rel = record[k]; break; }
    }
    if (!rel?.id) return null;
    const prefix = fd.typeDomain.replace(/([A-Z])/g, (m,l,o) => o === 0 ? l.toLowerCase() : '-'+l.toLowerCase()) + 's';
    try { return route(`${prefix}.show`, rel.id); } catch { return null; }
};

const hasEnumColor  = (column, record) => !!getEnumOption(column.key ?? column, record[column.key ?? column])?.color;
const getColumnLabel = (col) => col.label || props.fieldsSchema[col.key]?.label || col.key;

// ── Routes ────────────────────────────────────────────────────────────────────
const getShowUrl = (id) => {
    if (!id) return '#';
    try { return route(`${props.recordType}.show`, buildResourceRouteParams(props.recordType, id, props.extraRouteParams)); }
    catch { return '#'; }
};

// ── Create / success ──────────────────────────────────────────────────────────
const handleCreateClick = () => {
    if (props.createModal === false || props.schema?.allow_create_modal === false) {
        window.location.href = route(`${props.recordType}.create`, props.extraRouteParams);
    } else {
        showCreateModal.value = true;
    }
};

const handleRecordCreated = (id) => {
    createdRecordId.value = id;
    showCreateModal.value = false;
    showSuccessModal.value = true;
    router.reload({ only: ['records'] });
};

const viewRecord  = () => { if (createdRecordId.value) window.location.href = getShowUrl(createdRecordId.value); };
const backToPage  = () => { showSuccessModal.value = false; createdRecordId.value = null; };

// ── Selection ─────────────────────────────────────────────────────────────────
const toggleSelectAll = () => {
    if (selectAll.value) props.records.data.forEach(r => selectedRecords.value.add(r.id));
    else selectedRecords.value.clear();
};
const toggleRecordSelection = (id) => {
    if (selectedRecords.value.has(id)) selectedRecords.value.delete(id);
    else selectedRecords.value.add(id);
    selectAll.value = selectedRecords.value.size === props.records.data.length && props.records.data.length > 0;
};
const isRecordSelected = (id) => selectedRecords.value.has(id);

// ── View modal ────────────────────────────────────────────────────────────────
const handleViewOnPage = async (record) => {
    isLoadingRecord.value = true;
    showViewModal.value = true;
    try {
        const res = await axios.get(
            route(`${props.recordType}.show`, buildResourceRouteParams(props.recordType, record.id, props.extraRouteParams)),
            { headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' } }
        );
        selectedRecord.value = res.data?.record ?? record;
        selectedRecordImageUrls.value = res.data?.imageUrls ?? {};
    } catch {
        selectedRecord.value = record;
        selectedRecordImageUrls.value = {};
    } finally {
        isLoadingRecord.value = false;
    }
};

const handleNavigateToItem = (id) => {
    const url = getShowUrl(id);
    if (url !== '#') window.location.href = url;
};

const closeViewModal = () => {
    showViewModal.value = false;
    selectedRecord.value = null;
    selectedRecordImageUrls.value = {};
};

const handleRecordUpdated = (updated) => {
    if (updated?.id) {
        const idx = props.records.data.findIndex(r => r.id === updated.id);
        if (idx !== -1) Object.assign(props.records.data[idx], updated);
    }
    closeViewModal();
};

// ── Quick filters (from schema.filters) ───────────────────────────────────────
const quickFilterDefs = computed(() => props.schema?.filters ?? []);
const openQuickFilter = ref(null); // field key of the currently open quick-filter dropdown

const getQuickFieldDef = (field) => {
    const schema = props.fieldsSchema?.fields ?? props.fieldsSchema ?? {};
    return schema[field] ?? {};
};

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

// Active values for a quick filter field (extracted from activeFilters)
const getQuickActiveValues = (fieldKey) => {
    if (!fieldKey) {
        return [];
    }
    const f = activeFilters.value.find(f => f.field === fieldKey && (f.operator === 'any_of' || f.operator === 'equals'));
    if (!f) return [];
    return Array.isArray(f.value) ? f.value.map(String) : (f.value ? [String(f.value)] : []);
};

const toggleQuickValue = (fieldKey, optionId) => {
    const strId = String(optionId);
    const current = getQuickActiveValues(fieldKey);
    const next = current.includes(strId)
        ? current.filter(v => v !== strId)
        : [...current, strId];

    // Rebuild activeFilters — replace/remove this field's filter
    const others = activeFilters.value.filter(f => f.field !== fieldKey);
    if (next.length === 0) {
        applyFilters(others);
    } else {
        applyFilters([
            ...others,
            { id: Date.now(), field: fieldKey, operator: 'any_of', value: next },
        ]);
    }
};

const clearQuickFilter = (fieldKey) => {
    applyFilters(activeFilters.value.filter(f => f.field !== fieldKey));
};

const toggleQuickFilterDropdown = (fieldKey) => {
    openQuickFilter.value = openQuickFilter.value === fieldKey ? null : fieldKey;
};

const handleQuickFilterClickOutside = (e) => {
    if (!e.target.closest('[data-quick-filter]')) {
        openQuickFilter.value = null;
    }
};

// ── Filters ───────────────────────────────────────────────────────────────────
const parseFiltersFromUrl = () => {
    const p = new URLSearchParams(window.location.search).get('filters');
    if (!p) return [];
    try { const f = JSON.parse(decodeURIComponent(p)); return Array.isArray(f) ? f : []; } catch { return []; }
};

const applyFilters = (filters) => {
    activeFilters.value = filters;
    showFiltersModal.value = false;
    const params = new URLSearchParams(window.location.search);
    params.delete('filters');
    if (filters.length) params.set('filters', encodeURIComponent(JSON.stringify(filters)));
    const qs = params.toString();
    router.get(window.location.pathname + (qs ? '?' + qs : ''), {}, { preserveState: true, preserveScroll: true });
};

const removeFilter  = (i) => { const f = [...activeFilters.value]; f.splice(i,1); applyFilters(f); };
const clearAllFilters = () => { applyFilters([]); clearSearch(); };

const getFilterLabel = (filter) => {
    const schema = props.fieldsSchema?.fields ?? props.fieldsSchema ?? {};
    const fc = schema[filter.field] ?? {};
    const fl = fc.label || filter.field;
    let vl = '';
    if (filter.operator === 'between' && typeof filter.value === 'object') {
        vl = `${filter.value.start ?? filter.value.min} - ${filter.value.end ?? filter.value.max}`;
    } else if (!['is_empty','is_not_empty','today','this_week','this_month','is_true','is_false'].includes(filter.operator)) {
        if (fc.enum && props.enumOptions[fc.enum]) {
            if (Array.isArray(filter.value)) {
                vl = filter.value.map(v => {
                    const o = props.enumOptions[fc.enum].find(o => String(o.id) === String(v) || String(o.value) === String(v));
                    return o ? o.name : v;
                }).join(', ');
            } else {
                const o = props.enumOptions[fc.enum].find(o => String(o.id) === String(filter.value) || String(o.value) === String(filter.value));
                vl = o ? o.name : filter.value;
            }
        } else { vl = Array.isArray(filter.value) ? filter.value.join(', ') : filter.value; }
    }
    const ops = { contains:'contains', equals:'is', starts_with:'starts with', ends_with:'ends with',
                  is_empty:'is empty', is_not_empty:'is not empty', not_equals:'is not', any_of:'is any of',
                  none_of:'is none of', before:'before', after:'after', between:'between', today:'is today',
                  this_week:'is this week', this_month:'is this month', greater_than:'>', less_than:'<',
                  is_true:'is true', is_false:'is false' };
    return `${fl} ${ops[filter.operator] ?? filter.operator}${vl ? ` ${vl}` : ''}`;
};

// ── Search ────────────────────────────────────────────────────────────────────
const handleSearch = (e) => { e.preventDefault(); applySearch(searchQuery.value); };
const applySearch  = (q) => {
    const params = new URLSearchParams(window.location.search);
    if (q?.trim()) params.set('search', q.trim()); else params.delete('search');
    const qs = params.toString();
    router.get(window.location.pathname + (qs ? '?' + qs : ''), {}, { preserveState: true, preserveScroll: true });
};
const clearSearch = () => { searchQuery.value = ''; applySearch(''); };

// ── Column sort (?sort=&direction=) ───────────────────────────────────────────
const isColumnSortable = (col) => (col.sortable ?? true) !== false;

const syncSortFromUrl = () => {
    const p = new URLSearchParams(window.location.search);
    sortKey.value = p.get('sort') || '';
    sortDir.value = p.get('direction') === 'desc' ? 'desc' : 'asc';
};

const toggleSort = (col) => {
    if (!isColumnSortable(col)) return;
    const key = col.key;
    let dir = 'asc';
    if (sortKey.value === key) {
        dir = sortDir.value === 'asc' ? 'desc' : 'asc';
    }
    const params = new URLSearchParams(window.location.search);
    params.set('sort', key);
    params.set('direction', dir);
    params.delete('page');
    const qs = params.toString();
    router.get(window.location.pathname + (qs ? '?' + qs : ''), {}, { preserveState: true, preserveScroll: true });
};

watch(() => page.url, () => {
    syncSortFromUrl();
});

watch(() => props.records.data, () => {
    selectAll.value = props.records.data.length > 0 && selectedRecords.value.size === props.records.data.length;
}, { immediate: true });

onMounted(() => {
    activeFilters.value = parseFiltersFromUrl();
    syncSortFromUrl();
    const s = new URLSearchParams(window.location.search).get('search');
    if (s) searchQuery.value = s;
    document.addEventListener('click', handleQuickFilterClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleQuickFilterClickOutside);
});
</script>

<template>
    <section class="w-full flex flex-col space-y-4">
        <!-- Optional stat cards (defined in table.json schema.stats; values from page props stats) -->
        <div
            v-if="statCardDefs.length"
            class="grid grid-cols-2 gap-4 lg:grid-cols-4"
        >
            <div
                v-for="st in statCardDefs"
                :key="st.key"
                class="space-y-3 rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800"
            >
                <span
                    class="inline-block"
                    :class="statBadgeClass(st.color)"
                >{{ st.badge_label ?? st.label ?? st.key }}</span>
                <h2 class="text-2xl font-bold leading-none text-gray-900 tabular-nums dark:text-white">
                    {{ displayStatValue(st) }}
                </h2>
                <p
                    v-if="statSubtitleLine(st)"
                    class="text-sm text-gray-500 dark:text-gray-400"
                >
                    {{ statSubtitleLine(st) }}
                </p>
            </div>
        </div>

        <div class="rounded-xl bg-white dark:bg-gray-800 shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden flex flex-col">

            <!-- Header -->
            <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700 flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ pluralTitle }}</h2>
                <button v-if="!schema?.hide_create_button"
                        @click="handleCreateClick"
                        class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors">
                    <span class="material-icons text-[16px]">add</span>
                    Add {{ recordTitle }}
                </button>
            </div>

            <!-- Search (max width) left; quick filters + Filters on the right -->
            <div class="px-5 py-3 border-b border-gray-50 dark:border-gray-700/60 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                <form
                    @submit="handleSearch"
                    class="w-full min-w-0 max-w-96 shrink-0"
                >
                    <div class="relative w-full">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </div>
                        <input
                            type="search"
                            v-model="searchQuery"
                            @input="(e) => { if (!e.target.value) clearSearch(); }"
                            placeholder="Search..."
                            class="block w-full min-w-0 pl-9 pr-20 py-2 text-sm border border-gray-200 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 [&::-webkit-search-cancel-button]:hidden [&::-webkit-search-decoration]:hidden"
                        >
                        <div class="absolute inset-y-0 right-0 flex items-center">
                            <button
                                v-if="searchQuery"
                                type="button"
                                class="p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 mr-1"
                                @click="clearSearch"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                            <button
                                type="submit"
                                class="h-full px-3 text-xs font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-r-lg transition-colors"
                            >
                                Search
                            </button>
                        </div>
                    </div>
                </form>

                <div class="flex flex-wrap items-center gap-2 sm:justify-end sm:min-w-0 sm:flex-1">
                <!-- Quick filters (defined in schema.filters) -->
                <template v-if="quickFilterDefs.length">
                    <div
                        v-for="qf in quickFilterDefs"
                        :key="quickFilterKey(qf)"
                        class="relative shrink-0"
                        data-quick-filter
                    >
                        <button
                            type="button"
                            @click.stop="toggleQuickFilterDropdown(quickFilterKey(qf))"
                            :class="[
                                'inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-lg border transition-colors',
                                getQuickActiveValues(quickFilterKey(qf)).length
                                    ? 'border-primary-400 bg-primary-50 dark:bg-primary-900/20 text-primary-700 dark:text-primary-300'
                                    : 'border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700',
                            ]"
                        >
                            {{ qf.label ?? quickFilterKey(qf) }}
                            <span
                                v-if="getQuickActiveValues(quickFilterKey(qf)).length"
                                class="px-1.5 py-0.5 text-[10px] font-semibold bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-400 rounded-full"
                            >
                                {{ getQuickActiveValues(quickFilterKey(qf)).length }}
                            </span>
                            <svg class="w-3.5 h-3.5 opacity-60" :class="openQuickFilter === quickFilterKey(qf) ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>

                        <!-- Dropdown panel -->
                        <div
                            v-if="openQuickFilter === quickFilterKey(qf)"
                            class="absolute right-0 top-full mt-1.5 z-50 min-w-[180px] bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-lg overflow-hidden"
                        >
                            <div class="max-h-64 overflow-y-auto divide-y divide-gray-50 dark:divide-gray-700/60">
                                <label
                                    v-for="opt in getQuickFilterOptions(qf)"
                                    :key="opt.id ?? opt.value"
                                    class="flex items-center gap-2.5 px-3 py-2.5 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
                                >
                                    <input
                                        type="checkbox"
                                        :value="opt.id ?? opt.value"
                                        :checked="getQuickActiveValues(quickFilterKey(qf)).includes(String(opt.id ?? opt.value))"
                                        @change="toggleQuickValue(quickFilterKey(qf), opt.id ?? opt.value)"
                                        class="w-4 h-4 rounded text-primary-600 border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-primary-500"
                                    />
                                    <div v-if="opt.color" class="flex items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full shrink-0" :class="`bg-${opt.color}-500`"></span>
                                        <span class="text-sm text-gray-900 dark:text-white">{{ opt.name ?? opt.label }}</span>
                                    </div>
                                    <span v-else class="text-sm text-gray-900 dark:text-white">{{ opt.name ?? opt.label ?? opt.value }}</span>
                                </label>
                            </div>
                            <div v-if="getQuickActiveValues(quickFilterKey(qf)).length" class="px-3 py-2 border-t border-gray-100 dark:border-gray-700/60">
                                <button
                                    type="button"
                                    @click="clearQuickFilter(quickFilterKey(qf))"
                                    class="text-xs font-medium text-gray-500 dark:text-gray-400 hover:text-rose-600 dark:hover:text-rose-400 transition-colors"
                                >
                                    Clear
                                </button>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Advanced filters button -->
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors shrink-0"
                    @click="showFiltersModal = true"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                    </svg>
                    Filters
                    <span v-if="activeFilters.length" class="ml-1 px-1.5 py-0.5 text-[10px] font-semibold bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-400 rounded-full">
                        {{ activeFilters.length }}
                    </span>
                </button>
                </div>
            </div>

            <!-- Active filter pills -->
            <div v-if="activeFilters.length || searchQuery" class="px-5 py-2.5 border-b border-gray-50 dark:border-gray-700/60 flex flex-wrap gap-1.5">
                <span v-if="searchQuery"
                      class="inline-flex items-center gap-1 pl-2 pr-1 py-0.5 text-xs font-medium rounded-full bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-800 text-primary-700 dark:text-primary-400">
                    Search: {{ searchQuery }}
                    <button @click="clearSearch" class="ml-0.5 p-0.5 rounded-full hover:bg-primary-100 dark:hover:bg-primary-800/50 transition-colors">
                        <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </span>
                <span v-for="(filter, i) in activeFilters" :key="i"
                      class="inline-flex items-center gap-1 pl-2 pr-1 py-0.5 text-xs font-medium rounded-full bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-800 text-primary-700 dark:text-primary-400">
                    {{ getFilterLabel(filter) }}
                    <button @click="removeFilter(i)" class="ml-0.5 p-0.5 rounded-full hover:bg-primary-100 dark:hover:bg-primary-800/50 transition-colors">
                        <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </span>
            </div>

            <!-- Filtered-out empty state -->
            <div v-if="showFilteredOutState" class="flex flex-col items-center justify-center py-20 px-4">
                <div class="flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 dark:bg-gray-700 mb-4">
                    <svg class="w-8 h-8 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                    </svg>
                </div>
                <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-1">No records match your criteria</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 text-center max-w-sm mb-5">Try adjusting or removing your filters.</p>
                <div class="flex items-center gap-2">
                    <button @click="clearAllFilters"
                            class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-lg border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        Clear all
                    </button>
                    <button @click="showFiltersModal = true"
                            class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors">
                        Adjust filters
                    </button>
                </div>
            </div>

            <!-- Empty state -->
            <div v-else-if="showEmptyState" class="flex flex-col items-center justify-center py-20 px-4">
                <div class="flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 dark:bg-gray-700 mb-4">
                    <svg class="w-8 h-8 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                    </svg>
                </div>
                <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-1">No {{ recordType }} yet</h3>
                <p v-if="!schema?.hide_create_button"
                   class="text-sm text-gray-500 dark:text-gray-400 text-center max-w-sm mb-5">
                    Get started by creating your first {{ recordTitle }}.
                </p>
                <p v-else class="text-sm text-gray-500 dark:text-gray-400 text-center max-w-sm mb-5">
                    There are no payments to show yet. Payments appear when customers pay online or when you record a manual payment on an invoice.
                </p>
                <button v-if="!schema?.hide_create_button"
                        @click="handleCreateClick"
                        class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors">
                    <span class="material-icons text-[16px]">add</span>
                    Create your first {{ recordTitle }}
                </button>
            </div>

            <!-- Table -->
            <div v-else class="overflow-x-auto grow">
                <table class="w-full text-sm text-left table-auto">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-gray-700/50 border-b border-gray-100 dark:border-gray-700">
                            <th class="px-4 py-3 w-10">
                                <input type="checkbox" v-model="selectAll" @change="toggleSelectAll"
                                       class="w-4 h-4 rounded text-primary-600 border-gray-300 dark:border-gray-600 focus:ring-primary-500 dark:bg-gray-700"/>
                            </th>
                            <th v-for="col in columns" :key="col.key"
                                class="px-4 py-3 min-w-[11rem] text-sm font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300 whitespace-nowrap">
                                <button
                                    v-if="isColumnSortable(col)"
                                    type="button"
                                    @click="toggleSort(col)"
                                    class="inline-flex items-center gap-1.5 -mx-1 px-1.5 py-1 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700/60 hover:text-gray-900 dark:hover:text-white transition-colors text-left"
                                >
                                    <span>{{ getColumnLabel(col) }}</span>
                                    <span
                                        v-if="sortKey === col.key"
                                        class="material-icons shrink-0 text-[20px] leading-none text-primary-600 dark:text-primary-400"
                                        aria-hidden="true"
                                    >{{ sortDir === 'asc' ? 'arrow_upward' : 'arrow_downward' }}</span>
                                    <span
                                        v-else
                                        class="material-icons shrink-0 text-[20px] leading-none text-gray-400 dark:text-gray-500 opacity-70"
                                        aria-hidden="true"
                                    >unfold_more</span>
                                </button>
                                <span v-else class="block">{{ getColumnLabel(col) }}</span>
                            </th>
                            <th class="px-4 py-3 w-20"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50 dark:divide-gray-700/60">
                        <tr v-for="record in records.data" :key="record.id"
                            class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                            <td class="px-4 py-3">
                                <input type="checkbox" :checked="isRecordSelected(record.id)" @change="toggleRecordSelection(record.id)"
                                       class="w-4 h-4 rounded text-primary-600 border-gray-300 dark:border-gray-600 focus:ring-primary-500 dark:bg-gray-700"/>
                            </td>

                            <td v-for="col in columns" :key="col.key" class="px-4 py-3 text-gray-900 dark:text-white whitespace-nowrap">
                                <!-- ID -->
                                <template v-if="col.key === 'id'">
                                    <Link :href="getShowUrl(record.id)" class="font-mono text-xs text-primary-600 dark:text-primary-400 hover:underline">
                                        {{ getRecordValue(record, col) }}
                                    </Link>
                                </template>
                                <!-- Display name -->
                                <template v-else-if="col.key === 'display_name'">
                                    <a :href="getShowUrl(record.id)" target="_blank"
                                       class="font-medium text-primary-600 dark:text-primary-400 hover:underline">
                                        {{ getRecordValue(record, col) || '—' }}
                                    </a>
                                </template>
                                <!-- Primary key column (e.g. payment sequence) -->
                                <template v-else-if="col.isKey">
                                    <Link :href="getShowUrl(record.id)" class="font-mono text-sm font-medium text-primary-600 dark:text-primary-400 hover:underline">
                                        {{ getRecordValue(record, col) || '—' }}
                                    </Link>
                                </template>
                                <!-- Contact roles -->
                                <template v-else-if="col.key === 'contact_roles'">
                                    <div class="flex flex-wrap gap-1">
                                        <span v-if="record.contact_roles?.lead"
                                              class="inline-flex items-center rounded-full bg-amber-100 dark:bg-amber-900/40 px-2 py-0.5 text-xs font-medium text-amber-700 dark:text-amber-200">Lead</span>
                                        <span v-if="record.contact_roles?.customer"
                                              class="inline-flex items-center rounded-full bg-emerald-100 dark:bg-emerald-900/40 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:text-emerald-200">Customer</span>
                                        <span v-if="record.contact_roles?.vendor"
                                              class="inline-flex items-center rounded-full bg-violet-100 dark:bg-violet-900/40 px-2 py-0.5 text-xs font-medium text-violet-700 dark:text-violet-200">Vendor</span>
                                        <span v-if="!record.contact_roles?.lead && !record.contact_roles?.customer && !record.contact_roles?.vendor"
                                              class="text-gray-400 dark:text-gray-500 text-xs">—</span>
                                    </div>
                                </template>
                                <!-- Enum with color dot -->
                                <template v-else-if="hasEnumColor(col, record)">
                                    <div class="flex items-center gap-1.5">
                                        <div :class="[getColorClass(getEnumOption(col.key, record[col.key])?.color), 'w-2 h-2 rounded-full shrink-0']"></div>
                                        <span>{{ getRecordValue(record, col) }}</span>
                                    </div>
                                </template>
                                <!-- Rating stars -->
                                <template v-else-if="props.fieldsSchema[col.key]?.type === 'rating'">
                                    <div class="flex items-center gap-0.5">
                                        <template v-for="s in 5" :key="s">
                                            <svg class="w-3.5 h-3.5" :class="s <= record[col.key] ? 'text-amber-400' : 'text-gray-200 dark:text-gray-600'" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                            </svg>
                                        </template>
                                    </div>
                                </template>
                                <!-- Record relationship link -->
                                <template v-else-if="props.fieldsSchema[col.key]?.type === 'record' && getRelationshipLink(record, col)">
                                    <Link :href="getRelationshipLink(record, col)" class="text-primary-600 dark:text-primary-400 hover:underline font-medium">
                                        {{ getRecordValue(record, col) }}
                                    </Link>
                                </template>
                                <!-- Default -->
                                <template v-else>
                                    <span class="text-sm">{{ getRecordValue(record, col) }}</span>
                                </template>
                            </td>

                            <!-- Row actions -->
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-1">
                                    <button @click="handleViewOnPage(record)"
                                            class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-gray-700 dark:hover:text-gray-200 transition-colors"
                                            title="Quick view">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </button>
                                    <button @click="handleNavigateToItem(record.id)"
                                            class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-gray-700 dark:hover:text-gray-200 transition-colors"
                                            title="Open record">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!hasRecords">
                            <td :colspan="columns.length + 3" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                No records found
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <nav v-if="records.links?.length > 3"
                 class="px-5 py-3.5 border-t border-gray-100 dark:border-gray-700 flex flex-col sm:flex-row items-center justify-between gap-3">
                <span class="text-xs text-gray-500 dark:text-gray-400">
                    Showing <span class="font-semibold text-gray-900 dark:text-white">{{ records.from }}</span>
                    to <span class="font-semibold text-gray-900 dark:text-white">{{ records.to }}</span>
                    of <span class="font-semibold text-gray-900 dark:text-white">{{ records.total }}</span>
                </span>
                <div class="flex gap-1">
                    <template v-for="(link, i) in records.links" :key="i">
                        <Link v-if="link.url" :href="link.url" v-html="link.label"
                              :class="['flex items-center justify-center px-3 py-1 text-xs rounded-lg border transition-colors',
                                       link.active
                                           ? 'bg-primary-600 text-white border-primary-600'
                                           : 'border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700']"/>
                        <span v-else v-html="link.label"
                              class="flex items-center justify-center px-3 py-1 text-xs border border-gray-200 dark:border-gray-600 text-gray-400 dark:text-gray-500 rounded-lg"/>
                    </template>
                </div>
            </nav>
        </div>

        <!-- Create Modal -->
        <Modal :show="showCreateModal" @close="showCreateModal = false" :max-width="modalMaxWidth">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-700 shrink-0">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Create {{ recordTitle }}</h3>
                <button @click="showCreateModal = false" type="button"
                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors">
                    <span class="material-icons text-[20px]">close</span>
                </button>
            </div>
            <div class="overflow-y-auto flex-1">
                <component :is="recordFormComponent"
                           :schema="formSchema" :fields-schema="fieldsSchema" :record-type="recordType"
                           :record-title="recordTitle" :enum-options="enumOptions" :extra-route-params="extraRouteParams"
                           :initial-data="initialCreateData" :available-specs="createAvailableSpecs"
                           mode="create" :prevent-redirect="true"
                           @created="handleRecordCreated" @submit="() => {}" @cancel="showCreateModal = false"/>
            </div>
        </Modal>

        <!-- Success Modal -->
        <Modal :show="showSuccessModal" @close="backToPage" max-width="sm">
            <div class="p-8 text-center">
                <div class="mx-auto flex items-center justify-center w-12 h-12 rounded-full bg-green-100 dark:bg-green-900/30 mb-4">
                    <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-1">{{ recordTitle }} created</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">Successfully created.</p>
                <div class="flex items-center justify-center gap-3">
                    <button @click="viewRecord" type="button"
                            class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors">
                        View record
                    </button>
                    <button @click="backToPage" type="button"
                            class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 rounded-lg transition-colors">
                        Back
                    </button>
                </div>
            </div>
        </Modal>

        <!-- View/Edit Modal -->
        <Modal :show="showViewModal" @close="closeViewModal" :max-width="modalMaxWidth">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-700 shrink-0">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ selectedRecord ? `View ${recordTitle}` : '' }}</h3>
                <button @click="closeViewModal" type="button"
                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors">
                    <span class="material-icons text-[20px]">close</span>
                </button>
            </div>
            <div class="overflow-y-auto flex-1">
                <div v-if="isLoadingRecord" class="flex items-center justify-center py-16">
                    <svg class="animate-spin w-8 h-8 text-primary-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                    </svg>
                </div>
                <component v-else-if="selectedRecord" :is="recordFormComponent"
                           :schema="formSchema" :fields-schema="fieldsSchema" :record="selectedRecord"
                           :record-type="recordType" :record-title="recordTitle" :enum-options="enumOptions"
                           :extra-route-params="extraRouteParams" :image-urls="selectedRecordImageUrls"
                           mode="edit" :prevent-redirect="true"
                           @updated="handleRecordUpdated" @submit="closeViewModal" @cancel="closeViewModal"/>
            </div>
        </Modal>

        <!-- Filters Modal -->
        <Modal :show="showFiltersModal" @close="showFiltersModal = false" max-width="4xl">
            <div class="p-6">
                <FiltersModal :fields-schema="fieldsSchema" :enum-options="enumOptions" :columns="columns"
                              :active-filters="activeFilters" @apply="applyFilters" @close="showFiltersModal = false"/>
            </div>
        </Modal>
    </section>
</template>