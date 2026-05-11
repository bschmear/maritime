<script setup>
import Modal from '@/Components/Modal.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed, getCurrentInstance, onMounted, ref, watch } from 'vue';

const FALLBACK_INPUT_TYPES = [
    { id: 'select', name: 'Single select' },
    { id: 'color', name: 'Color' },
    { id: 'multi_select', name: 'Multi select' },
    { id: 'toggle', name: 'Toggle' },
];

const props = defineProps({
    resolvedOptions: {
        type: Array,
        default: () => [],
    },
    catalogContext: {
        type: Object,
        required: true,
    },
    /** Lead paragraph under the section title (defaults to generic catalog copy). */
    intro: {
        type: String,
        default: null,
    },
    /** Inertia `only` keys to refresh catalog data after attach/create */
    reloadOnlyKeys: {
        type: Array,
        default: () => ['catalogResolvedOptions', 'catalogContext'],
    },
});

const modalOpen = ref(false);
const saving = ref(false);
const formError = ref('');
const attachMode = ref('existing'); // existing | create

const scope = ref('asset');
const newOptionName = ref('');
const newOptionInputType = ref('select');

const lookupSearch = ref('');
const lookupRecords = ref([]);
const lookupLoading = ref(false);
const lookupPage = ref(1);
const lookupLastPage = ref(1);
const selectedExistingId = ref(null);

let lookupSeq = 0;
let lookupTimer = null;

/** Persist collapse preference across visits (tenant UI). */
const SECTION_EXPANDED_STORAGE_KEY = 'maritime.assetCatalogOptionsSection.expanded';

const instance = getCurrentInstance();

function formatMoney(value) {
    if (value == null || value === '') {
        return null;
    }
    const n = Number(value);
    if (!Number.isFinite(n)) {
        return null;
    }
    const fn = instance?.appContext?.config?.globalProperties?.$formatCurrency;
    if (typeof fn === 'function') {
        return fn(n);
    }

    return n.toLocaleString('en-US', { style: 'currency', currency: 'USD' });
}

const sectionExpanded = ref(true);

onMounted(() => {
    try {
        const raw = localStorage.getItem(SECTION_EXPANDED_STORAGE_KEY);
        if (raw === '0' || raw === 'false') {
            sectionExpanded.value = false;
        } else if (raw === '1' || raw === 'true') {
            sectionExpanded.value = true;
        }
    } catch {
        // ignore
    }
});

watch(sectionExpanded, (v) => {
    if (typeof window === 'undefined') {
        return;
    }
    try {
        localStorage.setItem(SECTION_EXPANDED_STORAGE_KEY, v ? '1' : '0');
    } catch {
        // ignore
    }
});

const optionCount = computed(() => (Array.isArray(props.resolvedOptions) ? props.resolvedOptions.length : 0));

function toggleSectionExpanded() {
    sectionExpanded.value = !sectionExpanded.value;
}

const assignedOptionIds = computed(() =>
    new Set((props.resolvedOptions || []).map((o) => Number(o.option_id)).filter((id) => id > 0)),
);

watch(
    () => props.catalogContext,
    (ctx) => {
        if (ctx?.show_variant_scope) {
            scope.value = 'variant';
        } else {
            scope.value = 'asset';
        }
    },
    { immediate: true },
);

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

function openModal() {
    formError.value = '';
    attachMode.value = 'existing';
    newOptionName.value = '';
    newOptionInputType.value = 'select';
    selectedExistingId.value = null;
    lookupSearch.value = '';
    lookupPage.value = 1;
    modalOpen.value = true;
    scheduleLookup(true);
}

function closeModal() {
    modalOpen.value = false;
}

const scopeChoices = computed(() => {
    const ctx = props.catalogContext;
    const out = [];
    if (ctx.show_variant_scope) {
        out.push({
            value: 'variant',
            label: 'This variant only',
            description: 'Applies when configuring this specific variant.',
        });
    }
    out.push({
        value: 'asset',
        label: ctx.show_variant_scope ? 'All variants of this asset' : 'This asset model only',
        description: ctx.show_variant_scope
            ? 'Default for every variant unless a variant overrides it.'
            : 'Applies to this catalog asset (no separate variants).',
    });
    out.push({
        value: 'brand',
        label: 'All models of this brand',
        description: 'Every asset under this manufacturer.',
        disabled: ctx.make_id == null,
    });
    return out;
});

