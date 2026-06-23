<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import Modal from '@/Components/Modal.vue';
import RecordSelect from '@/Components/Tenant/RecordSelect.vue';
import { fetchSoleLocationIdForSubsidiary } from '@/composables/useSubsidiaryLocationAutofill';
import axios from 'axios';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const DEFAULT_UNIT_STATUS = 4;

const props = defineProps({
    quickbooks: {
        type: Object,
        default: () => ({ connected: false, sync_bills_enabled: false }),
    },
    unitStatusOptions: {
        type: Array,
        default: () => [],
    },
});

const step = ref(1);
const parsing = ref(false);
const extracting = ref(false);
const confirming = ref(false);
const error = ref('');

const boatMakeId = ref(null);
const brand = ref(null);
const cacheKey = ref('');
const aiInstructions = ref('');
const saveAiInstructions = ref(true);
const aiInstructionsGenerated = ref(false);

const extraction = ref(null);
const excludedWithoutIdentifier = ref(0);
const rows = ref([]);

const vendorId = ref(null);
const vendorDisplayName = ref(null);

const placementMode = ref('all');
const bulkSubsidiaryId = ref(null);
const bulkSubsidiaryLabel = ref(null);
const bulkLocationId = ref(null);
const bulkLocationLabel = ref(null);

const statusMode = ref('all');
const bulkStatus = ref(DEFAULT_UNIT_STATUS);

const showBillModal = ref(false);
const createBill = ref(true);
const syncQuickbooks = ref(false);
const confirmResult = ref(null);

const brandField = {
    label: 'Brand',
    type: 'record',
    typeDomain: 'BoatMake',
    relationship: 'boatMake',
    displayField: 'display_name',
};

const vendorField = {
    label: 'Default vendor',
    type: 'record',
    typeDomain: 'Vendor',
    relationship: 'vendor',
    create: true,
    displayField: 'display_name',
};

const assetField = {
    label: 'Asset',
    type: 'record',
    typeDomain: 'Asset',
    relationship: 'asset',
    displayField: 'display_name',
};

const subsidiaryField = {
    label: 'Subsidiary',
    type: 'record',
    typeDomain: 'Subsidiary',
    relationship: 'subsidiary',
    displayField: 'display_name',
};

const locationField = {
    label: 'Location',
    type: 'record',
    typeDomain: 'Location',
    relationship: 'location',
    filterby: 'subsidiary_id',
    record_filter_field: 'subsidiary_id',
    displayField: 'display_name',
};

function variantFieldForRow(row) {
    return {
        label: 'Variant',
        type: 'record',
        typeDomain: 'AssetVariant',
        relationship: 'assetVariant',
        filterby: 'asset_id',
        record_filter_field: 'asset_id',
        displayField: 'display_name',
    };
}

function rowRecordStub(row) {
    const stub = {};

    if (row.asset_id) {
        stub.asset_id = row.asset_id;
        stub.asset = {
            id: row.asset_id,
            display_name: row.asset_display_name || '',
            has_variants: !! row.asset_has_variants,
        };
    }

    if (row.asset_variant_id) {
        stub.asset_variant_id = row.asset_variant_id;
        stub.asset_variant = {
            id: row.asset_variant_id,
            display_name: row.variant_display_name || '',
        };
        stub.assetVariant = stub.asset_variant;
    }

    return stub;
}

function vendorRecordStub() {
    if (! vendorId.value) {
        return {};
    }

    return {
        vendor_id: vendorId.value,
        vendor: {
            id: vendorId.value,
            display_name: vendorDisplayName.value ?? brand.value?.vendor?.display_name ?? '',
        },
    };
}

function syncVendorFromBrand(brandData) {
    vendorId.value = brandData?.vendor_id ?? null;
    vendorDisplayName.value = brandData?.vendor?.display_name ?? null;
}

function onVendorSelected(record) {
    vendorDisplayName.value = record?.display_name ?? null;
    if (brand.value) {
        brand.value = {
            ...brand.value,
            vendor_id: record?.id ?? null,
            vendor: record ?? null,
        };
    }
}

function bulkPlacementRecordStub() {
    const stub = {};

    if (bulkSubsidiaryId.value) {
        stub.subsidiary_id = bulkSubsidiaryId.value;
        stub.subsidiary = {
            id: bulkSubsidiaryId.value,
            display_name: bulkSubsidiaryLabel.value || '',
        };
    }

    if (bulkLocationId.value) {
        stub.location_id = bulkLocationId.value;
        stub.location = {
            id: bulkLocationId.value,
            display_name: bulkLocationLabel.value || '',
        };
    }

    return stub;
}

