<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import axios from 'axios';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    importDefaults: { type: Object, default: () => ({}) },
    importFieldOptions: { type: Array, default: () => [] },
});

const step = ref(1);
const parsing = ref(false);
const confirmingHeader = ref(false);
const previewing = ref(false);
const importing = ref(false);
const error = ref('');

const cacheKey = ref('');
const columns = ref([]);
const rowCount = ref(0);
const preamble = ref([]);
const columnMap = ref({});
const matchColumn = ref('');
const matchField = ref('id');
const preview = ref(null);
const importResult = ref(null);
const rawPreviewRows = ref([]);
const totalRawRows = ref(0);
const suggestedHeaderRowIndex = ref(0);
const selectedHeaderRowIndex = ref(0);

const matchFields = computed(() => props.importDefaults?.match_fields ?? [
    { value: 'id', label: 'Unit ID' },
    { value: 'hin', label: 'Hull number (HIN)' },
    { value: 'serial_number', label: 'Serial number' },
]);

const fieldOptions = computed(() => props.importFieldOptions ?? []);

const headerPreviewColumnCount = computed(() => {
    return rawPreviewRows.value.reduce((max, row) => Math.max(max, row?.length ?? 0), 0);
});

const headerRowNumber = computed({
    get: () => selectedHeaderRowIndex.value + 1,
    set: (value) => {
        const parsed = Number(value);
        if (!Number.isFinite(parsed)) {
            return;
        }

        const index = Math.max(0, Math.min(totalRawRows.value - 1, parsed - 1));
        selectedHeaderRowIndex.value = index;
    },
});

function formatPreviewCell(value) {
    const text = value == null ? '' : String(value).trim();
    return text === '' ? '—' : text;
}

function selectHeaderRow(index) {
    selectedHeaderRowIndex.value = index;
}

const updatablePreviewRows = computed(() => {
    if (!preview.value?.rows) {
        return [];
    }

    return preview.value.rows.filter((row) => row.action === 'update');
});

const skippedPreviewRows = computed(() => {
    if (!preview.value?.rows) {
        return [];
    }

    return preview.value.rows.filter((row) => row.action === 'skip');
});

function importActionLabel(action) {
    if (action === 'update') {
        return 'Update';
    }
    if (action === 'no_change') {
        return 'No changes';
    }
    if (action === 'skip') {
        return 'Skip';
    }

    return action;
}

function formatChanges(changes) {
    if (!changes || typeof changes !== 'object') {
        return '—';
    }

    return Object.entries(changes)
        .map(([key, value]) => `${key}: ${value}`)
        .join(', ');
}

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Assets', href: route('assets.index') },
    { label: 'Asset Units', href: route('assets.units.global-index') },
    { label: 'Import' },
]);