async function fetchLookup(immediate = false) {
    const seq = ++lookupSeq;
    if (immediate) {
        clearTimeout(lookupTimer);
        lookupTimer = null;
    }
    lookupLoading.value = true;
    try {
        const url = new URL(route('records.lookup'), window.location.origin);
        url.searchParams.set('page', String(lookupPage.value));
        url.searchParams.set('per_page', '15');
        url.searchParams.set('type', 'assetoption');
        const q = lookupSearch.value.trim();
        if (q) {
            url.searchParams.set('search', q);
        }
        url.searchParams.set('order_by', 'display_name');
        url.searchParams.set('order_direction', 'asc');

        const response = await fetch(url.toString(), {
            method: 'GET',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
            credentials: 'same-origin',
        });
        if (seq !== lookupSeq) {
            return;
        }
        if (!response.ok) {
            throw new Error('lookup failed');
        }
        const data = await response.json();
        let rows = data.records ?? [];
        rows = rows.filter((r) => r?.id && !assignedOptionIds.value.has(Number(r.id)));
        lookupRecords.value = rows;
        lookupLastPage.value = data.meta?.last_page ?? 1;
    } catch {
        if (seq === lookupSeq) {
            lookupRecords.value = [];
        }
    } finally {
        if (seq === lookupSeq) {
            lookupLoading.value = false;
        }
    }
}

function scheduleLookup(resetPage = false) {
    if (resetPage) {
        lookupPage.value = 1;
    }
    clearTimeout(lookupTimer);
    lookupTimer = setTimeout(() => {
        lookupTimer = null;
        fetchLookup(true);
    }, 280);
}

watch(lookupSearch, () => {
    if (!modalOpen.value) {
        return;
    }
    scheduleLookup(true);
});

watch(modalOpen, (open) => {
    if (open && attachMode.value === 'existing') {
        fetchLookup(true);
    }
});

watch(attachMode, (mode) => {
    if (modalOpen.value && mode === 'existing') {
        fetchLookup(true);
    }
});

function lookupPrev() {
    if (lookupPage.value <= 1) {
        return;
    }
    lookupPage.value -= 1;
    fetchLookup(true);
}

function lookupNext() {
    if (lookupPage.value >= lookupLastPage.value) {
        return;
    }
    lookupPage.value += 1;
    fetchLookup(true);
}

function variantPayload() {
    const ctx = props.catalogContext;
    if (scope.value === 'variant') {
        return ctx.variant_id;
    }

    return null;
}

async function postAttach(optionId) {
    const ctx = props.catalogContext;
    const body = {
        asset_id: ctx.asset_id,
        variant_id: variantPayload(),
        scope: scope.value,
    };

    const response = await fetch(route('asset-options.attach-catalog', optionId), {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
        },
        credentials: 'same-origin',
        body: JSON.stringify(body),
    });

    if (response.status === 422) {
        const data = await response.json().catch(() => ({}));
        const msg = data.errors ? Object.values(data.errors).flat().join(' ') : data.message || 'Validation failed';

        throw new Error(msg);
    }

    if (!response.ok) {
        throw new Error('Request failed');
    }
}

async function createOptionDefinition() {
    const name = newOptionName.value.trim();
    if (!name) {
        throw new Error('Option name is required.');
    }

    const response = await fetch(route('asset-options.store'), {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
        },
        credentials: 'same-origin',
        body: JSON.stringify({
            name,
            input_type: newOptionInputType.value,
            active: true,
            is_required: false,
            allow_multiple: newOptionInputType.value === 'multi_select',
        }),
    });

    const data = await response.json().catch(() => ({}));

    if (response.status === 422) {
        const msg = data.errors ? Object.values(data.errors).flat().join(' ') : data.message || 'Validation failed';

        throw new Error(msg);
    }

    const newId = data.recordId ?? data.record?.id;
    if (!response.ok || newId == null) {
        throw new Error(data.message || 'Could not create option.');
    }

    return Number(newId);
}

async function submitModal() {
    formError.value = '';
    saving.value = true;
    try {
        let optionId = null;
        if (attachMode.value === 'existing') {
            optionId = selectedExistingId.value ? Number(selectedExistingId.value) : null;
            if (!optionId) {
                throw new Error('Select an option from the list.');
            }
        } else {
            optionId = await createOptionDefinition();
        }

        await postAttach(optionId);
        closeModal();
        router.reload({ only: props.reloadOnlyKeys });
    } catch (e) {
        formError.value = e?.message || 'Something went wrong.';
    } finally {
        saving.value = false;
    }
}

function formatInputType(t) {
    const row = FALLBACK_INPUT_TYPES.find((x) => x.id === t);

    return row?.name || t || '—';
}

