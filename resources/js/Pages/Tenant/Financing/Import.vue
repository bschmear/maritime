<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import RecordSelect from '@/Components/Tenant/RecordSelect.vue';
import axios from 'axios';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    financingImportDefaults: { type: Object, default: () => ({}) },
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
const assetUnitMatchField = ref('serial_number');
const vendorId = ref(null);
const daysAlertThreshold = ref(props.financingImportDefaults?.days_alert_threshold ?? '');
const interestAlertThreshold = ref(props.financingImportDefaults?.interest_alert_threshold ?? '');
const preview = ref(null);
const importResult = ref(null);
const rawPreviewRows = ref([]);
const totalRawRows = ref(0);
const suggestedHeaderRowIndex = ref(0);
const selectedHeaderRowIndex = ref(0);

const matchFields = computed(() => props.financingImportDefaults?.match_fields ?? [
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
        if (! Number.isFinite(parsed)) {
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

const matchedPreviewRows = computed(() => {
    if (! preview.value?.rows) {
        return [];
    }

    return preview.value.rows.filter((row) => row.asset_unit != null && row.action !== 'skip');
});

const unlinkedPreviewRows = computed(() => {
    if (! preview.value?.rows) {
        return [];
    }

    return preview.value.rows.filter((row) => row.asset_unit == null && row.action !== 'skip');
});

const importablePreviewRows = computed(() => {
    if (! preview.value?.rows) {
        return [];
    }

    return preview.value.rows.filter((row) => row.action !== 'skip');
});

function matchValueForRow(source) {
    return String(source.match_value ?? source.serial_vin ?? '').trim();
}

function createAssetUnitHref(source) {
    const value = matchValueForRow(source);
    const params = new URLSearchParams();

    if (value !== '') {
        if (assetUnitMatchField.value === 'hin') {
            params.set('hin', value);
        } else {
            params.set('serial_number', value);
        }
    }

    if (source.id) {
        params.set('link_financing_id', String(source.id));
    }

    params.set('return_url', route('financings.import'));

    const query = params.toString();

    return query === '' ? route('assetunits.create') : `${route('assetunits.create')}?${query}`;
}

function findUnitHref(financingId) {
    return route('financings.edit', financingId);
}

function unlinkedReasonLabel(reasonOrStatus) {
    if (reasonOrStatus === 'ambiguous') {
        return 'Multiple matching units';
    }
    if (reasonOrStatus === 'duplicate') {
        return 'Duplicate row';
    }

    return 'No matching unit';
}

function importActionLabel(action) {
    if (action === 'create_unlinked') {
        return 'Import unlinked';
    }
    if (action === 'update') {
        return 'Update';
    }
    if (action === 'create') {
        return 'Create';
    }

    return action;
}

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Financings', href: route('financings.index') },
    { label: 'Import data' },
]);

const lenderField = {
    label: 'Lender',
    type: 'record',
    typeDomain: 'Vendor',
    relationship: 'vendor',
    create: true,
    record_filter_field: 'vendor_type',
};

function optionalInteger(value) {
    if (value === '' || value === null || value === undefined) {
        return null;
    }

    const parsed = Number(value);

    return Number.isFinite(parsed) ? Math.max(0, Math.trunc(parsed)) : null;
}

function optionalAmount(value) {
    if (value === '' || value === null || value === undefined) {
        return null;
    }

    const parsed = Number(value);

    return Number.isFinite(parsed) ? Math.max(0, parsed) : null;
}

const bulkThresholdSummary = computed(() => {
    const days = optionalInteger(daysAlertThreshold.value);
    const interest = optionalAmount(interestAlertThreshold.value);
    const parts = [];

    if (days !== null) {
        parts.push(`${days} days in inventory`);
    }

    if (interest !== null) {
        parts.push(`$${interest.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} accrued interest`);
    }

    return parts;
});

function resetAlertThresholds() {
    daysAlertThreshold.value = props.financingImportDefaults?.days_alert_threshold ?? '';
    interestAlertThreshold.value = props.financingImportDefaults?.interest_alert_threshold ?? '';
}

async function onFileSelected(event) {
    const file = event.target.files?.[0];
    if (!file) return;

    parsing.value = true;
    error.value = '';

    try {
        const formData = new FormData();
        formData.append('file', file);

        const { data } = await axios.post(route('financings.import.parse'), formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        cacheKey.value = data.cache_key;
        rawPreviewRows.value = data.preview_rows ?? [];
        totalRawRows.value = data.total_raw_rows ?? rawPreviewRows.value.length;
        suggestedHeaderRowIndex.value = data.suggested_header_row_index ?? 0;
        selectedHeaderRowIndex.value = suggestedHeaderRowIndex.value;
        step.value = 2;
    } catch (e) {
        error.value = e.response?.data?.message ?? 'Failed to parse CSV.';
    } finally {
        parsing.value = false;
        event.target.value = '';
    }
}

async function confirmHeaderRow() {
    confirmingHeader.value = true;
    error.value = '';

    try {
        const { data } = await axios.post(route('financings.import.confirm-header'), {
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
        const { data } = await axios.post(route('financings.import.preview'), {
            cache_key: cacheKey.value,
            match_column: matchColumn.value,
            asset_unit_match_field: assetUnitMatchField.value,
            column_map: columnMap.value,
            vendor_id: vendorId.value,
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
        const { data } = await axios.post(route('financings.import.run'), {
            cache_key: cacheKey.value,
            match_column: matchColumn.value,
            asset_unit_match_field: assetUnitMatchField.value,
            vendor_id: vendorId.value,
            column_map: columnMap.value,
            days_alert_threshold: optionalInteger(daysAlertThreshold.value),
            interest_alert_threshold: optionalAmount(interestAlertThreshold.value),
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
    resetAlertThresholds();
}
</script>

<template>
    <Head title="Import financing data" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Import financing data</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Upload a lender aging report CSV. Columns map 1:1 to financing fields.
                        </p>
                    </div>
                    <Link
                        :href="route('financings.index')"
                        class="inline-flex rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200"
                    >
                        Back to financings
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
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">CSV file</label>
                <input
                    type="file"
                    accept=".csv,text/csv"
                    class="mt-2 block w-full text-sm"
                    :disabled="parsing"
                    @change="onFileSelected"
                />
                <p v-if="parsing" class="mt-2 text-sm text-gray-500">Parsing file…</p>
                <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                    Expected format: Northpoint-style inventory aging report with columns such as Dealer Name, Serial/VIN, Invoice Number, etc.
                </p>
            </section>

            <section v-else-if="step === 2" class="space-y-6">
                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Confirm header row</h3>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        We guessed row {{ suggestedHeaderRowIndex + 1 }} is the column header. Click the correct row, then continue.
                    </p>
                    <p v-if="totalRawRows > rawPreviewRows.length" class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        Showing the first {{ rawPreviewRows.length }} of {{ totalRawRows }} rows. Use the row number field if the header is further down.
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
                    <ul v-if="preamble.length" class="mt-2 space-y-1 text-xs text-gray-500 dark:text-gray-400">
                        <li v-for="(line, i) in preamble.slice(0, 4)" :key="i">{{ line }}</li>
                    </ul>
                </div>

                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 space-y-4">
                    <div>
                        <label class="block text-sm font-medium">CSV column to match asset units</label>
                        <select v-model="matchColumn" class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                            <option v-for="col in columns" :key="col" :value="col">{{ col }}</option>
                        </select>
                        <p class="mt-1 text-xs text-gray-500">Usually Serial/VIN — matched against your selected field first, then the other identifier if needed.</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Match against asset unit field</label>
                        <div class="mt-2 flex flex-wrap gap-4">
                            <label v-for="f in matchFields" :key="f.value" class="inline-flex items-center gap-2 text-sm">
                                <input v-model="assetUnitMatchField" type="radio" :value="f.value" />
                                {{ f.label }}
                            </label>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Lender vendor</label>
                        <RecordSelect
                            id="financing-import-lender"
                            :field="lenderField"
                            v-model="vendorId"
                            field-key="vendor_id"
                            filter-by="vendor_type"
                            :filter-value="3"
                        />
                    </div>
                </div>

                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Alert thresholds</h3>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Applied to every financing created or updated in this import. Leave blank to keep existing per-record values on updates and use account defaults for new records.
                    </p>
                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="days-alert-threshold" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Days alert threshold
                            </label>
                            <input
                                id="days-alert-threshold"
                                v-model="daysAlertThreshold"
                                type="number"
                                min="0"
                                step="1"
                                placeholder="Account default"
                                class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-700"
                            />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Notify when days in inventory exceeds this value.</p>
                        </div>
                        <div>
                            <label for="interest-alert-threshold" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Interest alert threshold
                            </label>
                            <input
                                id="interest-alert-threshold"
                                v-model="interestAlertThreshold"
                                type="number"
                                min="0"
                                step="0.01"
                                placeholder="Account default"
                                class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-700"
                            />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Notify when accrued interest exceeds this amount.</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Column mapping</h3>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Each CSV column maps to one financing field (1:1).</p>
                    <div class="mt-4 space-y-2">
                        <div v-for="col in columns" :key="col" class="grid grid-cols-1 gap-2 sm:grid-cols-2 text-sm">
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
                        :disabled="!matchColumn || !vendorId || previewing"
                        @click="runPreview"
                    >
                        {{ previewing ? 'Previewing…' : 'Preview import' }}
                    </button>
                </div>
            </section>

            <section v-else-if="step === 4 && preview" class="space-y-6">
                <div
                    v-if="bulkThresholdSummary.length"
                    class="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900 dark:border-blue-800 dark:bg-blue-950/40 dark:text-blue-100"
                >
                    <p class="font-medium">Bulk alert thresholds</p>
                    <p class="mt-1">Will be set on all imported records: {{ bulkThresholdSummary.join(' and ') }}.</p>
                </div>

                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="flex flex-wrap gap-4 text-sm">
                        <span class="text-green-700 dark:text-green-300">Linked: {{ preview.summary.matched }}</span>
                        <span class="text-amber-700 dark:text-amber-300">Unlinked: {{ preview.summary.unlinked }}</span>
                        <span class="text-red-700 dark:text-red-300">Ambiguous: {{ preview.summary.ambiguous }}</span>
                        <span class="text-gray-500 dark:text-gray-400">Skipped: {{ preview.summary.skipped }}</span>
                    </div>
                    <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                        Financing records import even when no asset unit matches. Unlinked records can be connected afterward by finding or creating a unit.
                    </p>
                </div>

                <div v-if="matchedPreviewRows.length" class="space-y-3">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                        Linked to asset units ({{ matchedPreviewRows.length }})
                    </h3>
                    <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                        <div class="max-h-96 overflow-auto">
                            <table class="min-w-full text-left text-xs">
                                <thead class="sticky top-0 bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <th class="px-3 py-2">Serial / match</th>
                                        <th class="px-3 py-2">Invoice #</th>
                                        <th class="px-3 py-2">Asset unit</th>
                                        <th class="px-3 py-2">Import action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="row in matchedPreviewRows"
                                        :key="`matched-${row.row_index}`"
                                        class="border-t border-gray-100 dark:border-gray-700"
                                    >
                                        <td class="px-3 py-2">{{ row.match_value || row.mapped?.serial_vin || '—' }}</td>
                                        <td class="px-3 py-2">{{ row.mapped?.lender_invoice_number ?? '—' }}</td>
                                        <td class="px-3 py-2">
                                            <Link
                                                v-if="row.asset_unit?.id"
                                                :href="route('assetunits.show', row.asset_unit.id)"
                                                class="text-primary-600 hover:underline dark:text-primary-400"
                                            >
                                                {{ row.asset_unit.display_name }}
                                            </Link>
                                            <span v-else>—</span>
                                        </td>
                                        <td class="px-3 py-2">{{ importActionLabel(row.action) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div v-if="unlinkedPreviewRows.length" class="space-y-3">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                        Unlinked rows ({{ unlinkedPreviewRows.length }})
                    </h3>
                    <div class="overflow-hidden rounded-lg border border-amber-200 dark:border-amber-800">
                        <div class="max-h-96 overflow-auto">
                            <table class="min-w-full text-left text-xs">
                                <thead class="sticky top-0 bg-amber-50 dark:bg-amber-950/40">
                                    <tr>
                                        <th class="px-3 py-2">Serial / match</th>
                                        <th class="px-3 py-2">Invoice #</th>
                                        <th class="px-3 py-2">Model</th>
                                        <th class="px-3 py-2">Reason</th>
                                        <th class="px-3 py-2">Import action</th>
                                        <th class="px-3 py-2"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="row in unlinkedPreviewRows"
                                        :key="`unlinked-${row.row_index}`"
                                        class="border-t border-amber-100 dark:border-amber-900/40"
                                    >
                                        <td class="px-3 py-2 font-medium">{{ row.match_value || row.mapped?.serial_vin || '—' }}</td>
                                        <td class="px-3 py-2">{{ row.mapped?.lender_invoice_number ?? '—' }}</td>
                                        <td class="px-3 py-2">{{ row.mapped?.model_number ?? '—' }}</td>
                                        <td class="px-3 py-2">{{ unlinkedReasonLabel(row.status) }}</td>
                                        <td class="px-3 py-2">{{ importActionLabel(row.action) }}</td>
                                        <td class="px-3 py-2 text-right">
                                            <Link
                                                v-if="matchValueForRow(row) !== ''"
                                                :href="createAssetUnitHref(row)"
                                                class="inline-flex rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                                            >
                                                Create asset unit
                                            </Link>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div v-if="!importablePreviewRows.length" class="rounded-lg border border-gray-200 bg-white p-6 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                    No rows to import.
                </div>

                <div class="flex flex-wrap justify-end gap-2">
                    <button type="button" class="rounded-lg border px-4 py-2 text-sm" @click="step = 3">Back</button>
                    <button
                        type="button"
                        class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                        :disabled="importing || !importablePreviewRows.length"
                        @click="runImport"
                    >
                        {{ importing ? 'Importing…' : `Run import (${importablePreviewRows.length} rows)` }}
                    </button>
                </div>
            </section>

            <section v-else-if="step === 5 && importResult" class="space-y-6">
                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 space-y-3">
                    <p class="text-sm text-green-700 dark:text-green-300">
                        Created {{ importResult.created }}, updated {{ importResult.updated }}, skipped {{ importResult.skipped }}.
                        Linked {{ importResult.linked ?? 0 }}, unlinked {{ importResult.unlinked ?? 0 }}.
                    </p>
                    <ul v-if="importResult.errors?.length" class="max-h-48 overflow-auto text-xs text-amber-700 dark:text-amber-300">
                        <li v-for="(err, i) in importResult.errors" :key="i">{{ err }}</li>
                    </ul>
                </div>

                <div v-if="importResult.unlinked_financings?.length" class="space-y-3">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                        Unlinked financing records — find or create an asset unit
                    </h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        These financing records were saved without an asset unit. Link each one by searching for an existing unit or creating a new one.
                    </p>
                    <div class="overflow-hidden rounded-lg border border-amber-200 dark:border-amber-800">
                        <div class="max-h-[28rem] overflow-auto">
                            <table class="min-w-full text-left text-xs">
                                <thead class="sticky top-0 bg-amber-50 dark:bg-amber-950/40">
                                    <tr>
                                        <th class="px-3 py-2">Financing</th>
                                        <th class="px-3 py-2">Serial / match</th>
                                        <th class="px-3 py-2">Invoice #</th>
                                        <th class="px-3 py-2">Model</th>
                                        <th class="px-3 py-2">Balance</th>
                                        <th class="px-3 py-2">Reason</th>
                                        <th class="px-3 py-2"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="financing in importResult.unlinked_financings"
                                        :key="financing.id"
                                        class="border-t border-amber-100 dark:border-amber-900/40"
                                    >
                                        <td class="px-3 py-2">
                                            <Link
                                                :href="route('financings.show', financing.id)"
                                                class="font-medium text-primary-600 hover:underline dark:text-primary-400"
                                            >
                                                {{ financing.display_name }}
                                            </Link>
                                        </td>
                                        <td class="px-3 py-2">{{ financing.serial_vin || financing.match_value || '—' }}</td>
                                        <td class="px-3 py-2">{{ financing.lender_invoice_number ?? '—' }}</td>
                                        <td class="px-3 py-2">{{ financing.model_number ?? '—' }}</td>
                                        <td class="px-3 py-2">{{ financing.current_balance ?? '—' }}</td>
                                        <td class="px-3 py-2">{{ unlinkedReasonLabel(financing.reason) }}</td>
                                        <td class="px-3 py-2 text-right">
                                            <div class="flex flex-wrap justify-end gap-2">
                                                <Link
                                                    :href="findUnitHref(financing.id)"
                                                    class="inline-flex rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                                                >
                                                    Find unit
                                                </Link>
                                                <Link
                                                    :href="createAssetUnitHref(financing)"
                                                    class="inline-flex rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-primary-700"
                                                >
                                                    Create asset unit
                                                </Link>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2">
                    <button type="button" class="rounded-lg border px-4 py-2 text-sm" @click="resetImport">Import another file</button>
                    <Link
                        :href="route('financings.index')"
                        class="inline-flex rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white"
                    >
                        View financings
                    </Link>
                </div>
            </section>
        </div>
    </TenantLayout>
</template>