function rowPlacementRecordStub(row) {
    const stub = rowRecordStub(row);

    if (row.subsidiary_id) {
        stub.subsidiary_id = row.subsidiary_id;
        stub.subsidiary = {
            id: row.subsidiary_id,
            display_name: row.subsidiary_display_name || '',
        };
    }

    if (row.location_id) {
        stub.location_id = row.location_id;
        stub.location = {
            id: row.location_id,
            display_name: row.location_display_name || '',
        };
    }

    return stub;
}

function resolveRowPlacement(row) {
    if (placementMode.value === 'all') {
        return {
            subsidiary_id: bulkSubsidiaryId.value,
            location_id: bulkLocationId.value,
        };
    }

    return {
        subsidiary_id: row.subsidiary_id ?? null,
        location_id: row.location_id ?? null,
    };
}

function resolveRowStatus(row) {
    if (statusMode.value === 'all') {
        return bulkStatus.value;
    }

    return row.status ?? bulkStatus.value;
}

function setStatusMode(mode) {
    if (mode === 'individual' && statusMode.value === 'all') {
        rows.value.forEach((row) => {
            if (row.status == null || row.status === '') {
                row.status = bulkStatus.value;
            }
        });
    }

    statusMode.value = mode;
}

const unitStatusChoices = computed(() => {
    if (props.unitStatusOptions.length) {
        return props.unitStatusOptions;
    }

    return [
        { id: 1, name: 'Available' },
        { id: 2, name: 'Pending' },
        { id: 3, name: 'Sold' },
        { id: 4, name: 'Inbound' },
        { id: 5, name: 'Consignment' },
        { id: 6, name: 'Reserved' },
        { id: 7, name: 'Unavailable' },
    ];
});

async function fetchSoleLocationForSubsidiary(subsidiaryId) {
    const soleLocationId = await fetchSoleLocationIdForSubsidiary(subsidiaryId);
    if (! soleLocationId) {
        return null;
    }

    const url = new URL(route('records.lookup'), window.location.origin);
    url.searchParams.append('type', 'location');
    url.searchParams.append('page', '1');
    url.searchParams.append('per_page', '1');
    url.searchParams.append('filters', JSON.stringify([{
        field: 'id',
        operator: 'equals',
        value: soleLocationId,
    }]));

    try {
        const response = await fetch(url.toString(), {
            method: 'GET',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            credentials: 'same-origin',
        });

        if (! response.ok) {
            return { id: soleLocationId, display_name: null };
        }

        const data = await response.json();
        const record = (data.records ?? [])[0];

        return {
            id: soleLocationId,
            display_name: record?.display_name ?? null,
        };
    } catch {
        return { id: soleLocationId, display_name: null };
    }
}

async function onBulkSubsidiarySelected(record) {
    bulkSubsidiaryLabel.value = record?.display_name ?? null;
    bulkLocationId.value = null;
    bulkLocationLabel.value = null;

    if (! record?.id) {
        return;
    }

    const soleLocation = await fetchSoleLocationForSubsidiary(record.id);
    if (! soleLocation) {
        return;
    }

    bulkLocationId.value = soleLocation.id;
    bulkLocationLabel.value = soleLocation.display_name;
}

function onBulkLocationSelected(record) {
    bulkLocationLabel.value = record?.display_name ?? null;
}

function onRowSubsidiarySelected(row, record) {
    row.subsidiary_display_name = record?.display_name ?? null;
    row.location_id = null;
    row.location_display_name = null;

    if (! record?.id) {
        return;
    }

    void fetchSoleLocationForSubsidiary(record.id).then((soleLocation) => {
        if (! soleLocation) {
            return;
        }
        row.location_id = soleLocation.id;
        row.location_display_name = soleLocation.display_name;
    });
}

function onRowLocationSelected(row, record) {
    row.location_display_name = record?.display_name ?? null;
}

function setPlacementMode(mode) {
    if (mode === 'individual' && placementMode.value === 'all') {
        rows.value.forEach((row) => {
            if (! row.subsidiary_id && bulkSubsidiaryId.value) {
                row.subsidiary_id = bulkSubsidiaryId.value;
                row.subsidiary_display_name = bulkSubsidiaryLabel.value;
            }
            if (! row.location_id && bulkLocationId.value) {
                row.location_id = bulkLocationId.value;
                row.location_display_name = bulkLocationLabel.value;
            }
        });
    }

    placementMode.value = mode;
}

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Assets', href: route('assets.index') },
    { label: 'Asset Units', href: route('assetunits.index') },
    { label: 'Import from document' },
]);

const canShowQuickbooksSync = computed(() =>
    props.quickbooks?.connected && props.quickbooks?.sync_bills_enabled,
);

const vendorHasQuickbooks = computed(() => !!brand.value?.vendor?.quickbooks_id);

function rowHasIdentifier(row) {
    const hin = typeof row.hin === 'string' ? row.hin.trim() : '';
    const serial = typeof row.serial_number === 'string' ? row.serial_number.trim() : '';

    return hin !== '' || serial !== '';
}