function normalizeHex(hex) {
    if (!hex || typeof hex !== 'string') {
        return null;
    }
    const h = hex.trim();
    if (/^#[0-9a-fA-F]{6}$/.test(h)) {
        return h.toLowerCase();
    }
    if (/^[0-9a-fA-F]{6}$/.test(h)) {
        return `#${h.toLowerCase()}`;
    }

    return null;
}

/** Rows for choice chips (select / multi / color). */
function choicePreview(opt) {
    const isColor = opt.input_type === 'color';
    if (!opt?.values || !Array.isArray(opt.values)) {
        return {
            chips: [],
            more: 0,
            total: 0,
            isColor,
        };
    }

    const rows = opt.values
        .filter((v) => v.label != null && String(v.label).trim() !== '')
        .map((v) => {
            const nh = isColor ? normalizeHex(v.color_hex) : null;
            const costFmt = formatMoney(v.cost);
            const priceFmt = formatMoney(v.price);

            return {
                label: v.label,
                color_hex: nh,
                costFmt,
                priceFmt,
                hasPricing: Boolean(costFmt || priceFmt),
            };
        });

    const total = rows.length;

    return {
        chips: rows.slice(0, 8),
        more: Math.max(0, total - 8),
        total,
        isColor,
    };
}

function chipSwatchStyle(row) {
    if (row.color_hex) {
        return { backgroundColor: row.color_hex };
    }

    return { backgroundColor: '#d1d5db' };
}
</script>
<template>
    <section
        id="asset-options"
        class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900/40"
    >
        <!-- ── Header ──────────────────────────────────────────────────── -->
        <div class="border-b border-gray-100 px-4 py-4 sm:px-6 dark:border-gray-700">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="flex min-w-0 items-start gap-2 sm:gap-3">
                    <button
                        type="button"
                        class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-gray-200 text-gray-600 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800"
                        :title="sectionExpanded ? 'Hide section' : 'Show section'"
                        :aria-expanded="sectionExpanded"
                        aria-controls="asset-options-body"
                        @click="toggleSectionExpanded"
                    >
                        <span class="material-icons text-[22px]">{{ sectionExpanded ? 'expand_less' : 'expand_more' }}</span>
                    </button>
                    <div class="min-w-0">
                        <h3 class="text-md font-semibold text-gray-900 dark:text-white">
                            Asset options
                            <span v-if="optionCount > 0" class="ml-1.5 font-normal text-gray-500 dark:text-gray-400">
                                ({{ optionCount }})
                            </span>
                        </h3>
                        <p v-show="sectionExpanded" class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ intro || 'Options available for this catalog configuration. Add an existing option or create a new definition, then choose where it applies.' }}
                        </p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Link
                        :href="route('asset-options.index')"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                    >
                        <span class="material-icons text-[16px]">list</span>
                        All options
                    </Link>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-3 py-2 text-sm font-medium text-white hover:bg-primary-700"
                        @click="openModal"
                    >
                        <span class="material-icons text-[16px]">add</span>
                        Add option
                    </button>
                </div>
            </div>
        </div>

        <!-- ── Body ───────────────────────────────────────────────────── -->
        <div v-show="sectionExpanded" id="asset-options-body">
            <div v-if="!resolvedOptions?.length" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                No asset options are assigned yet for this configuration.
            </div>

            <div v-else class="divide-y divide-gray-100 dark:divide-gray-700/60">
                <article
                    v-for="opt in resolvedOptions"
                    :key="opt.option_id"
                    class="group px-4 py-4 sm:px-6"
                >
                    <!-- Option header row -->
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <Link
                                :href="route('asset-options.show', opt.option_id)"
                                class="text-lg font-bold text-primary-600 hover:text-primary-700 dark:text-primary-400 hover:underline underline-offset-2"
                            >
                                {{ opt.name }}
                            </Link>
                            <span class="rounded-md border border-gray-200 bg-gray-50 px-2 py-0.5 text-sm font-medium text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                                {{ formatInputType(opt.input_type) }}
                            </span>
                            <span
                                v-if="opt.is_required"
                                class="rounded-md bg-amber-100 px-2 py-0.5 text-sm font-semibold uppercase tracking-wide text-amber-800 dark:bg-amber-900/40 dark:text-amber-300"
                            >
                                Required
                            </span>
                        </div>
                    </div>

                    <!-- Toggle -->
                    <div v-if="opt.input_type === 'toggle'" class="text-sm text-gray-500 dark:text-gray-400 italic">
                        On / off — no preset choices.
                    </div>

                    <!-- Choices table -->
                    <template v-else>
                        <template v-for="preview in [choicePreview(opt)]" :key="`ch-${opt.option_id}`">
                            <div v-if="preview.total === 0" class="text-sm text-gray-400 dark:text-gray-500 italic">
                                No choices defined yet — edit the option to add values.
                            </div>

                            <div v-else class="overflow-hidden rounded-lg border border-gray-100 dark:border-gray-700/70">
                                <!-- Column headers — only show if any row has pricing -->
                                <div class="grid bg-gray-50 px-3 py-1.5 dark:bg-gray-800/60"
                                    :class="preview.chips.some(r => r.hasPricing) ? 'grid-cols-[1fr_auto_auto]' : 'grid-cols-[1fr]'"
                                >
                                    <span class="text-sm font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500">Choice</span>
                                    <template v-if="preview.chips.some(r => r.hasPricing)">
                                        <span class="text-sm font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500 text-right pr-6">Cost</span>
                                        <span class="text-sm font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500 text-right">Price</span>
                                    </template>
                                </div>

                                <ul class="divide-y divide-gray-100 dark:divide-gray-700/60">
                                    <li
                                        v-for="(row, i) in preview.chips"
                                        :key="i"
                                        class="grid items-center gap-x-4 px-3 py-2 hover:bg-gray-50/70 dark:hover:bg-gray-800/40 transition-colors"
                                        :class="preview.chips.some(r => r.hasPricing) ? 'grid-cols-[1fr_auto_auto]' : 'grid-cols-[1fr]'"
                                    >
                                        <!-- Label + swatch -->
                                        <span class="flex min-w-0 items-center gap-2">
                                            <span
                                                v-if="preview.isColor"
                                                class="h-4 w-4 shrink-0 rounded-full border border-gray-300 shadow-sm dark:border-gray-600"
                                                :style="chipSwatchStyle(row)"
                                                aria-hidden="true"
                                            />
                                            <span class="truncate text-md font-medium text-gray-800 dark:text-gray-200">{{ row.label }}</span>
                                            <span
                                                v-if="preview.isColor && row.color_hex"
                                                class="shrink-0 font-mono text-sm text-gray-400 dark:text-gray-500"
                                            >{{ row.color_hex }}</span>
                                        </span>

                                        <!-- Cost -->
                                        <span
                                            v-if="preview.chips.some(r => r.hasPricing)"
                                            class="text-right text-md tabular-nums"
                                            :class="row.costFmt ? 'font-medium text-gray-700 dark:text-gray-300' : 'text-gray-300 dark:text-gray-600'"
                                        >
                                            {{ row.costFmt || '—' }}
                                        </span>

                                        <!-- Price -->
                                        <span
                                            v-if="preview.chips.some(r => r.hasPricing)"
                                            class="text-right text-md tabular-nums"
                                            :class="row.priceFmt ? 'font-semibold text-gray-900 dark:text-white' : 'text-gray-300 dark:text-gray-600'"
                                        >
                                            {{ row.priceFmt || '—' }}
                                        </span>
                                    </li>
                                </ul>

                                <!-- +N more -->
                                <div
                                    v-if="preview.more > 0"
                                    class="border-t border-gray-100 bg-gray-50 px-3 py-2 text-center text-sm font-medium text-gray-500 dark:border-gray-700 dark:bg-gray-800/40 dark:text-gray-400"
                                >
                                    + {{ preview.more }} more choices — <Link :href="route('asset-options.show', opt.option_id)" class="text-primary-600 hover:underline dark:text-primary-400">view all</Link>
                                </div>
                            </div>
                        </template>
                    </template>
                </article>
            </div>
        </div>

        <!-- ── Modal ──────────────────────────────────────────────────── -->
        <Modal :show="modalOpen" max-width="lg" @close="closeModal">
            <div class="flex max-h-[90vh] flex-col bg-white dark:bg-gray-900">
                <div class="flex shrink-0 items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Add asset option</h3>
                    <button type="button" class="text-gray-400 transition-colors hover:text-gray-600 dark:hover:text-gray-200" @click="closeModal">
                        <span class="material-icons text-[20px]">close</span>
                    </button>
                </div>

                <div class="space-y-5 overflow-y-auto px-5 py-4">
                    <div class="flex rounded-lg border border-gray-200 p-1 dark:border-gray-600">
                        <button
                            type="button"
                            class="flex-1 rounded-md px-3 py-2 text-sm font-medium transition-colors"
                            :class="attachMode === 'existing' ? 'bg-primary-600 text-white' : 'text-gray-600 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-gray-800'"
                            @click="attachMode = 'existing'"
                        >Link existing</button>
                        <button
                            type="button"
                            class="flex-1 rounded-md px-3 py-2 text-sm font-medium transition-colors"
                            :class="attachMode === 'create' ? 'bg-primary-600 text-white' : 'text-gray-600 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-gray-800'"
                            @click="attachMode = 'create'"
                        >Create new</button>
                    </div>

                    <div>
                        <p class="mb-2 text-sm font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">Apply to</p>
                        <div class="space-y-2">
                            <label
                                v-for="ch in scopeChoices"
                                :key="ch.value"
                                class="flex cursor-pointer gap-3 rounded-lg border px-3 py-2 transition-colors"
                                :class="[
                                    scope === ch.value ? 'border-primary-500 bg-primary-50 dark:border-primary-600 dark:bg-primary-950/30' : 'border-gray-200 dark:border-gray-600',
                                    ch.disabled ? 'cursor-not-allowed opacity-50' : '',
                                ]"
                            >
                                <input v-model="scope" type="radio" class="mt-1" :value="ch.value" :disabled="ch.disabled" />
                                <span>
                                    <span class="block text-sm font-medium text-gray-900 dark:text-white">{{ ch.label }}</span>
                                    <span class="block text-sm text-gray-500 dark:text-gray-400">{{ ch.description }}</span>
                                </span>
                            </label>
                        </div>
                    </div>

                    <div v-if="attachMode === 'create'" class="space-y-3">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-800 dark:text-gray-200">Name</label>
                            <input v-model="newOptionName" type="text" class="input-style w-full" maxlength="255" placeholder="e.g. Hull color" autocomplete="off" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-800 dark:text-gray-200">Input type</label>
                            <select v-model="newOptionInputType" class="input-style w-full">
                                <option v-for="t in FALLBACK_INPUT_TYPES" :key="t.id" :value="t.id">{{ t.name }}</option>
                            </select>
                        </div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">You can add choices, colors, and pricing on the option's edit page after saving.</p>
                    </div>

                    <div v-else class="space-y-3">
                        <label class="mb-1 block text-sm font-medium text-gray-800 dark:text-gray-200">Search options</label>
                        <input v-model="lookupSearch" type="search" class="input-style w-full" placeholder="Start typing…" autocomplete="off" />
                        <div class="min-h-[160px] rounded-lg border border-gray-100 dark:border-gray-700">
                            <div v-if="lookupLoading" class="flex justify-center py-10">
                                <svg class="h-8 w-8 animate-spin text-primary-600 dark:text-primary-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                                </svg>
                            </div>
                            <ul v-else-if="lookupRecords.length" class="max-h-52 divide-y divide-gray-100 overflow-y-auto dark:divide-gray-700">
                                <li v-for="r in lookupRecords" :key="r.id">
                                    <label class="flex cursor-pointer items-center gap-3 px-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-800">
                                        <input v-model="selectedExistingId" type="radio" class="text-primary-600" :value="r.id" />
                                        <span class="text-sm text-gray-900 dark:text-gray-100">{{ r.display_name || r.name }}</span>
                                    </label>
                                </li>
                            </ul>
                            <p v-else class="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                No matching options, or every match is already assigned here.
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center justify-between gap-2 text-sm text-gray-500 dark:text-gray-400">
                            <span>Page {{ lookupPage }} / {{ lookupLastPage }}</span>
                            <div class="flex gap-2">
                                <button type="button" class="rounded border border-gray-200 px-2 py-1 dark:border-gray-600" :disabled="lookupPage <= 1 || lookupLoading" @click="lookupPrev">Prev</button>
                                <button type="button" class="rounded border border-gray-200 px-2 py-1 dark:border-gray-600" :disabled="lookupPage >= lookupLastPage || lookupLoading" @click="lookupNext">Next</button>
                            </div>
                        </div>
                    </div>

                    <p v-if="formError" class="text-sm text-red-600 dark:text-red-400">{{ formError }}</p>
                </div>

                <div class="flex shrink-0 justify-end gap-2 border-t border-gray-100 px-5 py-4 dark:border-gray-700">
                    <button type="button" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800" @click="closeModal">Cancel</button>
                    <button type="button" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50" :disabled="saving" @click="submitModal">
                        {{ saving ? 'Saving…' : 'Save' }}
                    </button>
                </div>
            </div>
        </Modal>
    </section>
</template>