async function onFileSelected(event) {
    const file = event.target.files?.[0];
    if (!file) {
        return;
    }

    parsing.value = true;
    error.value = '';

    try {
        const formData = new FormData();
        formData.append('file', file);

        const { data } = await axios.post(route('assetunits.import.parse'), formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        cacheKey.value = data.cache_key;
        rawPreviewRows.value = data.preview_rows ?? [];
        totalRawRows.value = data.total_raw_rows ?? rawPreviewRows.value.length;
        suggestedHeaderRowIndex.value = data.suggested_header_row_index ?? 0;
        selectedHeaderRowIndex.value = suggestedHeaderRowIndex.value;
        step.value = 2;
    } catch (e) {
        error.value = e.response?.data?.message ?? 'Failed to parse file.';
    } finally {
        parsing.value = false;
        event.target.value = '';
    }
}

async function confirmHeaderRow() {
    confirmingHeader.value = true;
    error.value = '';

    try {
        const { data } = await axios.post(route('assetunits.import.confirm-header'), {
            cache_key: cacheKey.value,
            header_row_index: selectedHeaderRowIndex.value,
        });

        columns.value = data.columns ?? [];
        rowCount.value = data.row_count ?? 0;
        preamble.value = data.preamble ?? [];
        columnMap.value = { ...(data.default_column_map ?? {}) };
        matchColumn.value = data.suggested_match_column ?? columns.value[0] ?? '';
        step.value = 3;
    } catch (e) {
        error.value = e.response?.data?.message ?? 'Failed to apply header row.';
    } finally {
        confirmingHeader.value = false;
    }
}

async function runPreview() {
    previewing.value = true;
    error.value = '';

    try {
        const { data } = await axios.post(route('assetunits.import.preview'), {
            cache_key: cacheKey.value,
            match_column: matchColumn.value,
            match_field: matchField.value,
            column_map: columnMap.value,
        });
        preview.value = data;
        step.value = 4;
    } catch (e) {
        error.value = e.response?.data?.message ?? 'Preview failed.';
    } finally {
        previewing.value = false;
    }
}

async function runImport() {
    importing.value = true;
    error.value = '';

    try {
        const { data } = await axios.post(route('assetunits.import.run'), {
            cache_key: cacheKey.value,
            match_column: matchColumn.value,
            match_field: matchField.value,
            column_map: columnMap.value,
        });
        importResult.value = data;
        step.value = 5;
    } catch (e) {
        error.value = e.response?.data?.message ?? 'Import failed.';
    } finally {
        importing.value = false;
    }
}

function resetImport() {
    step.value = 1;
    error.value = '';
    preview.value = null;
    importResult.value = null;
    cacheKey.value = '';
    columns.value = [];
    rowCount.value = 0;
    preamble.value = [];
    rawPreviewRows.value = [];
    totalRawRows.value = 0;
    suggestedHeaderRowIndex.value = 0;
    selectedHeaderRowIndex.value = 0;
}
</script>

<template>
    <Head title="Import asset units" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Bulk update asset units</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Upload a spreadsheet to update status, condition, cost, and asking price. Export first for the recommended column layout.
                        </p>
                    </div>
                    <Link
                        :href="route('assets.units.global-index')"
                        class="inline-flex rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200"
                    >
                        Back to units
                    </Link>
                </div>
            </div>
        </template>

        <div class="w-full min-w-0 space-y-6">
            <div class="flex flex-wrap gap-2 text-sm">
                <span :class="step >= 1 ? 'font-semibold text-primary-600' : 'text-gray-400'">1. Upload</span>
                <span class="text-gray-300">/</span>
                <span :class="step >= 2 ? 'font-semibold text-primary-600' : 'text-gray-400'">2. Header row</span>
                <span class="text-gray-300">/</span>
                <span :class="step >= 3 ? 'font-semibold text-primary-600' : 'text-gray-400'">3. Map</span>
                <span class="text-gray-300">/</span>
                <span :class="step >= 4 ? 'font-semibold text-primary-600' : 'text-gray-400'">4. Preview</span>
                <span class="text-gray-300">/</span>
                <span :class="step >= 5 ? 'font-semibold text-primary-600' : 'text-gray-400'">5. Done</span>
            </div>

            <p v-if="error" class="rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-200">{{ error }}</p>

            <section v-if="step === 1" class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Spreadsheet file</label>
                <input
                    type="file"
                    accept=".csv,.xlsx,.xls,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                    class="mt-2 block w-full text-sm"
                    :disabled="parsing"
                    @change="onFileSelected"
                />
                <p v-if="parsing" class="mt-2 text-sm text-gray-500">Parsing file…</p>
                <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                    CSV or Excel (.xlsx). For dropdowns on Status and Condition, export as Excel from the units list.
                </p>
            </section>

            <section v-else-if="step === 2" class="space-y-6">
                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Confirm header row</h3>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        We guessed row {{ suggestedHeaderRowIndex + 1 }} is the column header. Click the correct row, then continue.
                    </p>
                    <div class="mt-4 flex flex-wrap items-end gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400">Header row number</label>
                            <input
                                v-model.number="headerRowNumber"
                                type="number"
                                min="1"
                                :max="totalRawRows"
                                class="mt-1 w-28 rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-700"
                            />
                        </div>
                    </div>
                </div>

                <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                    <div class="max-h-[28rem] overflow-auto">
                        <table class="min-w-full text-left text-xs">
                            <thead class="sticky top-0 bg-gray-50 dark:bg-gray-800">
                                <tr>
                                    <th class="w-28 px-3 py-2">Header</th>
                                    <th class="w-16 px-3 py-2">Row</th>
                                    <th
                                        v-for="colIndex in headerPreviewColumnCount"
                                        :key="colIndex"
                                        class="px-3 py-2"
                                    >
                                        Col {{ colIndex }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(row, rowIndex) in rawPreviewRows"
                                    :key="rowIndex"
                                    class="cursor-pointer border-t border-gray-100 dark:border-gray-700"
                                    :class="selectedHeaderRowIndex === rowIndex
                                        ? 'bg-primary-50 dark:bg-primary-900/20'
                                        : 'hover:bg-gray-50 dark:hover:bg-gray-800/60'"
                                    @click="selectHeaderRow(rowIndex)"
                                >
                                    <td class="px-3 py-2 align-top">
                                        <label class="inline-flex items-center gap-2">
                                            <input
                                                type="radio"
                                                name="header-row"
                                                :value="rowIndex"
                                                :checked="selectedHeaderRowIndex === rowIndex"
                                                @change="selectHeaderRow(rowIndex)"
                                            />
                                            <span
                                                v-if="rowIndex === suggestedHeaderRowIndex"
                                                class="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-medium text-amber-800 dark:bg-amber-900/40 dark:text-amber-200"
                                            >
                                                Best guess
                                            </span>
                                        </label>
                                    </td>
                                    <td class="px-3 py-2 align-top font-medium text-gray-700 dark:text-gray-300">
                                        {{ rowIndex + 1 }}
                                    </td>
                                    <td
                                        v-for="colIndex in headerPreviewColumnCount"
                                        :key="colIndex"
                                        class="whitespace-nowrap px-3 py-2 align-top text-gray-600 dark:text-gray-400"
                                        :title="formatPreviewCell(row[colIndex - 1])"
                                    >
                                        {{ formatPreviewCell(row[colIndex - 1]) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="flex justify-end gap-2">
                    <button type="button" class="rounded-lg border px-4 py-2 text-sm" @click="resetImport">Start over</button>
                    <button
                        type="button"
                        class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                        :disabled="confirmingHeader"
                        @click="confirmHeaderRow"
                    >
                        {{ confirmingHeader ? 'Applying…' : 'Continue to column mapping' }}
                    </button>
                </div>
            </section>

            <section v-else-if="step === 3" class="space-y-6">
                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ rowCount }} data rows detected.</p>
                </div>

                <div class="space-y-4 rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div>
                        <label class="block text-sm font-medium">Spreadsheet column to match units</label>
                        <select v-model="matchColumn" class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                            <option v-for="col in columns" :key="col" :value="col">{{ col }}</option>
                        </select>
                        <p class="mt-1 text-xs text-gray-500">Usually ID when using an exported file.</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Match against unit field</label>
                        <div class="mt-2 flex flex-wrap gap-4">
                            <label v-for="f in matchFields" :key="f.value" class="inline-flex items-center gap-2 text-sm">
                                <input v-model="matchField" type="radio" :value="f.value" />
                                {{ f.label }}
                            </label>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Column mapping</h3>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Map columns to unit fields. Only Status, Condition, Cost, and Asking Price are updated on import.
                    </p>
                    <div class="mt-4 space-y-2">
                        <div v-for="col in columns" :key="col" class="grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">
                            <span class="truncate text-gray-600 dark:text-gray-400">{{ col }}</span>
                            <select v-model="columnMap[col]" class="rounded border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-700">
                                <option value="">Skip</option>
                                <option v-for="opt in fieldOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2">
                    <button type="button" class="rounded-lg border px-4 py-2 text-sm" @click="resetImport">Start over</button>
                    <button
                        type="button"
                        class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                        :disabled="!matchColumn || previewing"
                        @click="runPreview"
                    >
                        {{ previewing ? 'Previewing…' : 'Preview import' }}
                    </button>
                </div>
            </section>

            <section v-else-if="step === 4 && preview" class="space-y-6">
                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="flex flex-wrap gap-4 text-sm">
                        <span class="text-green-700 dark:text-green-300">Matched: {{ preview.summary.matched }}</span>
                        <span class="text-amber-700 dark:text-amber-300">Unmatched: {{ preview.summary.unmatched }}</span>
                        <span class="text-red-700 dark:text-red-300">Ambiguous: {{ preview.summary.ambiguous }}</span>
                        <span class="text-gray-500 dark:text-gray-400">No changes: {{ preview.summary.no_changes }}</span>
                        <span class="text-gray-500 dark:text-gray-400">Skipped: {{ preview.summary.skipped }}</span>
                    </div>
                </div>

                <div v-if="updatablePreviewRows.length" class="space-y-3">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                        Rows to update ({{ updatablePreviewRows.length }})
                    </h3>
                    <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                        <div class="max-h-96 overflow-auto">
                            <table class="min-w-full text-left text-xs">
                                <thead class="sticky top-0 bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <th class="px-3 py-2">Match value</th>
                                        <th class="px-3 py-2">Unit</th>
                                        <th class="px-3 py-2">Changes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="row in updatablePreviewRows"
                                        :key="`update-${row.row_index}`"
                                        class="border-t border-gray-100 dark:border-gray-700"
                                    >
                                        <td class="px-3 py-2">{{ row.match_value || '—' }}</td>
                                        <td class="px-3 py-2">
                                            <Link
                                                v-if="row.asset_unit?.id"
                                                :href="route('assetunits.show', row.asset_unit.id)"
                                                class="text-primary-600 hover:underline dark:text-primary-400"
                                            >
                                                {{ row.asset_unit.display_name }}
                                            </Link>
                                        </td>
                                        <td class="px-3 py-2">{{ formatChanges(row.changes) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div v-if="skippedPreviewRows.length" class="space-y-3">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                        Skipped rows ({{ skippedPreviewRows.length }})
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Unmatched, ambiguous, duplicate, or rows with no field changes.
                    </p>
                </div>

                <div class="flex flex-wrap justify-end gap-2">
                    <button type="button" class="rounded-lg border px-4 py-2 text-sm" @click="step = 3">Back</button>
                    <button
                        type="button"
                        class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                        :disabled="importing || !updatablePreviewRows.length"
                        @click="runImport"
                    >
                        {{ importing ? 'Importing…' : `Run import (${updatablePreviewRows.length} rows)` }}
                    </button>
                </div>
            </section>

            <section v-else-if="step === 5 && importResult" class="space-y-6">
                <div class="space-y-3 rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <p class="text-sm text-green-700 dark:text-green-300">
                        Updated {{ importResult.updated }}, no changes {{ importResult.no_changes }}, skipped {{ importResult.skipped }}.
                    </p>
                    <ul v-if="importResult.errors?.length" class="max-h-48 overflow-auto text-xs text-amber-700 dark:text-amber-300">
                        <li v-for="(err, i) in importResult.errors" :key="i">{{ err }}</li>
                    </ul>
                </div>

                <div class="flex justify-end gap-2">
                    <button type="button" class="rounded-lg border px-4 py-2 text-sm" @click="resetImport">Import another file</button>
                    <Link
                        :href="route('assets.units.global-index')"
                        class="inline-flex rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white"
                    >
                        View units
                    </Link>
                </div>
            </section>
        </div>
    </TenantLayout>
</template>