const reviewRows = computed(() => rows.value.filter((row) => rowHasIdentifier(row)));

const includedRows = computed(() => reviewRows.value.filter((r) => r.include !== false && ! r.already_exists));

const existingRowsCount = computed(() => reviewRows.value.filter((r) => r.already_exists).length);

const importableRowsCount = computed(() => includedRows.value.length);

const canConfirmUnits = computed(() => {
    if (! includedRows.value.length) {
        return false;
    }

    return includedRows.value.every((row) => {
        if (! row.asset_id) {
            return false;
        }
        if (row.asset_has_variants && ! row.asset_variant_id) {
            return false;
        }

        return true;
    });
});

const matchStatusLabel = (status) => {
    if (status === 'matched') {
        return 'Catalog match';
    }
    if (status === 'needs_attention') {
        return 'Needs review';
    }

    return 'Unmatched';
};

const matchStatusClass = (status) => {
    if (status === 'matched') {
        return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-200';
    }
    if (status === 'needs_attention') {
        return 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-200';
    }

    return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-200';
};

function onBrandSelected(record) {
    if (! record) {
        brand.value = null;
        vendorId.value = null;
        vendorDisplayName.value = null;

        return;
    }

    brand.value = {
        id: record.id,
        display_name: record.display_name,
        vendor_id: record.vendor_id ?? null,
        vendor: record.vendor ?? null,
    };
    syncVendorFromBrand(brand.value);
}

async function onFileSelected(event) {
    const file = event.target.files?.[0];
    if (! file || ! boatMakeId.value) {
        return;
    }

    parsing.value = true;
    error.value = '';

    try {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('boat_make_id', String(boatMakeId.value));

        const { data } = await axios.post(route('assetunits.import.invoice.parse'), formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        cacheKey.value = data.cache_key;
        aiInstructions.value = data.ai_instructions ?? '';
        aiInstructionsGenerated.value = !! data.ai_instructions_generated;
        if (aiInstructionsGenerated.value) {
            saveAiInstructions.value = true;
        }
        brand.value = data.brand ?? brand.value;
        syncVendorFromBrand(brand.value);
        step.value = 2;
    } catch (e) {
        error.value = e.response?.data?.message ?? 'Failed to upload document.';
    } finally {
        parsing.value = false;
        event.target.value = '';
    }
}

async function runExtraction() {
    if (! cacheKey.value) {
        return;
    }

    extracting.value = true;
    error.value = '';
    step.value = 3;

    try {
        const { data } = await axios.post(route('assetunits.import.invoice.extract'), {
            cache_key: cacheKey.value,
            ai_instructions: aiInstructions.value,
            save_ai_instructions: saveAiInstructions.value,
        });

        extraction.value = data.extraction;
        excludedWithoutIdentifier.value = Number(data.extraction?.excluded_without_identifier ?? 0);
        rows.value = (data.rows ?? []).map((row) => ({
            ...row,
            asset_has_variants: !! row.asset_has_variants,
            already_exists: !! row.already_exists,
            status: row.status ?? bulkStatus.value,
            include: row.already_exists ? false : row.include !== false,
        }));
        brand.value = data.brand ?? brand.value;
        syncVendorFromBrand(brand.value);
        step.value = 4;
    } catch (e) {
        error.value = e.response?.data?.message ?? 'AI extraction failed.';
        step.value = 2;
    } finally {
        extracting.value = false;
    }
}

function onAssetSelected(row, record) {
    row.asset_has_variants = !! record?.has_variants;
    row.asset_display_name = record?.display_name ?? null;
    row.asset_variant_id = null;
    row.variant_display_name = null;
    if (record) {
        row.match_status = row.asset_has_variants ? 'needs_attention' : 'matched';
    }
}

function onVariantSelected(row, record) {
    row.variant_display_name = record?.display_name ?? record?.name ?? null;
    if (row.asset_variant_id) {
        row.match_status = 'matched';
    }
}

async function refreshRowExistingFlags(row) {
    if (! rowHasIdentifier(row)) {
        row.already_exists = false;
        row.existing_asset_unit_id = null;
        row.existing_asset_unit_label = null;
        row.existing_match_field = null;

        return;
    }

    const hin = row.hin?.trim?.() ?? row.hin;
    const serial = row.serial_number?.trim?.() ?? row.serial_number;

    try {
        const { data } = await axios.post(route('assetunits.import.invoice.check-existing'), {
            rows: [{ row_index: row.row_index, hin: hin || null, serial_number: serial || null }],
        });
        const flag = data.rows?.[0];
        if (! flag) {
            return;
        }

        row.already_exists = !! flag.already_exists;
        row.existing_asset_unit_id = flag.existing_asset_unit_id ?? null;
        row.existing_asset_unit_label = flag.existing_asset_unit_label ?? null;
        row.existing_match_field = flag.existing_match_field ?? null;
        if (flag.already_exists) {
            row.include = false;
        }
    } catch {
        // Keep current flags if the check fails.
    }
}

function existingMatchLabel(row) {
    if (! row.already_exists) {
        return '';
    }

    if (row.existing_match_field === 'serial_number' && row.serial_number) {
        return `Serial ${row.serial_number}`;
    }

    if (row.hin) {
        return `HIN ${row.hin}`;
    }

    return 'Identifier on file';
}

function openConfirmModal() {
    if (! canConfirmUnits.value) {
        return;
    }
    createBill.value = !! vendorId.value;
    syncQuickbooks.value = canShowQuickbooksSync.value && vendorHasQuickbooks.value;
    showBillModal.value = true;
}

async function confirmImport() {
    if (! canConfirmUnits.value || confirming.value) {
        return;
    }

    confirming.value = true;
    error.value = '';

    try {
        const payload = {
            cache_key: cacheKey.value,
            vendor_id: vendorId.value,
            create_bill: createBill.value,
            sync_quickbooks: syncQuickbooks.value && canShowQuickbooksSync.value,
            rows: reviewRows.value.map((row) => ({
                row_index: row.row_index,
                include: row.include !== false,
                asset_id: row.asset_id,
                asset_variant_id: row.asset_variant_id,
                hin: row.hin,
                serial_number: row.serial_number,
                unit_price: row.unit_price,
                condition: row.condition ?? 1,
                status: resolveRowStatus(row),
                ...resolveRowPlacement(row),
            })),
        };

        const { data } = await axios.post(route('assetunits.import.invoice.confirm'), payload);
        confirmResult.value = data;
        showBillModal.value = false;
        step.value = 5;
    } catch (e) {
        error.value = e.response?.data?.message ?? 'Import failed.';
    } finally {
        confirming.value = false;
    }
}

function goBackToInstructions() {
    error.value = '';
    extraction.value = null;
    excludedWithoutIdentifier.value = 0;
    rows.value = [];
    showBillModal.value = false;
    step.value = 2;
}

function resetImport() {
    step.value = 1;
    boatMakeId.value = null;
    brand.value = null;
    cacheKey.value = '';
    aiInstructions.value = '';
    saveAiInstructions.value = true;
    aiInstructionsGenerated.value = false;
    extraction.value = null;
    excludedWithoutIdentifier.value = 0;
    rows.value = [];
    vendorId.value = null;
    vendorDisplayName.value = null;
    placementMode.value = 'all';
    bulkSubsidiaryId.value = null;
    bulkSubsidiaryLabel.value = null;
    bulkLocationId.value = null;
    bulkLocationLabel.value = null;
    statusMode.value = 'all';
    bulkStatus.value = DEFAULT_UNIT_STATUS;
    confirmResult.value = null;
    error.value = '';
}

function formatMoney(value) {
    const n = Number(value ?? 0);

    return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>

<template>
    <Head title="Import from document" />

    <TenantLayout>
        <template #header>
            <Breadcrumb :items="breadcrumbItems" />
        </template>

        <div class="w-full min-w-0 space-y-6">
            <div class="flex flex-wrap gap-2 text-sm">
                <span :class="step >= 1 ? 'font-semibold text-primary-600' : 'text-gray-400'">1. Brand</span>
                <span class="text-gray-300">/</span>
                <span :class="step >= 2 ? 'font-semibold text-primary-600' : 'text-gray-400'">2. Upload</span>
                <span class="text-gray-300">/</span>
                <span :class="step >= 3 ? 'font-semibold text-primary-600' : 'text-gray-400'">3. Extract</span>
                <span class="text-gray-300">/</span>
                <span :class="step >= 4 ? 'font-semibold text-primary-600' : 'text-gray-400'">4. Review</span>
                <span class="text-gray-300">/</span>
                <span :class="step >= 5 ? 'font-semibold text-primary-600' : 'text-gray-400'">5. Done</span>
            </div>

            <p v-if="error" class="rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-200">{{ error }}</p>

            <section v-if="step === 1" class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Select brand</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Choose the manufacturer brand for this document. Set a default vendor on the brand record for bill creation.
                </p>
                <div class="mt-4 max-w-md">
                    <RecordSelect
                        id="invoice-import-brand"
                        :field="brandField"
                        v-model="boatMakeId"
                        field-key="boat_make_id"
                        @record-selected="onBrandSelected"
                    />
                </div>
                <p v-if="brand && !brand.vendor_id" class="mt-3 text-sm text-amber-700 dark:text-amber-300">
                    This brand has no default vendor.
                    <Link
                        v-if="brand.id"
                        :href="route('boatmakes.show', brand.id)"
                        class="font-medium underline"
                    >
                        Add one on the brand record
                    </Link>
                    or pick a vendor during review.
                </p>
                <div class="mt-6 flex justify-end">
                    <button
                        type="button"
                        class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                        :disabled="!boatMakeId"
                        @click="step = 2"
                    >
                        Continue
                    </button>
                </div>
            </section>

            <section v-else-if="step === 2" class="space-y-6">
                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Upload document PDF</h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Upload the manufacturer document (invoice, packing slip, etc.). AI will extract units using the instructions below.
                    </p>
                    <input
                        type="file"
                        accept="application/pdf,.pdf"
                        class="mt-4 block w-full text-sm"
                        :disabled="parsing"
                        @change="onFileSelected"
                    />
                    <p v-if="parsing" class="mt-2 text-sm text-gray-500">Uploading and analyzing document…</p>
                    <p
                        v-else-if="cacheKey"
                        class="mt-2 rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800 dark:border-green-800 dark:bg-green-950/40 dark:text-green-200"
                    >
                        Document uploaded. Choose another file to replace it, or edit the AI instructions below and extract again.
                    </p>
                </div>

                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">AI instructions for this brand</label>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Saved per brand for your dealership. Write your own, or use the draft AI creates from the uploaded document on first import.
                    </p>
                    <p
                        v-if="aiInstructionsGenerated"
                        class="mt-3 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-900 dark:border-blue-800 dark:bg-blue-950/40 dark:text-blue-100"
                    >
                        AI drafted these instructions from your document. Review and edit before extracting. Check “Save instructions for this brand” to reuse them next time.
                    </p>
                    <textarea
                        v-model="aiInstructions"
                        rows="10"
                        class="mt-2 w-full rounded-lg border-gray-300 font-mono text-xs dark:border-gray-600 dark:bg-gray-700"
                        placeholder="Describe document layout, columns, and how to find HINs, models, and prices…"
                    />
                    <label class="mt-3 inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                        <input v-model="saveAiInstructions" type="checkbox" />
                        Save instructions for this brand
                    </label>
                </div>

                <div class="flex justify-between gap-2">
                    <button type="button" class="rounded-lg border px-4 py-2 text-sm" @click="step = 1">Back</button>
                    <button
                        type="button"
                        class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                        :disabled="!cacheKey || extracting || !aiInstructions.trim()"
                        @click="runExtraction"
                    >
                        Extract with AI
                    </button>
                </div>
            </section>

            <section v-else-if="step === 3" class="rounded-lg border border-gray-200 bg-white p-8 text-center shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="mx-auto mb-4 size-10 animate-spin rounded-full border-2 border-primary-600 border-t-transparent" />
                <p class="text-sm text-gray-600 dark:text-gray-400">Extracting line items and mapping to your catalog…</p>
            </section>

            <section v-else-if="step === 4" class="space-y-6">
                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Does this look correct?</h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Review each unit. Select asset and variant before confirming. Uncheck rows to skip.
                    </p>
                    <dl v-if="extraction" class="mt-4 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                        <div>
                            <dt class="text-gray-500">Document #</dt>
                            <dd class="font-medium">{{ extraction.invoice_number || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Date</dt>
                            <dd class="font-medium">{{ extraction.invoice_date || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Units</dt>
                            <dd class="font-medium">{{ includedRows.length }} / {{ rows.length }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Brand</dt>
                            <dd class="font-medium">{{ brand?.display_name || '—' }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="rounded-xl border-2 border-primary-300 bg-primary-50/90 p-6 shadow-sm dark:border-primary-700 dark:bg-primary-950/40">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Before you confirm</h2>
                        <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                            Set the bill vendor, unit status, and where units should be stocked before creating inventory.
                        </p>
                    </div>

                    <div class="mt-6 grid gap-6 lg:grid-cols-2">
                        <div class="rounded-lg border border-primary-200 bg-white/90 p-4 dark:border-primary-800 dark:bg-gray-900/50">
                            <label class="block text-sm font-semibold text-gray-900 dark:text-white">Bill vendor</label>
                            <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">
                                Used when creating a bill from this document.
                            </p>
                            <div class="mt-3">
                                <RecordSelect
                                    id="invoice-import-vendor"
                                    :field="vendorField"
                                    v-model="vendorId"
                                    field-key="vendor_id"
                                    :record="vendorRecordStub()"
                                    @record-selected="onVendorSelected"
                                />
                            </div>
                            <p v-if="!vendorId" class="mt-2 text-xs font-medium text-amber-700 dark:text-amber-300">
                                No vendor selected — bill creation will be disabled.
                            </p>
                        </div>

                        <div class="rounded-lg border border-primary-200 bg-white/90 p-4 dark:border-primary-800 dark:bg-gray-900/50">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Unit status</h3>
                                    <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">
                                        Status assigned to each imported unit. Defaults to Inbound.
                                    </p>
                                </div>
                                <div class="inline-flex shrink-0 rounded-lg border border-primary-200 bg-white p-0.5 text-xs dark:border-primary-700 dark:bg-gray-900">
                                    <button
                                        type="button"
                                        class="rounded-md px-3 py-1.5 font-medium transition"
                                        :class="statusMode === 'all'
                                            ? 'bg-primary-600 text-white'
                                            : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200'"
                                        @click="setStatusMode('all')"
                                    >
                                        Apply to all
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-md px-3 py-1.5 font-medium transition"
                                        :class="statusMode === 'individual'
                                            ? 'bg-primary-600 text-white'
                                            : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200'"
                                        @click="setStatusMode('individual')"
                                    >
                                        Per row
                                    </button>
                                </div>
                            </div>
                            <div v-if="statusMode === 'all'" class="mt-4">
                                <label :for="'invoice-import-bulk-status'" class="sr-only">Unit status</label>
                                <select
                                    id="invoice-import-bulk-status"
                                    v-model.number="bulkStatus"
                                    class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                >
                                    <option v-for="opt in unitStatusChoices" :key="opt.id" :value="opt.id">
                                        {{ opt.name }}
                                    </option>
                                </select>
                            </div>
                            <p v-else class="mt-4 rounded-md border border-dashed border-primary-200 bg-primary-50/60 px-3 py-2 text-xs text-gray-700 dark:border-primary-800 dark:bg-primary-950/30 dark:text-gray-300">
                                Unit status can be set for each row in the table below.
                            </p>
                        </div>
                    </div>

                    <div class="mt-6 rounded-lg border border-primary-200 bg-white/90 p-4 dark:border-primary-800 dark:bg-gray-900/50">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Subsidiary &amp; location</h3>
                                    <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">
                                        Optional. Where imported units are stocked in your dealership.
                                    </p>
                                </div>
                                <div class="inline-flex shrink-0 rounded-lg border border-primary-200 bg-white p-0.5 text-xs dark:border-primary-700 dark:bg-gray-900">
                                    <button
                                        type="button"
                                        class="rounded-md px-3 py-1.5 font-medium transition"
                                        :class="placementMode === 'all'
                                            ? 'bg-primary-600 text-white'
                                            : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200'"
                                        @click="setPlacementMode('all')"
                                    >
                                        Apply to all
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-md px-3 py-1.5 font-medium transition"
                                        :class="placementMode === 'individual'
                                            ? 'bg-primary-600 text-white'
                                            : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200'"
                                        @click="setPlacementMode('individual')"
                                    >
                                        Per row
                                    </button>
                                </div>
                            </div>

                            <div v-if="placementMode === 'all'" class="mt-4 grid gap-4 sm:grid-cols-2">
                                <div>
                                    <RecordSelect
                                        id="invoice-import-subsidiary"
                                        :field="subsidiaryField"
                                        v-model="bulkSubsidiaryId"
                                        field-key="subsidiary_id"
                                        :record="bulkPlacementRecordStub()"
                                        @record-selected="onBulkSubsidiarySelected"
                                    />
                                </div>
                                <div>
                                    <RecordSelect
                                        id="invoice-import-location"
                                        :field="locationField"
                                        v-model="bulkLocationId"
                                        field-key="location_id"
                                        :record="bulkPlacementRecordStub()"
                                        filter-by="subsidiary_id"
                                        :filter-value="bulkSubsidiaryId"
                                        :disabled="!bulkSubsidiaryId"
                                        @record-selected="onBulkLocationSelected"
                                    />
                                </div>
                            </div>
                            <p v-else class="mt-4 rounded-md border border-dashed border-primary-200 bg-primary-50/60 px-3 py-2 text-xs text-gray-700 dark:border-primary-800 dark:bg-primary-950/30 dark:text-gray-300">
                                Subsidiary and location columns are shown in the unit table below.
                            </p>
                    </div>
                </div>

                <div
                    v-if="excludedWithoutIdentifier > 0"
                    class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-300"
                >
                    {{ excludedWithoutIdentifier }} {{ excludedWithoutIdentifier === 1 ? 'row was' : 'rows were' }} hidden because they had no HIN or serial number from the document.
                </div>

                <div
                    v-if="existingRowsCount > 0"
                    class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-100"
                >
                    <p class="font-medium">
                        {{ existingRowsCount }} {{ existingRowsCount === 1 ? 'unit already exists' : 'units already exist' }} in inventory and will not be uploaded.
                    </p>
                    <p class="mt-1 text-amber-800 dark:text-amber-200/90">
                        Highlighted rows match an existing HIN or serial number. The <span class="font-medium">Catalog</span> column shows whether the line was mapped to your product catalog — that is separate from whether the unit is already in stock.
                    </p>
                </div>

                <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-900/40">
                            <tr>
                                <th class="px-3 py-2 text-left">Include</th>
                                <th class="px-3 py-2 text-left">Item</th>
                                <th class="px-3 py-2 text-left">HIN</th>
                                <th class="px-3 py-2 text-left">Serial</th>
                                <th class="px-3 py-2 text-right">Cost</th>
                                <th class="px-3 py-2 text-left">Asset</th>
                                <th class="px-3 py-2 text-left">Variant</th>
                                <th v-if="placementMode === 'individual'" class="px-3 py-2 text-left">Subsidiary</th>
                                <th v-if="placementMode === 'individual'" class="px-3 py-2 text-left">Location</th>
                                <th v-if="statusMode === 'individual'" class="px-3 py-2 text-left">Unit status</th>
                                <th class="px-3 py-2 text-left">Catalog</th>
                                <th class="px-3 py-2 text-left">Inventory</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in reviewRows"
                                :key="row.row_index"
                                class="border-t border-gray-100 dark:border-gray-700"
                                :class="row.already_exists
                                    ? 'bg-amber-50/90 dark:bg-amber-900/15'
                                    : ''"
                            >
                                <td class="px-3 py-2 align-top">
                                    <input
                                        v-model="row.include"
                                        type="checkbox"
                                        :disabled="row.already_exists"
                                        :title="row.already_exists ? 'This unit already exists in inventory' : undefined"
                                    />
                                </td>
                                <td class="px-3 py-2 align-top">
                                    <div class="font-medium">{{ row.item_code || '—' }}</div>
                                    <div class="text-xs text-gray-500">{{ row.description }}</div>
                                    <div v-if="row.extracted_model" class="text-xs text-gray-400">AI: {{ row.extracted_model }} {{ row.extracted_variant }}</div>
                                </td>
                                <td class="px-3 py-2 align-top">
                                    <input
                                        v-model="row.hin"
                                        type="text"
                                        class="w-36 rounded border-gray-300 text-xs dark:border-gray-600 dark:bg-gray-700"
                                        @blur="refreshRowExistingFlags(row)"
                                    />
                                </td>
                                <td class="px-3 py-2 align-top">
                                    <input
                                        v-model="row.serial_number"
                                        type="text"
                                        class="w-28 rounded border-gray-300 text-xs dark:border-gray-600 dark:bg-gray-700"
                                        @blur="refreshRowExistingFlags(row)"
                                    />
                                </td>
                                <td class="px-3 py-2 align-top text-right">
                                    <input v-model.number="row.unit_price" type="number" step="0.01" min="0" class="w-24 rounded border-gray-300 text-right text-xs dark:border-gray-600 dark:bg-gray-700" />
                                </td>
                                <td class="min-w-[12rem] px-3 py-2 align-top">
                                    <RecordSelect
                                        :id="`invoice-asset-${row.row_index}`"
                                        :field="assetField"
                                        v-model="row.asset_id"
                                        field-key="asset_id"
                                        :record="rowRecordStub(row)"
                                        filter-by="make_id"
                                        :filter-value="boatMakeId"
                                        :disabled="!boatMakeId"
                                        @record-selected="(rec) => onAssetSelected(row, rec)"
                                    />
                                </td>
                                <td class="min-w-[10rem] px-3 py-2 align-top">
                                    <RecordSelect
                                        v-if="row.asset_has_variants"
                                        :id="`invoice-variant-${row.row_index}`"
                                        :field="variantFieldForRow(row)"
                                        v-model="row.asset_variant_id"
                                        field-key="asset_variant_id"
                                        :record="rowRecordStub(row)"
                                        filter-by="asset_id"
                                        :filter-value="row.asset_id"
                                        :disabled="!row.asset_id"
                                        @record-selected="(rec) => onVariantSelected(row, rec)"
                                    />
                                    <span v-else class="text-xs text-gray-400">—</span>
                                </td>
                                <td v-if="placementMode === 'individual'" class="min-w-[10rem] px-3 py-2 align-top">
                                    <RecordSelect
                                        :id="`invoice-subsidiary-${row.row_index}`"
                                        :field="subsidiaryField"
                                        v-model="row.subsidiary_id"
                                        field-key="subsidiary_id"
                                        :record="rowPlacementRecordStub(row)"
                                        @record-selected="(rec) => onRowSubsidiarySelected(row, rec)"
                                    />
                                </td>
                                <td v-if="placementMode === 'individual'" class="min-w-[10rem] px-3 py-2 align-top">
                                    <RecordSelect
                                        :id="`invoice-location-${row.row_index}`"
                                        :field="locationField"
                                        v-model="row.location_id"
                                        field-key="location_id"
                                        :record="rowPlacementRecordStub(row)"
                                        filter-by="subsidiary_id"
                                        :filter-value="row.subsidiary_id"
                                        :disabled="!row.subsidiary_id"
                                        @record-selected="(rec) => onRowLocationSelected(row, rec)"
                                    />
                                </td>
                                <td v-if="statusMode === 'individual'" class="min-w-[8rem] px-3 py-2 align-top">
                                    <select
                                        v-model.number="row.status"
                                        class="w-full rounded border-gray-300 text-xs dark:border-gray-600 dark:bg-gray-700"
                                    >
                                        <option v-for="opt in unitStatusChoices" :key="opt.id" :value="opt.id">
                                            {{ opt.name }}
                                        </option>
                                    </select>
                                </td>
                                <td class="px-3 py-2 align-top">
                                    <span class="rounded px-2 py-0.5 text-xs font-medium" :class="matchStatusClass(row.match_status)">
                                        {{ matchStatusLabel(row.match_status) }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 align-top">
                                    <template v-if="row.already_exists">
                                        <span class="rounded bg-amber-200 px-2 py-0.5 text-xs font-semibold text-amber-950 dark:bg-amber-800/60 dark:text-amber-50">
                                            Already exists
                                        </span>
                                        <div class="mt-1 text-xs text-amber-900 dark:text-amber-100">
                                            {{ existingMatchLabel(row) }}
                                        </div>
                                        <Link
                                            v-if="row.existing_asset_unit_id"
                                            :href="route('assetunits.show', row.existing_asset_unit_id)"
                                            class="mt-1 inline-block text-xs font-medium text-primary-700 underline dark:text-primary-300"
                                            target="_blank"
                                        >
                                            {{ row.existing_asset_unit_label || 'View unit' }}
                                        </Link>
                                    </template>
                                    <span v-else class="text-xs text-gray-500 dark:text-gray-400">New</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-between gap-2">
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="rounded-lg border px-4 py-2 text-sm" @click="goBackToInstructions">
                            Go back to instructions
                        </button>
                        <button type="button" class="rounded-lg border px-4 py-2 text-sm" @click="resetImport">
                            Start over
                        </button>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                        :disabled="!canConfirmUnits"
                        @click="openConfirmModal"
                    >
                        Confirm and create {{ importableRowsCount }} {{ importableRowsCount === 1 ? 'unit' : 'units' }}
                    </button>
                </div>
            </section>

            <section v-else-if="step === 5" class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Import complete</h2>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Created {{ confirmResult?.import?.created ?? 0 }} units.
                    <span v-if="confirmResult?.import?.skipped"> Skipped {{ confirmResult.import.skipped }}.</span>
                </p>
                <ul v-if="confirmResult?.import?.errors?.length" class="mt-3 list-disc space-y-1 pl-5 text-sm text-red-600 dark:text-red-400">
                    <li v-for="(msg, i) in confirmResult.import.errors" :key="i">{{ msg }}</li>
                </ul>
                <p v-if="confirmResult?.bill?.success" class="mt-3 text-sm text-gray-700 dark:text-gray-300">
                    Bill created
                    <Link
                        v-if="confirmResult.bill.bill_id"
                        :href="route('bills.show', confirmResult.bill.bill_id)"
                        class="font-medium text-primary-600 underline dark:text-primary-400"
                    >
                        #{{ confirmResult.bill.bill_id }}
                    </Link>.
                    <span v-if="confirmResult.bill.quickbooks_sync?.success"> Synced to QuickBooks.</span>
                </p>
                <div class="mt-6 flex flex-wrap gap-3">
                    <Link :href="route('assetunits.index')" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white">
                        View units
                    </Link>
                    <button type="button" class="rounded-lg border px-4 py-2 text-sm" @click="resetImport">Import another</button>
                </div>
            </section>
        </div>

        <Modal :show="showBillModal" max-width="md" @close="showBillModal = false">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Create inventory units</h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Create {{ includedRows.length }} unit(s) from this document?
                </p>
                <div class="mt-5 space-y-3">
                    <label class="flex items-start gap-3 text-sm">
                        <input v-model="createBill" type="checkbox" class="mt-0.5" :disabled="!vendorId" />
                        <span>
                            Create Bill from this document
                            <span v-if="!vendorId" class="block text-xs text-amber-600">Select a vendor first.</span>
                        </span>
                    </label>
                    <label v-if="canShowQuickbooksSync && createBill" class="flex items-start gap-3 text-sm">
                        <input
                            v-model="syncQuickbooks"
                            type="checkbox"
                            class="mt-0.5"
                            :disabled="!vendorHasQuickbooks"
                        />
                        <span>
                            Sync Bill to QuickBooks
                            <span v-if="!vendorHasQuickbooks" class="block text-xs text-amber-600">Vendor is not linked to QuickBooks.</span>
                        </span>
                    </label>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" class="rounded-lg border px-4 py-2 text-sm" @click="showBillModal = false">Cancel</button>
                    <button
                        type="button"
                        class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                        :disabled="confirming"
                        @click="confirmImport"
                    >
                        {{ confirming ? 'Creating…' : 'Confirm' }}
                    </button>
                </div>
            </div>
        </Modal>
    </TenantLayout>
</template>
