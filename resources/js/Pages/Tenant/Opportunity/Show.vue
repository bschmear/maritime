<script setup>
import AssetLineVariantCell from '@/Components/Tenant/AssetLineVariantCell.vue';
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import { buildResourceRouteParams } from '@/utils/resourceRoutes.js';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, getCurrentInstance, nextTick, ref, watch } from 'vue';

const FEATURE_REQUEST_ADDON_PAGE_SIZE = 15;

function sortAddonsAlphabetically(addons) {
    return [...addons].sort((a, b) =>
        String(a?.name ?? '').localeCompare(String(b?.name ?? ''), undefined, { sensitivity: 'base' })
    );
}

const props = defineProps({
    record: { type: Object, required: true },
    recordType: { type: String, default: 'opportunities' },
    recordTitle: { type: String, default: 'Opportunity' },
    domainName: { type: String, default: 'Opportunity' },
    formSchema: { type: Object, default: null },
    fieldsSchema: { type: Object, default: () => ({}) },
    enumOptions: { type: Object, default: () => ({}) },
    account: { type: Object, default: null },
    timezones: { type: Array, default: () => [] },
    qualificationEnumOptions: { type: Object, default: () => ({}) },
    /** Tenant Add-On catalog (`addons.id`, `name`, `default_price`) for feature-request picker */
    catalogAddons: { type: Array, default: () => [] },
});

const opportunityLabel = computed(() =>
    props.record?.display_name ? `${props.record.display_name}` : `Opportunity #${props.record?.id}`
);

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Opportunities', href: route('opportunities.index') },
    { label: opportunityLabel.value, href: route('opportunities.show', buildResourceRouteParams('opportunities', props.record.id)) },
]);

const formatCurrency = (value) =>
    value != null && value !== '' ? `$${parseFloat(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}` : '—';

const formatDate = (value) => {
    if (!value) return '—';
    return new Date(value).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
};

const getEnumLabel = (options, value) => {
    if (!options?.length || value == null) return '—';
    const opt = options.find(o => o.id == value || o.value == value);
    return opt ? opt.name : String(value);
};

const stageLabel = computed(() =>
    getEnumLabel(props.enumOptions['App\\Enums\\Opportunity\\Stage'], props.record?.stage)
);
const statusLabel = computed(() =>
    getEnumLabel(props.enumOptions['App\\Enums\\Opportunity\\Status'], props.record?.status)
);

/** DB stores tinyint IDs; enum options use id + value — match either so Open/New enables sending. */
const canSendFeatureRequest = computed(() => {
    const statusOpts = props.enumOptions?.['App\\Enums\\Opportunity\\Status'];
    const stageOpts = props.enumOptions?.['App\\Enums\\Opportunity\\Stage'];
    const openOpt = Array.isArray(statusOpts) ? statusOpts.find((o) => o.value === 'open') : null;
    const newOpt = Array.isArray(stageOpts) ? stageOpts.find((o) => o.value === 'new') : null;
    const openId = openOpt != null ? Number(openOpt.id) : 1;
    const newId = newOpt != null ? Number(newOpt.id) : 1;
    const rs = props.record?.status;
    const rg = props.record?.stage;
    const statusOk =
        rs === 'open' || rs === openOpt?.value || Number(rs) === openId;
    const stageOk =
        rg === 'new' || rg === newOpt?.value || Number(rg) === newId;
    return Boolean(statusOk && stageOk);
});

const assets = computed(() => props.record?.assets ?? []);

const assetOptionPremiumSum = (item) =>
    (item.opportunity_selected_options || []).reduce((s, r) => s + Number(r.price || 0), 0);

const assetOptionCostSum = (item) =>
    (item.opportunity_selected_options || []).reduce((s, r) => s + Number(r.cost || 0), 0);

const assetAddonRevenue = (item) =>
    (item.opportunity_addons || []).reduce((s, a) => s + Number(a.price || 0) * Number(a.quantity || 1), 0);

const assetBaseRevenue = (item) => Number(item.pivot?.unit_price || 0) * Number(item.pivot?.quantity || 0);

/** Combined revenue for this asset line (base + selected options + add-ons). */
const assetTotal = (item) => assetBaseRevenue(item) + assetOptionPremiumSum(item) + assetAddonRevenue(item);

/** Pivot cost × qty plus option cost snapshots (when present). */
const assetCostItem = (item) =>
    Number(item.pivot?.estimated_cost || 0) * Number(item.pivot?.quantity || 0) + assetOptionCostSum(item);

const assetSubtotal = computed(() => assets.value.reduce((sum, item) => sum + assetTotal(item), 0));
const assetCostTotal = computed(() => assets.value.reduce((sum, item) => sum + assetCostItem(item), 0));

/** Pivot variant display (matches estimate line item pattern). */
const oppAssetVariantLabel = (item) => {
    const v = item.asset_variant ?? item.assetVariant;
    const name = v?.display_name?.trim() || v?.name?.trim();
    if (name) {
        return name;
    }
    const vid = item.pivot?.asset_variant_id;
    if (vid) {
        return `Variant #${vid}`;
    }
    return '';
};

const lineItems = computed(() => props.record?.inventory_items ?? props.record?.inventoryItems ?? []);

const invAddonRevenue = (item) =>
    (item.opportunity_addons || []).reduce((s, a) => s + Number(a.price || 0) * Number(a.quantity || 1), 0);

const lineTotal = (item) =>
    Number(item.pivot?.unit_price || 0) * Number(item.pivot?.quantity || 0) + invAddonRevenue(item);

const lineCostItem = (item) => Number(item.pivot?.estimated_cost || 0) * Number(item.pivot?.quantity || 0);
const lineItemsSubtotal = computed(() => lineItems.value.reduce((sum, item) => sum + lineTotal(item), 0));
const lineItemsCostTotal = computed(() => lineItems.value.reduce((sum, item) => sum + lineCostItem(item), 0));

const combinedSubtotal = computed(() => assetSubtotal.value + lineItemsSubtotal.value);
const combinedCostTotal = computed(() => assetCostTotal.value + lineItemsCostTotal.value);
const grossProfit = computed(() => combinedSubtotal.value - combinedCostTotal.value);

const qualification = computed(() => props.record?.qualification ?? null);

const qualBudgetLabel = computed(() =>
    getEnumLabel(props.qualificationEnumOptions?.budget_range, qualification.value?.budget_range)
);
const qualTimelineLabel = computed(() =>
    getEnumLabel(props.qualificationEnumOptions?.purchase_timeline, qualification.value?.purchase_timeline)
);

const handleDelete = () => {
    if (!confirm('Are you sure you want to delete this opportunity?')) return;
    router.delete(route('opportunities.destroy', buildResourceRouteParams('opportunities', props.record.id)));
};

const featureRequestModalOpen = ref(false);
const featureRequestAddonSearch = ref('');
/** 1-based page into the filtered, alphabetically sorted catalog list */
const featureRequestAddonPage = ref(1);
const featureRequestIncludeAddons = ref(false);
/** Selected `asset_opportunity.id` values (numbers). */
const featureRequestSelectedPivotIds = ref([]);
/** Map pivot id → selected catalog `addons.id` values */
const featureRequestCatalogSelections = ref({});

const featureRequestBatchForm = useForm({
    lines: [],
    customer_note: '',
});

const featureRequests = computed(() => props.record?.feature_requests ?? []);

function pivotId(item) {
    const raw = item?.pivot?.id;
    return raw != null ? Number(raw) : null;
}

/** Pivot ids from v-for / APIs may be string or number — normalize for lookups. */
function normalizePivotId(pid) {
    if (pid == null || pid === '') return null;
    const n = Number(pid);
    return Number.isFinite(n) ? n : null;
}

function addonsForPivot(pid) {
    const n = normalizePivotId(pid);
    if (n == null) return [];
    const item = assets.value.find((a) => pivotId(a) === n);
    return item?.opportunity_addons ?? [];
}

function assetLabelForPivot(pid) {
    const n = normalizePivotId(pid);
    if (n == null) return 'Asset';
    const item = assets.value.find((a) => pivotId(a) === n);
    return item?.display_name ?? `Asset #${pid}`;
}

const anyCatalogAddons = computed(() => (props.catalogAddons ?? []).length > 0);

const catalogAddonsList = computed(() => sortAddonsAlphabetically(props.catalogAddons ?? []));

function catalogAddonIdsPresetForPivot(pid) {
    const fromLine = new Set();
    for (const row of addonsForPivot(pid)) {
        if (row.addon_id != null) {
            fromLine.add(Number(row.addon_id));
        }
    }
    return [...fromLine];
}

const allAssetsFeatureRequestSelected = computed(() => {
    const ids = assets.value.map((a) => pivotId(a)).filter((id) => id != null);
    return (
        ids.length > 0 &&
        ids.every((id) => featureRequestSelectedPivotIds.value.includes(id))
    );
});

function toggleSelectAllFeatureRequestAssets(checked) {
    if (checked) {
        featureRequestSelectedPivotIds.value = assets.value.map((a) => pivotId(a)).filter((id) => id != null);
    } else {
        featureRequestSelectedPivotIds.value = [];
    }
}

function toggleFeatureRequestAssetSelection(item) {
    const id = pivotId(item);
    if (id == null) return;
    const arr = [...featureRequestSelectedPivotIds.value];
    const i = arr.indexOf(id);
    if (i >= 0) {
        arr.splice(i, 1);
    } else {
        arr.push(id);
    }
    featureRequestSelectedPivotIds.value = arr;
}

function isPivotSelectedForFeatureRequest(item) {
    const id = pivotId(item);
    if (id == null) return false;
    return featureRequestSelectedPivotIds.value.includes(id);
}

/** Lowercase haystack for catalog rows and pivot snapshot rows (name, ids, price). */
function addonSearchHaystack(addon) {
    const parts = [
        addon?.name,
        addon?.notes,
        addon?.addon_id != null ? String(addon.addon_id) : '',
        addon?.id != null ? String(addon.id) : '',
        addon?.default_price != null ? String(addon.default_price) : '',
    ];
    return parts
        .filter((p) => p != null && String(p).trim() !== '')
        .map((p) => String(p).toLowerCase())
        .join(' ');
}

/**
 * Match every whitespace-separated token (order-independent).
 * Fixes full-string `includes()` failing when query words differ from name order (e.g. "hitch trailer" vs "Trailer Hitch").
 */
function addonMatchesSearchQuery(addon, queryRaw) {
    const raw = (queryRaw ?? '').trim();
    if (!raw) return true;
    const hay = addonSearchHaystack(addon);
    if (!hay) return false;
    const tokens = raw
        .toLowerCase()
        .split(/\s+/)
        .map((t) => t.trim())
        .filter(Boolean);
    if (tokens.length === 0) return true;
    return tokens.every((t) => hay.includes(t));
}

const filteredCatalogAddons = computed(() => {
    const q = featureRequestAddonSearch.value ?? '';
    const list = catalogAddonsList.value;
    const filtered = !q.trim() ? list : list.filter((a) => addonMatchesSearchQuery(a, q));
    return sortAddonsAlphabetically(filtered);
});

const featureRequestAddonPageCount = computed(() => {
    const n = filteredCatalogAddons.value.length;
    if (n <= 0) return 1;
    return Math.ceil(n / FEATURE_REQUEST_ADDON_PAGE_SIZE);
});

const featureRequestAddonEffectivePage = computed(() =>
    Math.min(Math.max(1, featureRequestAddonPage.value), featureRequestAddonPageCount.value)
);

const paginatedCatalogAddons = computed(() => {
    const all = filteredCatalogAddons.value;
    const page = featureRequestAddonEffectivePage.value;
    const start = (page - 1) * FEATURE_REQUEST_ADDON_PAGE_SIZE;
    return all.slice(start, start + FEATURE_REQUEST_ADDON_PAGE_SIZE);
});

const featureRequestAddonRangeLabel = computed(() => {
    const total = filteredCatalogAddons.value.length;
    if (total === 0) return '';
    const page = featureRequestAddonEffectivePage.value;
    const start = (page - 1) * FEATURE_REQUEST_ADDON_PAGE_SIZE + 1;
    const end = Math.min(page * FEATURE_REQUEST_ADDON_PAGE_SIZE, total);
    return `${start}–${end} of ${total}`;
});

watch(featureRequestAddonSearch, () => {
    featureRequestAddonPage.value = 1;
});

watch(
    () => filteredCatalogAddons.value.length,
    () => {
        const max = featureRequestAddonPageCount.value;
        if (featureRequestAddonPage.value > max) {
            featureRequestAddonPage.value = max;
        }
    }
);

const openFeatureRequestModal = () => {
    if (!canSendFeatureRequest.value || featureRequestSelectedPivotIds.value.length === 0) return;
    featureRequestAddonSearch.value = '';
    featureRequestAddonPage.value = 1;
    featureRequestBatchForm.clearErrors();
    featureRequestBatchForm.customer_note = '';
    const next = {};
    for (const pid of featureRequestSelectedPivotIds.value) {
        next[pid] = catalogAddonIdsPresetForPivot(pid);
    }
    featureRequestCatalogSelections.value = next;
    featureRequestIncludeAddons.value = false;
    featureRequestModalOpen.value = true;
};

const closeFeatureRequestModal = () => {
    featureRequestModalOpen.value = false;
    featureRequestAddonSearch.value = '';
    featureRequestAddonPage.value = 1;
};

const onFeatureRequestIncludeAddonsChange = (checked) => {
    featureRequestIncludeAddons.value = checked;
    if (checked) {
        const next = { ...featureRequestCatalogSelections.value };
        for (const pid of featureRequestSelectedPivotIds.value) {
            next[pid] = catalogAddonIdsPresetForPivot(pid);
        }
        featureRequestCatalogSelections.value = next;
    }
};

const toggleFeatureRequestCatalogAddonId = (pid, catalogAddonId) => {
    const cid = Number(catalogAddonId);
    const ids = [...(featureRequestCatalogSelections.value[pid] ?? [])].map(Number);
    const i = ids.indexOf(cid);
    if (i >= 0) {
        ids.splice(i, 1);
    } else {
        ids.push(cid);
    }
    featureRequestCatalogSelections.value = {
        ...featureRequestCatalogSelections.value,
        [pid]: ids,
    };
};

const featureRequestCatalogAddonSelected = (pid, catalogAddonId) =>
    (featureRequestCatalogSelections.value[pid] ?? []).map(Number).includes(Number(catalogAddonId));

function buildFeatureRequestLines() {
    return featureRequestSelectedPivotIds.value.map((pid) => {
        const wantInclude = featureRequestIncludeAddons.value;
        return {
            asset_opportunity_id: pid,
            include_addons: wantInclude,
            catalog_addon_ids: wantInclude ? (featureRequestCatalogSelections.value[pid] ?? []) : [],
        };
    });
}

const featureRequestSubmitDisabled = computed(() => {
    if (featureRequestBatchForm.processing) return true;
    if (!featureRequestIncludeAddons.value) return false;
    for (const pid of featureRequestSelectedPivotIds.value) {
        const picked = featureRequestCatalogSelections.value[pid] ?? [];
        if (picked.length === 0) return true;
    }
    return false;
});

const submitFeatureRequestModal = () => {
    featureRequestBatchForm.lines = buildFeatureRequestLines();
    featureRequestBatchForm.post(route('opportunities.send-feature-requests', props.record.id), {
        preserveScroll: true,
        onFinish: async () => {
            await nextTick();
            if (!featureRequestBatchForm.hasErrors) {
                const n = featureRequestSelectedPivotIds.value.length;
                closeFeatureRequestModal();
                featureRequestSelectedPivotIds.value = [];
                const proxy = getCurrentInstance()?.proxy;
                if (typeof proxy?.$root?.createToast === 'function') {
                    proxy.$root.createToast(
                        'success',
                        n === 1
                            ? 'Feature request link sent to the customer.'
                            : `${n} feature request links sent to the customer.`
                    );
                }
            }
        },
    });
};

const catalogAddonNameById = computed(() => {
    const m = {};
    for (const a of props.catalogAddons ?? []) {
        if (a?.id != null) {
            m[a.id] = a.name;
        }
    }
    return m;
});

/** Rows for “Requested asset options” (server adds asset_option_selections_display; fallback uses raw ids). */
function featureRequestAssetOptionDisplayRows(fr) {
    const labeled = fr.asset_option_selections_display;
    if (Array.isArray(labeled) && labeled.length > 0) {
        return labeled;
    }
    const raw = fr.asset_option_selections || [];
    if (!Array.isArray(raw) || raw.length === 0) {
        return [];
    }
    return raw.map((r) => ({
        option_name: `Option #${r.option_id ?? '?'}`,
        value_label: `Value #${r.option_value_id ?? '?'}`,
        price: null,
    }));
}

function featureRequestAssetOptionSummary(fr) {
    const n = featureRequestAssetOptionDisplayRows(fr).length;
    if (n === 0) return '—';
    return n === 1 ? '1 selected' : `${n} selected`;
}

/** Customer-requested catalog add-ons for staff approve/deny (new submissions only). */
function featureRequestAddonReviewRows(fr) {
    const rows = fr.addon_selections || [];
    const decisions = fr.addon_staff_decisions || {};
    const out = [];
    for (const row of rows) {
        const cid = row.catalog_addon_id != null ? Number(row.catalog_addon_id) : null;
        if (cid == null || !Number.isFinite(cid)) continue;
        const decision = decisions[String(cid)] ?? null;
        out.push({
            catalog_addon_id: cid,
            quantity: row.quantity ?? 1,
            decision,
            name: catalogAddonNameById.value[cid] ?? `Add-on #${cid}`,
        });
    }
    return out;
}

function reviewFeatureRequestAddon(fr, catalogAddonId, decision) {
    router.post(
        route('opportunities.feature-request-review-addon', [props.record.id, fr.id]),
        { catalog_addon_id: catalogAddonId, decision },
        { preserveScroll: true }
    );
}

const formatDateTime = (value) => {
    if (!value) return '—';
    return new Date(value).toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
};
</script>

<template>
    <Head :title="`${opportunityLabel} - Opportunity`" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="flex flex-wrap items-center justify-between gap-3 mt-4">
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        {{ opportunityLabel }}
                    </h2>
                    <div class="flex items-center gap-2">
                        <Link
                            :href="route('opportunities.edit', buildResourceRouteParams('opportunities', record.id))"
                            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            Edit
                        </Link>
                        <button
                            @click="handleDelete"
                            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-red-600 dark:text-red-400 bg-white dark:bg-gray-800 border border-red-200 dark:border-red-800 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <div class="w-full flex flex-col space-y-6">
            <div class="grid gap-6 lg:grid-cols-12">

                <!-- ============================
                     Main Column
                     ============================ -->
                <div class="lg:col-span-8 space-y-6">

                    <!-- Header Card -->
                    <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden">
                        <div class="bg-gradient-to-r from-primary-600 to-primary-700 dark:from-primary-700 dark:to-primary-800 px-6 py-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h1 class="text-2xl font-bold text-white">OPPORTUNITY</h1>
                                    <p class="text-primary-100 text-base mt-1">Sales opportunity details</p>
                                </div>
                                <div class="text-right">
                                    <div class="text-primary-200 text-base font-medium">Reference</div>
                                    <div class="text-white text-lg font-mono">{{ opportunityLabel }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="p-6 space-y-6">

                            <!-- Customer + Qualification + Salesperson / Deal Details -->
                            <div class="space-y-6">

                                <!-- Customer & Lead Section - Full Width -->
                                <div class="space-y-4">
                                    <h3 class="text-base font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700">
                                        Customer & Lead
                                    </h3>

                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <!-- Customer -->
                                        <div>
                                            <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">
                                                {{ fieldsSchema.customer_id?.label || 'Customer' }}
                                            </div>
                                            <Link
                                                v-if="record.customer"
                                                :href="route('customers.show', record.customer_id)"
                                                class="text-sm font-medium text-primary-600 dark:text-primary-400 hover:underline"
                                            >
                                                {{ record.customer.display_name }}
                                            </Link>
                                            <div v-else class="text-sm text-gray-400 dark:text-gray-500">—</div>
                                        </div>

                                        <!-- Qualification -->
                                        <div v-if="record.qualification_id">
                                            <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">
                                                {{ fieldsSchema.qualification_id?.label || 'Qualification' }}
                                            </div>
                                            <Link
                                                v-if="record.qualification"
                                                :href="route('qualifications.show', record.qualification_id)"
                                                class="text-sm font-medium text-primary-600 dark:text-primary-400 hover:underline"
                                            >
                                                {{ record.qualification.display_name }}
                                            </Link>
                                            <div v-else class="text-sm text-gray-400 dark:text-gray-500">—</div>
                                        </div>

                                        <!-- Salesperson -->
                                        <div>
                                            <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">
                                                {{ fieldsSchema.user_id?.label || 'Salesperson' }}
                                            </div>
                                            <div class="text-sm text-gray-900 dark:text-gray-100">
                                                {{ record.salesperson?.display_name ?? '—' }}
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Deal Details Section - Full Width -->
                                <div class="space-y-4">
                                    <h3 class="text-base font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700">
                                        Deal Details
                                    </h3>

                                    <!-- Stage, Status, Expected Close Date - One Line -->
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <!-- Stage -->
                                        <div>
                                            <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">
                                                {{ fieldsSchema.stage?.label || 'Stage' }}
                                            </div>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">
                                                {{ stageLabel }}
                                            </span>
                                        </div>

                                        <!-- Status -->
                                        <div>
                                            <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">
                                                {{ fieldsSchema.status?.label || 'Status' }}
                                            </div>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300">
                                                {{ statusLabel }}
                                            </span>
                                        </div>

                                        <!-- Expected Close Date -->
                                        <div v-if="fieldsSchema.expected_close_date">
                                            <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">
                                                {{ fieldsSchema.expected_close_date?.label || 'Expected Close Date' }}
                                            </div>
                                            <div class="text-sm text-gray-900 dark:text-gray-100">
                                                {{ formatDate(record.expected_close_date) }}
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Probability - Full Width -->
                                    <div v-if="fieldsSchema.probability">
                                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">
                                            {{ fieldsSchema.probability?.label || 'Probability' }}
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <div class="flex-1 bg-gray-200 dark:bg-gray-700 rounded-full h-1.5">
                                                <div
                                                    class="bg-primary-600 h-1.5 rounded-full transition-all"
                                                    :style="{ width: `${record.probability ?? 0}%` }"
                                                ></div>
                                            </div>
                                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300 w-10 text-right">
                                                {{ record.probability ?? 0 }}%
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Product Requirements -->
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-5">
                                <h3 class="text-base font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-4">
                                    Product Requirements
                                </h3>
                                <div class="flex flex-wrap gap-3">
                                    <span
                                        :class="record.needs_engine
                                            ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300'
                                            : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium"
                                    >
                                        <svg v-if="record.needs_engine" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        <svg v-else class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        {{ fieldsSchema.needs_engine?.label || 'Needs Engine' }}
                                    </span>
                                    <span
                                        :class="record.needs_trailer
                                            ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300'
                                            : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium"
                                    >
                                        <svg v-if="record.needs_trailer" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        <svg v-else class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        {{ fieldsSchema.needs_trailer?.label || 'Needs Trailer' }}
                                    </span>
                                    <span
                                        :class="record.requires_delivery
                                            ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300'
                                            : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium"
                                    >
                                        <svg v-if="record.requires_delivery" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        <svg v-else class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        {{ fieldsSchema.requires_delivery?.label || 'Requires Delivery' }}
                                    </span>
                                </div>
                            </div>

                            <!-- Delivery -->
                            <div
                                v-if="record.requires_delivery && (record.delivery_location || record.delivery_state || record.delivery_country)"
                                class="border-t border-gray-200 dark:border-gray-700 pt-5"
                            >
                                <h3 class="text-base font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-4">
                                    Delivery
                                </h3>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div v-if="record.delivery_location">
                                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">
                                            {{ fieldsSchema.delivery_location?.label || 'Delivery Location' }}
                                        </div>
                                        <div class="text-sm text-gray-900 dark:text-gray-100">{{ record.delivery_location }}</div>
                                    </div>
                                    <div v-if="record.delivery_state">
                                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">
                                            {{ fieldsSchema.delivery_state?.label || 'Delivery State' }}
                                        </div>
                                        <div class="text-sm text-gray-900 dark:text-gray-100">{{ record.delivery_state }}</div>
                                    </div>
                                    <div v-if="record.delivery_country">
                                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">
                                            {{ fieldsSchema.delivery_country?.label || 'Delivery Country' }}
                                        </div>
                                        <div class="text-sm text-gray-900 dark:text-gray-100">{{ record.delivery_country }}</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Notes -->
                            <div
                                v-if="record.customer_notes || record.internal_notes"
                                class="border-t border-gray-200 dark:border-gray-700 pt-5 grid grid-cols-1 md:grid-cols-2 gap-4"
                            >
                                <div v-if="record.customer_notes">
                                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
                                        {{ fieldsSchema.customer_notes?.label || 'Customer Notes' }}
                                    </div>
                                    <div class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line">
                                        {{ record.customer_notes }}
                                    </div>
                                </div>
                                <div v-if="record.internal_notes">
                                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
                                        {{ fieldsSchema.internal_notes?.label || 'Internal Notes' }}
                                    </div>
                                    <div class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line">
                                        {{ record.internal_notes }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ============================
                         Assets
                         ============================ -->
<!-- ============================
                         Assets
                         ============================ -->
                         <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden">
                        <div
                            class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex flex-wrap items-center justify-between gap-3"
                        >
                            <h2 class="text-base font-semibold text-gray-900 dark:text-white">Assets</h2>
                            <button
                                v-if="assets.length > 0"
                                type="button"
                                class="inline-flex items-center justify-center rounded-lg border border-primary-200 dark:border-primary-800 bg-primary-50 dark:bg-primary-900/25 px-3 py-2 text-sm font-medium text-primary-700 dark:text-primary-300 hover:bg-primary-100 dark:hover:bg-primary-900/40 transition-colors disabled:opacity-50 shrink-0"
                                :disabled="
                                    !canSendFeatureRequest ||
                                        featureRequestSelectedPivotIds.length === 0 ||
                                        featureRequestBatchForm.processing
                                "
                                @click="openFeatureRequestModal"
                            >
                                Send feature request form(s)
                            </button>
                        </div>

                        <div
                            v-if="assets.length > 0 && !canSendFeatureRequest"
                            class="px-6 py-3 bg-amber-50 dark:bg-amber-950/30 border-b border-amber-100 dark:border-amber-900/40 text-sm sm:text-base text-amber-900 dark:text-amber-100"
                        >
                            Feature request forms can only be sent when <strong>status</strong> is Open and <strong>stage</strong> is New.
                        </div>

                        <!-- Assets -->
                        <!-- Mobile: Card Layout -->
                        <div v-if="assets.length > 0" class="block md:hidden">
                            <!-- Select All -->
                            <div v-if="canSendFeatureRequest" class="px-4 py-3 bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700">
                                <label class="flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        class="rounded border-gray-300 dark:border-gray-600 text-primary-600 focus:ring-primary-500"
                                        :checked="allAssetsFeatureRequestSelected"
                                        :disabled="
                                            featureRequestBatchForm.processing ||
                                            !canSendFeatureRequest ||
                                            assets.length === 0
                                        "
                                        @change="toggleSelectAllFeatureRequestAssets($event.target.checked)"
                                    />
                                    <span class="font-medium text-gray-700 dark:text-gray-300">Select all assets</span>
                                </label>
                            </div>

                            <!-- Asset Cards -->
                            <div class="divide-y divide-gray-200 dark:divide-gray-700">
                                <div
                                    v-for="item in assets"
                                    :key="`${item.id}-${item.pivot?.asset_variant_id ?? 'n'}-${item.pivot?.id ?? ''}`"
                                    class="p-4"
                                >
                                    <!-- Main Asset Card -->
                                    <div class="space-y-3">
                                        <!-- Header with checkbox -->
                                        <div class="flex items-start gap-3">
                                            <input
                                                v-if="canSendFeatureRequest"
                                                type="checkbox"
                                                class="mt-1 rounded border-gray-300 dark:border-gray-600 text-primary-600 focus:ring-primary-500"
                                                :checked="isPivotSelectedForFeatureRequest(item)"
                                                :disabled="
                                                    featureRequestBatchForm.processing ||
                                                    !canSendFeatureRequest
                                                "
                                                @change="toggleFeatureRequestAssetSelection(item)"
                                            />
                                            <div class="flex-1 min-w-0">
                                                <div class="font-medium text-gray-900 dark:text-white">{{ item.display_name }}</div>
                                                <div v-if="item.make?.display_name" class="text-sm text-gray-500 dark:text-gray-400">{{ item.make.display_name }}</div>
                                            </div>
                                            <div class="text-right">
                                                <div class="font-semibold text-gray-900 dark:text-white">{{ formatCurrency(assetTotal(item)) }}</div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">Total</div>
                                            </div>
                                        </div>

                                        <!-- Details Grid -->
                                        <div class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                                            <div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Variant</div>
                                                <div class="text-gray-900 dark:text-white">
                                                    <AssetLineVariantCell
                                                        :label="oppAssetVariantLabel(item)"
                                                        :has-variants="item.has_variants"
                                                        pending-label="—"
                                                    />
                                                </div>
                                            </div>
                                            <div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Year</div>
                                                <div class="text-gray-900 dark:text-white">{{ item.year || '—' }}</div>
                                            </div>
                                            <div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Quantity</div>
                                                <div class="text-gray-900 dark:text-white">{{ item.pivot?.quantity ?? 1 }}</div>
                                            </div>
                                            <div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Unit Price</div>
                                                <div class="text-gray-900 dark:text-white">{{ formatCurrency(item.pivot?.unit_price) }}</div>
                                            </div>
                                            <div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Est. Cost</div>
                                                <div class="text-gray-900 dark:text-white">{{ formatCurrency(item.pivot?.estimated_cost) }}</div>
                                            </div>
                                        </div>

                                        <!-- Notes -->
                                        <div v-if="item.pivot?.notes" class="pt-2 border-t border-gray-100 dark:border-gray-700">
                                            <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Notes</div>
                                            <div class="text-sm text-gray-700 dark:text-gray-300">{{ item.pivot.notes }}</div>
                                        </div>

                                        <!-- Options -->
                                        <div
                                            v-if="item.opportunity_selected_options && item.opportunity_selected_options.length > 0"
                                            class="pl-4 space-y-2 border-l-2 border-slate-200 dark:border-slate-700"
                                        >
                                            <div
                                                v-for="opt in item.opportunity_selected_options"
                                                :key="`opt-${opt.id}`"
                                                class="text-sm"
                                            >
                                                <div class="flex items-center justify-between">
                                                    <div class="text-gray-600 dark:text-gray-400">
                                                        ↳ {{ opt.option_name }} · {{ opt.value_label }}
                                                    </div>
                                                    <div class="font-medium text-gray-900 dark:text-white">
                                                        {{ formatCurrency(opt.price) }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Addons -->
                                        <div
                                            v-if="item.opportunity_addons && item.opportunity_addons.length > 0"
                                            class="pl-4 space-y-2 border-l-2 border-primary-200 dark:border-primary-700"
                                        >
                                            <div
                                                v-for="(addon, aix) in item.opportunity_addons"
                                                :key="`addon-${item.id}-${aix}`"
                                                class="text-sm"
                                            >
                                                <div class="flex items-center justify-between">
                                                    <div class="text-gray-600 dark:text-gray-400 italic">
                                                        ↳ {{ addon.name }} (× {{ addon.quantity }})
                                                    </div>
                                                    <div class="font-medium text-gray-900 dark:text-white">
                                                        {{ formatCurrency(Number(addon.price || 0) * Number(addon.quantity || 1)) }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Mobile Totals -->
                            <div class="border-t-2 border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/50 p-4 space-y-2">
                                <div class="flex justify-between text-sm">
                                    <span class="font-semibold text-gray-700 dark:text-gray-300">Assets Subtotal (Revenue)</span>
                                    <span class="font-bold text-gray-900 dark:text-white">{{ formatCurrency(assetSubtotal) }}</span>
                                </div>
                                <div class="flex justify-between text-sm border-t border-gray-200 dark:border-gray-600 pt-2">
                                    <span class="font-semibold text-gray-500 dark:text-gray-400">Assets Total Cost</span>
                                    <span class="text-red-600 dark:text-red-400">{{ formatCurrency(assetCostTotal) }}</span>
                                </div>
                                <div class="flex justify-between text-sm border-t border-dashed border-gray-200 dark:border-gray-600 pt-2">
                                    <span class="font-semibold text-gray-700 dark:text-gray-300">Assets Gross Profit</span>
                                    <span class="font-bold"
                                        :class="(assetSubtotal - assetCostTotal) >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
                                        {{ formatCurrency(assetSubtotal - assetCostTotal) }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Desktop: Table Layout -->
                        <div v-if="assets.length > 0" class="hidden md:block overflow-x-auto">
                            <table class="w-full text-base">
                                <thead class="bg-gray-50 dark:bg-gray-700/50">
                                    <tr>
                                        <th class="w-12 px-2 py-3 text-center align-middle">
                                            <input
                                                type="checkbox"
                                                class="rounded border-gray-300 dark:border-gray-600 text-primary-600 focus:ring-primary-500"
                                                title="Select all asset lines"
                                                :checked="allAssetsFeatureRequestSelected"
                                                :disabled="
                                                    featureRequestBatchForm.processing ||
                                                    !canSendFeatureRequest ||
                                                    assets.length === 0
                                                "
                                                @change="toggleSelectAllFeatureRequestAssets($event.target.checked)"
                                            />
                                        </th>
                                        <th class="px-4 py-3 text-left text-base font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Asset</th>
                                        <th class="px-4 py-3 text-left text-base font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide min-w-[7rem]">Variant</th>
                                        <th class="px-4 py-3 text-left text-base font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide w-20">Year</th>
                                        <th class="px-4 py-3 text-right text-base font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide w-28">Estimated Cost</th>
                                        <th class="px-4 py-3 text-right text-base font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide w-28">Unit Price</th>
                                        <th class="px-4 py-3 text-right text-base font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide w-20">Qty</th>
                                        <th class="px-4 py-3 text-right text-base font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide w-28">Total</th>
                                        <th class="px-4 py-3 text-left text-base font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Notes</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    <template v-for="item in assets" :key="`${item.id}-${item.pivot?.asset_variant_id ?? 'n'}-${item.pivot?.id ?? ''}`">
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                                            <td class="w-12 px-2 py-3 align-top text-center">
                                                <input
                                                    type="checkbox"
                                                    class="rounded border-gray-300 dark:border-gray-600 text-primary-600 focus:ring-primary-500"
                                                    :checked="isPivotSelectedForFeatureRequest(item)"
                                                    :disabled="
                                                        featureRequestBatchForm.processing ||
                                                        !canSendFeatureRequest
                                                    "
                                                    @change="toggleFeatureRequestAssetSelection(item)"
                                                />
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="font-medium text-gray-900 dark:text-white">{{ item.display_name }}</div>
                                                <div v-if="item.make?.display_name" class="text-sm text-gray-400 dark:text-gray-500">{{ item.make.display_name }}</div>
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                                <AssetLineVariantCell
                                                    :label="oppAssetVariantLabel(item)"
                                                    :has-variants="item.has_variants"
                                                    pending-label="—"
                                                />
                                            </td>
                                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ item.year || '—' }}</td>
                                            <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">{{ formatCurrency(item.pivot?.estimated_cost) }}</td>
                                            <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">{{ formatCurrency(item.pivot?.unit_price) }}</td>
                                            <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">{{ item.pivot?.quantity ?? 1 }}</td>
                                            <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">{{ formatCurrency(assetTotal(item)) }}</td>
                                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400 text-sm truncate max-w-[160px]">{{ item.pivot?.notes || '—' }}</td>
                                        </tr>
                                        <tr
                                            v-for="opt in (item.opportunity_selected_options || [])"
                                            :key="`opt-${opt.id}`"
                                            class="bg-slate-50/70 dark:bg-slate-900/25"
                                        >
                                            <td class="w-12 px-2 py-2"></td>
                                            <td colspan="3" class="px-4 py-2 pl-8 text-sm text-gray-600 dark:text-gray-400">
                                                ↳ {{ opt.option_name }} · {{ opt.value_label }}
                                            </td>
                                            <td class="px-4 py-2 text-right text-sm text-gray-600 dark:text-gray-400">{{ formatCurrency(opt.cost) }}</td>
                                            <td class="px-4 py-2 text-right text-sm text-gray-400">—</td>
                                            <td class="px-4 py-2 text-right text-sm text-gray-400">—</td>
                                            <td class="px-4 py-2 text-right text-sm font-medium text-gray-700 dark:text-gray-300">{{ formatCurrency(opt.price) }}</td>
                                            <td class="px-4 py-2"></td>
                                        </tr>
                                        <tr
                                            v-for="(addon, aix) in (item.opportunity_addons || [])"
                                            :key="`addon-${item.id}-${aix}`"
                                            class="bg-primary-50/35 dark:bg-primary-900/15"
                                        >
                                            <td class="w-12 px-2 py-2"></td>
                                            <td colspan="3" class="px-4 py-2 pl-8 text-sm text-gray-600 dark:text-gray-400 italic">
                                                ↳ {{ addon.name }}
                                            </td>
                                            <td class="px-4 py-2"></td>
                                            <td class="px-4 py-2 text-right text-sm text-gray-600 dark:text-gray-400">{{ formatCurrency(addon.price) }}</td>
                                            <td class="px-4 py-2 text-right text-sm text-gray-600 dark:text-gray-400">{{ addon.quantity }}</td>
                                            <td class="px-4 py-2 text-right text-sm font-medium text-gray-700 dark:text-gray-300">
                                                {{ formatCurrency(Number(addon.price || 0) * Number(addon.quantity || 1)) }}
                                            </td>
                                            <td class="px-4 py-2"></td>
                                        </tr>
                                    </template>
                                </tbody>
                                <tfoot class="bg-gray-50 dark:bg-gray-700/50 border-t-2 border-gray-200 dark:border-gray-600">
                                    <tr>
                                        <td colspan="7" class="px-4 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">Assets Subtotal (Revenue)</td>
                                        <td class="px-4 py-3 text-right text-sm font-bold text-gray-900 dark:text-white">{{ formatCurrency(assetSubtotal) }}</td>
                                        <td></td>
                                    </tr>
                                    <tr class="border-t border-gray-200 dark:border-gray-600">
                                        <td colspan="7" class="px-4 py-3 text-right text-sm font-semibold text-gray-500 dark:text-gray-400">Assets Total Cost</td>
                                        <td class="px-4 py-3 text-right text-sm text-red-600 dark:text-red-400">{{ formatCurrency(assetCostTotal) }}</td>
                                        <td></td>
                                    </tr>
                                    <tr class="border-t border-dashed border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800">
                                        <td colspan="7" class="px-4 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">Assets Gross Profit</td>
                                        <td class="px-4 py-3 text-right text-sm font-bold"
                                            :class="(assetSubtotal - assetCostTotal) >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
                                            {{ formatCurrency(assetSubtotal - assetCostTotal) }}
                                        </td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <div v-else class="flex flex-col items-center justify-center py-12 text-center px-6">
                            <svg class="w-10 h-10 text-gray-300 dark:text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                            <p class="text-base text-gray-400 dark:text-gray-500">No assets attached</p>
                        </div>
                    </div>
                    <!-- ============================
                         Feature requests
                         ============================ -->
                    <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h2 class="text-base font-semibold text-gray-900 dark:text-white">Feature requests</h2>
                            <p class="text-base text-gray-500 dark:text-gray-400 mt-1">
                                Customer submissions from the Feature Request Form (asset options and optional add-ons).
                            </p>
                        </div>
                        <div v-if="featureRequests.length === 0" class="py-10 px-6 text-center text-base text-gray-500 dark:text-gray-400">
                            No submissions yet. Select one or more assets above, then use “Send feature request form(s)” to email the customer a secure link.
                        </div>
                        <!-- Mobile: stacked cards (avoids wide table overflow) -->
                        <div v-else class="block md:hidden divide-y divide-gray-200 dark:divide-gray-700">
                            <div
                                v-for="fr in featureRequests"
                                :key="`fr-m-${fr.id}`"
                                class="p-4 space-y-4"
                            >
                                <div class="text-sm text-gray-500 dark:text-gray-400">{{ formatDateTime(fr.submitted_at) }}</div>
                                <div class="font-semibold text-base text-gray-900 dark:text-white">{{ fr.asset_display_name || '—' }}</div>
                                <div class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                                    <div>
                                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Variant</div>
                                        <div class="text-base text-gray-900 dark:text-white mt-0.5">{{ fr.variant_label || '—' }}</div>
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Add-ons</div>
                                        <div class="mt-0.5">
                                            <span
                                                :class="fr.include_addons ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-600/40'"
                                                class="inline-flex px-2 py-0.5 rounded-full text-sm font-medium"
                                            >
                                                {{ fr.include_addons ? 'Yes' : 'No' }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-span-2">
                                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Asset options</div>
                                        <div class="text-base text-gray-900 dark:text-white mt-0.5 tabular-nums">
                                            {{ featureRequestAssetOptionSummary(fr) }}
                                        </div>
                                    </div>
                                    <div class="col-span-2">
                                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Signer</div>
                                        <div class="text-base text-gray-900 dark:text-white mt-0.5">{{ fr.signer_name }}</div>
                                    </div>
                                </div>
                                <div
                                    v-if="featureRequestAssetOptionDisplayRows(fr).length > 0"
                                    class="rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50/90 dark:bg-slate-900/25 px-3 py-3"
                                >
                                    <div class="text-sm font-semibold text-slate-800 dark:text-slate-200 uppercase tracking-wide mb-2">
                                        Requested asset options
                                    </div>
                                    <ul class="space-y-2">
                                        <li
                                            v-for="(row, idx) in featureRequestAssetOptionDisplayRows(fr)"
                                            :key="`${fr.id}-m-opt-${idx}`"
                                            class="flex flex-wrap items-baseline justify-between gap-2 text-base text-gray-800 dark:text-gray-200"
                                        >
                                            <span>
                                                <span class="font-medium text-gray-900 dark:text-white">{{ row.option_name }}</span>
                                                <span class="text-gray-500 dark:text-gray-400"> → </span>
                                                <span>{{ row.value_label }}</span>
                                            </span>
                                            <span v-if="row.price != null" class="tabular-nums text-sm text-gray-500 dark:text-gray-400 shrink-0">
                                                {{ formatCurrency(row.price) }}
                                            </span>
                                        </li>
                                    </ul>
                                </div>
                                <div
                                    v-if="featureRequestAddonReviewRows(fr).length > 0"
                                    class="rounded-lg border border-amber-200 dark:border-amber-900/50 bg-amber-50/90 dark:bg-amber-900/20 px-3 py-3"
                                >
                                    <div class="text-sm font-semibold text-amber-900 dark:text-amber-200 uppercase tracking-wide mb-2">
                                        Requested by customer — add-ons
                                    </div>
                                    <ul class="space-y-3">
                                        <li
                                            v-for="row in featureRequestAddonReviewRows(fr)"
                                            :key="`${fr.id}-m-addon-${row.catalog_addon_id}`"
                                            class="flex flex-col gap-2 text-base text-gray-800 dark:text-gray-200 sm:flex-row sm:flex-wrap sm:items-center"
                                        >
                                            <div class="flex flex-wrap items-baseline gap-2 min-w-0">
                                                <span class="font-medium">{{ row.name }}</span>
                                                <span class="text-gray-500 dark:text-gray-400 tabular-nums text-sm">× {{ row.quantity }}</span>
                                            </div>
                                            <div class="flex flex-wrap items-center gap-2 sm:ml-auto">
                                                <span
                                                    v-if="row.decision === 'approved'"
                                                    class="text-sm font-medium text-green-700 dark:text-green-400"
                                                >
                                                    Approved
                                                </span>
                                                <span
                                                    v-else-if="row.decision === 'denied'"
                                                    class="text-sm font-medium text-red-600 dark:text-red-400"
                                                >
                                                    Declined
                                                </span>
                                                <template v-else>
                                                    <button
                                                        type="button"
                                                        class="rounded-md bg-green-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-green-700"
                                                        @click="reviewFeatureRequestAddon(fr, row.catalog_addon_id, 'approved')"
                                                    >
                                                        Approve
                                                    </button>
                                                    <button
                                                        type="button"
                                                        class="rounded-md border border-gray-300 dark:border-gray-600 px-3 py-1.5 text-sm font-semibold text-gray-700 dark:text-gray-200 hover:bg-white dark:hover:bg-gray-800"
                                                        @click="reviewFeatureRequestAddon(fr, row.catalog_addon_id, 'denied')"
                                                    >
                                                        Decline
                                                    </button>
                                                </template>
                                            </div>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <!-- Desktop: wide table -->
                        <div v-if="featureRequests.length > 0" class="hidden md:block overflow-x-auto">
                            <table class="w-full text-base">
                                <thead class="bg-gray-50 dark:bg-gray-700/50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-base font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Submitted</th>
                                        <th class="px-4 py-3 text-left text-base font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Asset</th>
                                        <th class="px-4 py-3 text-left text-base font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Variant</th>
                                        <th class="px-4 py-3 text-center text-base font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Add-ons</th>
                                        <th class="px-4 py-3 text-right text-base font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Asset options</th>
                                        <th class="px-4 py-3 text-left text-base font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Signer</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    <template v-for="fr in featureRequests" :key="fr.id">
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ formatDateTime(fr.submitted_at) }}</td>
                                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ fr.asset_display_name || '—' }}</td>
                                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ fr.variant_label || '—' }}</td>
                                            <td class="px-4 py-3 text-center">
                                                <span
                                                    :class="fr.include_addons ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-600/40'"
                                                    class="inline-flex px-2 py-0.5 rounded-full text-sm font-medium"
                                                >
                                                    {{ fr.include_addons ? 'Yes' : 'No' }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-right tabular-nums text-gray-700 dark:text-gray-300">
                                                {{ featureRequestAssetOptionSummary(fr) }}
                                            </td>
                                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ fr.signer_name }}</td>
                                        </tr>
                                        <tr
                                            v-if="featureRequestAssetOptionDisplayRows(fr).length > 0"
                                            class="bg-slate-50/90 dark:bg-slate-900/25 border-t border-slate-100 dark:border-slate-800/80"
                                        >
                                            <td colspan="6" class="px-4 py-3">
                                                <div class="text-sm font-semibold text-slate-800 dark:text-slate-200 uppercase tracking-wide mb-2">
                                                    Requested asset options
                                                </div>
                                                <ul class="space-y-1.5">
                                                    <li
                                                        v-for="(row, idx) in featureRequestAssetOptionDisplayRows(fr)"
                                                        :key="`${fr.id}-opt-${idx}`"
                                                        class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5 text-sm text-gray-800 dark:text-gray-200"
                                                    >
                                                        <span class="font-medium text-gray-900 dark:text-white">{{ row.option_name }}</span>
                                                        <span class="text-gray-500 dark:text-gray-400">→</span>
                                                        <span>{{ row.value_label }}</span>
                                                        <span
                                                            v-if="row.price != null"
                                                            class="text-gray-500 dark:text-gray-400 tabular-nums text-sm ml-auto sm:ml-0"
                                                        >
                                                            {{ formatCurrency(row.price) }}
                                                        </span>
                                                    </li>
                                                </ul>
                                            </td>
                                        </tr>
                                        <tr
                                            v-if="featureRequestAddonReviewRows(fr).length > 0"
                                            class="bg-amber-50/90 dark:bg-amber-900/20 border-t border-amber-100 dark:border-amber-900/40"
                                        >
                                            <td colspan="6" class="px-4 py-3">
                                                <div class="text-sm font-semibold text-amber-900 dark:text-amber-200 uppercase tracking-wide mb-2">
                                                    Requested by customer — add-ons
                                                </div>
                                                <ul class="space-y-2">
                                                    <li
                                                        v-for="row in featureRequestAddonReviewRows(fr)"
                                                        :key="`${fr.id}-addon-${row.catalog_addon_id}`"
                                                        class="flex flex-wrap items-center gap-2 text-sm text-gray-800 dark:text-gray-200"
                                                    >
                                                        <span class="font-medium">{{ row.name }}</span>
                                                        <span class="text-gray-500 dark:text-gray-400 tabular-nums">× {{ row.quantity }}</span>
                                                        <span
                                                            v-if="row.decision === 'approved'"
                                                            class="ml-auto text-sm font-medium text-green-700 dark:text-green-400"
                                                        >
                                                            Approved
                                                        </span>
                                                        <span
                                                            v-else-if="row.decision === 'denied'"
                                                            class="ml-auto text-sm font-medium text-red-600 dark:text-red-400"
                                                        >
                                                            Declined
                                                        </span>
                                                        <span v-else class="ml-auto inline-flex items-center gap-2">
                                                            <button
                                                                type="button"
                                                                class="rounded-md bg-green-600 px-2.5 py-1 text-sm font-semibold text-white hover:bg-green-700"
                                                                @click="reviewFeatureRequestAddon(fr, row.catalog_addon_id, 'approved')"
                                                            >
                                                                Approve
                                                            </button>
                                                            <button
                                                                type="button"
                                                                class="rounded-md border border-gray-300 dark:border-gray-600 px-2.5 py-1 text-sm font-semibold text-gray-700 dark:text-gray-200 hover:bg-white dark:hover:bg-gray-800"
                                                                @click="reviewFeatureRequestAddon(fr, row.catalog_addon_id, 'denied')"
                                                            >
                                                                Decline
                                                            </button>
                                                        </span>
                                                    </li>
                                                </ul>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- ============================
                         Parts & Accessories (Inventory Items)
                         ============================ -->
                    <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h2 class="text-base font-semibold text-gray-900 dark:text-white">Parts &amp; Accessories</h2>
                        </div>

                        <!-- Mobile: card layout -->
                        <div v-if="lineItems.length > 0" class="block md:hidden divide-y divide-gray-200 dark:divide-gray-700">
                            <div
                                v-for="(item, ix) in lineItems"
                                :key="`inv-m-${item.id}-${item.pivot?.id ?? ix}`"
                                class="p-4 space-y-3"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0 flex-1">
                                        <div class="font-semibold text-base text-gray-900 dark:text-white">{{ item.display_name }}</div>
                                        <div v-if="item.sku" class="text-sm font-mono text-gray-500 dark:text-gray-400 mt-1">SKU {{ item.sku }}</div>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <div class="font-semibold text-base text-gray-900 dark:text-white">{{ formatCurrency(lineTotal(item)) }}</div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">Line total</div>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                                    <div>
                                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Est. cost</div>
                                        <div class="text-base text-gray-900 dark:text-white">{{ formatCurrency(item.pivot?.estimated_cost) }}</div>
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Unit price</div>
                                        <div class="text-base text-gray-900 dark:text-white">{{ formatCurrency(item.pivot?.unit_price) }}</div>
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Qty</div>
                                        <div class="text-base text-gray-900 dark:text-white">{{ item.pivot?.quantity ?? 1 }}</div>
                                    </div>
                                </div>
                                <div v-if="item.pivot?.notes" class="pt-2 border-t border-gray-100 dark:border-gray-700">
                                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Notes</div>
                                    <div class="text-base text-gray-700 dark:text-gray-300">{{ item.pivot.notes }}</div>
                                </div>
                                <div
                                    v-if="item.opportunity_addons && item.opportunity_addons.length > 0"
                                    class="pl-3 space-y-2 border-l-2 border-primary-200 dark:border-primary-700"
                                >
                                    <div
                                        v-for="(addon, aix) in item.opportunity_addons"
                                        :key="`inv-m-addon-${item.id}-${aix}`"
                                        class="flex items-center justify-between gap-2 text-base"
                                    >
                                        <div class="text-gray-600 dark:text-gray-400 italic min-w-0">
                                            ↳ {{ addon.name }} (× {{ addon.quantity }})
                                        </div>
                                        <div class="font-medium text-gray-900 dark:text-white shrink-0 tabular-nums">
                                            {{ formatCurrency(Number(addon.price || 0) * Number(addon.quantity || 1)) }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="border-t-2 border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/50 p-4 space-y-2">
                                <div class="flex justify-between text-base">
                                    <span class="font-semibold text-gray-700 dark:text-gray-300">Parts &amp; Acc. Subtotal (Revenue)</span>
                                    <span class="font-bold text-gray-900 dark:text-white">{{ formatCurrency(lineItemsSubtotal) }}</span>
                                </div>
                                <div class="flex justify-between text-base border-t border-gray-200 dark:border-gray-600 pt-2">
                                    <span class="font-semibold text-gray-500 dark:text-gray-400">Parts &amp; Acc. Total Cost</span>
                                    <span class="text-red-600 dark:text-red-400">{{ formatCurrency(lineItemsCostTotal) }}</span>
                                </div>
                                <div class="flex justify-between text-base border-t border-dashed border-gray-200 dark:border-gray-600 pt-2">
                                    <span class="font-semibold text-gray-700 dark:text-gray-300">Parts &amp; Acc. Gross Profit</span>
                                    <span
                                        class="font-bold"
                                        :class="(lineItemsSubtotal - lineItemsCostTotal) >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
                                    >
                                        {{ formatCurrency(lineItemsSubtotal - lineItemsCostTotal) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <!-- Desktop: table -->
                        <div v-if="lineItems.length > 0" class="hidden md:block overflow-x-auto">
                            <table class="w-full text-base">
                                <thead class="bg-gray-50 dark:bg-gray-700/50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-base font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Item</th>
                                        <th class="px-4 py-3 text-left text-base font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide w-24">SKU</th>
                                        <th class="px-4 py-3 text-right text-base font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide w-28">Estimated Cost</th>
                                        <th class="px-4 py-3 text-right text-base font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide w-28">Unit Price</th>
                                        <th class="px-4 py-3 text-right text-base font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide w-20">Qty</th>
                                        <th class="px-4 py-3 text-right text-base font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide w-28">Total</th>
                                        <th class="px-4 py-3 text-left text-base font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Notes</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    <template v-for="(item, ix) in lineItems" :key="`${item.id}-inv-${item.pivot?.id ?? ix}`">
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ item.display_name }}</td>
                                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400 font-mono text-sm">{{ item.sku || '—' }}</td>
                                            <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">{{ formatCurrency(item.pivot?.estimated_cost) }}</td>
                                            <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">{{ formatCurrency(item.pivot?.unit_price) }}</td>
                                            <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">{{ item.pivot?.quantity ?? 1 }}</td>
                                            <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">{{ formatCurrency(lineTotal(item)) }}</td>
                                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400 text-sm truncate max-w-[160px]">{{ item.pivot?.notes || '—' }}</td>
                                        </tr>
                                        <tr
                                            v-for="(addon, aix) in (item.opportunity_addons || [])"
                                            :key="`inv-addon-${item.id}-${aix}`"
                                            class="bg-primary-50/35 dark:bg-primary-900/15"
                                        >
                                            <td colspan="2" class="px-4 py-2 pl-8 text-sm text-gray-600 dark:text-gray-400 italic">
                                                ↳ {{ addon.name }}
                                            </td>
                                            <td class="px-4 py-2"></td>
                                            <td class="px-4 py-2 text-right text-sm text-gray-600 dark:text-gray-400">{{ formatCurrency(addon.price) }}</td>
                                            <td class="px-4 py-2 text-right text-sm text-gray-600 dark:text-gray-400">{{ addon.quantity }}</td>
                                            <td class="px-4 py-2 text-right text-sm font-medium text-gray-700 dark:text-gray-300">
                                                {{ formatCurrency(Number(addon.price || 0) * Number(addon.quantity || 1)) }}
                                            </td>
                                            <td class="px-4 py-2"></td>
                                        </tr>
                                    </template>
                                </tbody>
                                <tfoot class="bg-gray-50 dark:bg-gray-700/50 border-t-2 border-gray-200 dark:border-gray-600">
                                    <tr>
                                        <td colspan="5" class="px-4 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">Parts &amp; Acc. Subtotal (Revenue)</td>
                                        <td class="px-4 py-3 text-right text-sm font-bold text-gray-900 dark:text-white">{{ formatCurrency(lineItemsSubtotal) }}</td>
                                        <td></td>
                                    </tr>
                                    <tr class="border-t border-gray-200 dark:border-gray-600">
                                        <td colspan="5" class="px-4 py-3 text-right text-sm font-semibold text-gray-500 dark:text-gray-400">Parts &amp; Acc. Total Cost</td>
                                        <td class="px-4 py-3 text-right text-sm text-red-600 dark:text-red-400">{{ formatCurrency(lineItemsCostTotal) }}</td>
                                        <td></td>
                                    </tr>
                                    <tr class="border-t border-dashed border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800">
                                        <td colspan="5" class="px-4 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">Parts &amp; Acc. Gross Profit</td>
                                        <td class="px-4 py-3 text-right text-sm font-bold"
                                            :class="(lineItemsSubtotal - lineItemsCostTotal) >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
                                            {{ formatCurrency(lineItemsSubtotal - lineItemsCostTotal) }}
                                        </td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <div v-else class="flex flex-col items-center justify-center py-12 text-center px-6">
                            <svg class="w-10 h-10 text-gray-300 dark:text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                            <p class="text-base text-gray-400 dark:text-gray-500">No inventory items attached</p>
                        </div>
                    </div>

                    <!-- ============================
                         Qualification Product Requirements
                         ============================ -->
                    <div v-if="qualification" class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden">
                        <div class="bg-gradient-to-r from-secondary-600 to-secondary-700 dark:from-secondary-700 dark:to-secondary-800 px-6 py-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h2 class="text-lg font-bold text-white">Qualification Details</h2>
                                    <p class="text-secondary-100 text-base mt-0.5">Product requirements from the linked qualification</p>
                                </div>
                                <Link
                                    :href="route('qualifications.show', record.qualification_id)"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-white bg-white/20 hover:bg-white/30 rounded-lg transition-colors"
                                >
                                    View Qualification
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </Link>
                            </div>
                        </div>

                        <div class="p-6">
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-x-6 gap-y-5">

                                <!-- Desired Brand -->
                                <div>
                                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Desired Brand</div>
                                    <div class="text-sm text-gray-900 dark:text-gray-100">
                                        {{ qualification.desired_brand?.display_name ?? '—' }}
                                    </div>
                                </div>

                                <!-- Desired Model -->
                                <div>
                                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Desired Model</div>
                                    <div class="text-sm text-gray-900 dark:text-gray-100">{{ qualification.desired_model || '—' }}</div>
                                </div>

                                <!-- Preferred Length -->
                                <div>
                                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Preferred Length</div>
                                    <div class="text-sm text-gray-900 dark:text-gray-100">
                                        {{ qualification.preferred_length ? `${qualification.preferred_length} ft` : '—' }}
                                    </div>
                                </div>

                                <!-- Max Weight -->
                                <div>
                                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Max Weight</div>
                                    <div class="text-sm text-gray-900 dark:text-gray-100">
                                        {{ qualification.max_weight ? `${qualification.max_weight} lbs` : '—' }}
                                    </div>
                                </div>

                                <!-- Budget Range -->
                                <div>
                                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Budget Range</div>
                                    <div class="text-sm text-gray-900 dark:text-gray-100">{{ qualBudgetLabel }}</div>
                                </div>

                                <!-- Purchase Timeline -->
                                <div>
                                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Purchase Timeline</div>
                                    <div class="text-sm text-gray-900 dark:text-gray-100">{{ qualTimelineLabel }}</div>
                                </div>

                                <!-- Boolean badges row -->
                                <div class="col-span-full flex flex-wrap gap-3 pt-3 border-t border-gray-100 dark:border-gray-700">
                                    <span
                                        :class="qualification.needs_engine
                                            ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300'
                                            : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium"
                                    >
                                        <svg v-if="qualification.needs_engine" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        <svg v-else class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        Needs Engine
                                    </span>
                                    <span
                                        :class="qualification.needs_trailer
                                            ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300'
                                            : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium"
                                    >
                                        <svg v-if="qualification.needs_trailer" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        <svg v-else class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        Needs Trailer
                                    </span>
                                    <span
                                        :class="qualification.requires_delivery
                                            ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'
                                            : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium"
                                    >
                                        <svg v-if="qualification.requires_delivery" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        <svg v-else class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        Requires Delivery
                                    </span>
                                </div>

                                <!-- Delivery location details (only when requires_delivery) -->
                                <template v-if="qualification.requires_delivery">
                                    <div v-if="qualification.delivery_location">
                                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Delivery Location</div>
                                        <div class="text-sm text-gray-900 dark:text-gray-100">{{ qualification.delivery_location }}</div>
                                    </div>
                                    <div v-if="qualification.delivery_state">
                                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">State</div>
                                        <div class="text-sm text-gray-900 dark:text-gray-100">{{ qualification.delivery_state }}</div>
                                    </div>
                                    <div v-if="qualification.delivery_country">
                                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Country</div>
                                        <div class="text-sm text-gray-900 dark:text-gray-100">{{ qualification.delivery_country }}</div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- ============================
                     Sidebar
                     ============================ -->
                <div class="lg:col-span-4 space-y-6">

                    <!-- Actions -->
                    <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden ">
                        <div class="px-5 py-4 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">Actions</span>
                        </div>
                        <div class="p-5 space-y-3">
                            <Link
                                :href="route('opportunities.edit', buildResourceRouteParams('opportunities', record.id))"
                                class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                                Edit Opportunity
                            </Link>
                            <Link
                                :href="route('estimates.create') + '?from=opportunity&id=' + record.id"
                                class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-secondary-600 dark:text-secondary-400 bg-secondary-50 dark:bg-secondary-900/20 border border-secondary-200 dark:border-secondary-800 hover:bg-secondary-100 dark:hover:bg-secondary-900/30 rounded-lg transition-colors"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                Create Estimate
                            </Link>
                            <button
                                @click="handleDelete"
                                class="w-full inline-flex items-center justify-center px-4 py-2.5 text-sm font-medium text-red-600 dark:text-red-400 bg-white dark:bg-gray-700 border border-red-200 dark:border-red-800 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors"
                            >
                                Delete Opportunity
                            </button>
                        </div>
                    </div>

<!-- Deal Value -->
<div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-600">
        <span class="text-base font-semibold text-gray-900 dark:text-white">Deal Value</span>
    </div>
    <div class="p-5 space-y-3">

        <!-- Estimated Revenue (manual field) -->
        <div class="flex justify-between items-center">
            <span class="text-base text-gray-500 dark:text-gray-400">
                {{ fieldsSchema.estimated_value?.label || 'Estimated Revenue' }}
            </span>
            <span class="text-lg font-bold text-gray-900 dark:text-white">
                {{ formatCurrency(record.estimated_value) }}
            </span>
        </div>

        <!-- Line item breakdown (only when assets or parts exist) -->
        <div v-if="assets.length > 0 || lineItems.length > 0" class="pt-3 border-t border-gray-100 dark:border-gray-700 space-y-2">

            <!-- Revenue breakdown -->
            <div v-if="assets.length > 0" class="flex justify-between items-center text-base">
                <span class="text-gray-500 dark:text-gray-400">Assets Revenue</span>
                <span class="text-gray-700 dark:text-gray-300">{{ formatCurrency(assetSubtotal) }}</span>
            </div>
            <div v-if="lineItems.length > 0" class="flex justify-between items-center text-base">
                <span class="text-gray-500 dark:text-gray-400">Parts &amp; Acc. Revenue</span>
                <span class="text-gray-700 dark:text-gray-300">{{ formatCurrency(lineItemsSubtotal) }}</span>
            </div>
            <div class="flex justify-between items-center text-base pt-1 border-t border-gray-100 dark:border-gray-700">
                <span class="font-medium text-gray-700 dark:text-gray-300">Total Revenue</span>
                <span class="font-semibold text-gray-900 dark:text-white">{{ formatCurrency(combinedSubtotal) }}</span>
            </div>

            <!-- Cost + Profit -->
            <div class="flex justify-between items-center text-base pt-2 border-t border-gray-100 dark:border-gray-700">
                <span class="text-gray-500 dark:text-gray-400">Total Cost</span>
                <span class="text-red-600 dark:text-red-400">{{ formatCurrency(combinedCostTotal) }}</span>
            </div>
            <div class="flex justify-between items-center text-base pt-1 border-t border-dashed border-gray-200 dark:border-gray-600">
                <span class="font-semibold text-gray-700 dark:text-gray-300">Estimated Profit</span>
                <span :class="grossProfit >= 0
                    ? 'font-bold text-green-600 dark:text-green-400'
                    : 'font-bold text-red-600 dark:text-red-400'">
                    {{ formatCurrency(grossProfit) }}
                </span>
            </div>

            <!-- Margin % (bonus, only when revenue > 0) -->
            <div v-if="combinedSubtotal > 0" class="flex justify-between items-center text-base pt-1">
                <span class="text-gray-400 dark:text-gray-500">Margin</span>
                <span :class="grossProfit >= 0 ? 'text-green-500 dark:text-green-400' : 'text-red-500 dark:text-red-400'">
                    {{ ((grossProfit / combinedSubtotal) * 100).toFixed(1) }}%
                </span>
            </div>
        </div>
    </div>
</div>

                    <!-- Opportunity Info -->
                    <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-600">
                            <span class="text-base font-semibold text-gray-900 dark:text-white">Opportunity Info</span>
                        </div>
                        <div class="p-5 space-y-3 text-base">
                            <div v-if="record.opened_at" class="flex justify-between items-center">
                                <span class="text-gray-500 dark:text-gray-400">Opened</span>
                                <span class="text-gray-900 dark:text-gray-100">{{ formatDate(record.opened_at) }}</span>
                            </div>
                            <div v-if="record.expected_close_date" class="flex justify-between items-center">
                                <span class="text-gray-500 dark:text-gray-400">Expected Close</span>
                                <span class="text-gray-900 dark:text-gray-100">{{ formatDate(record.expected_close_date) }}</span>
                            </div>
                            <div v-if="record.won_at" class="flex justify-between items-center">
                                <span class="text-gray-500 dark:text-gray-400">Won</span>
                                <span class="font-medium text-green-700 dark:text-green-400">{{ formatDate(record.won_at) }}</span>
                            </div>
                            <div v-if="record.lost_at" class="flex justify-between items-center">
                                <span class="text-gray-500 dark:text-gray-400">Lost</span>
                                <span class="font-medium text-red-600 dark:text-red-400">{{ formatDate(record.lost_at) }}</span>
                            </div>
                            <div class="flex justify-between items-center pt-2 border-t border-gray-100 dark:border-gray-700">
                                <span class="text-gray-500 dark:text-gray-400">Created</span>
                                <span class="text-gray-900 dark:text-gray-100">{{ formatDate(record.created_at) }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-500 dark:text-gray-400">Last Updated</span>
                                <span class="text-gray-900 dark:text-gray-100">{{ formatDate(record.updated_at) }}</span>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </TenantLayout>

    <Teleport to="body">
        <div
            v-if="featureRequestModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
            role="dialog"
            aria-modal="true"
            @click.self="closeFeatureRequestModal"
        >
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl max-w-2xl w-full p-6 space-y-4 border border-gray-200 dark:border-gray-700 max-h-[90vh] overflow-y-auto">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Feature Request Form</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Send
                    {{ featureRequestSelectedPivotIds.length === 1 ? 'a link' : `${featureRequestSelectedPivotIds.length} links` }}
                    so the customer can choose asset options for the selected boat{{ featureRequestSelectedPivotIds.length === 1 ? '' : 's' }}.
                    Optionally include add-ons per line below.
                </p>

                <div class="space-y-1">
                    <label for="feature-request-customer-note" class="block text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                        Note to customer <span class="font-normal normal-case text-gray-400">(optional)</span>
                    </label>
                    <textarea
                        id="feature-request-customer-note"
                        v-model="featureRequestBatchForm.customer_note"
                        rows="3"
                        maxlength="5000"
                        placeholder="Add a short personalized message that will appear in the email…"
                        class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 shadow-sm text-sm focus:border-primary-500 focus:ring-primary-500"
                        :disabled="featureRequestBatchForm.processing"
                    />
                </div>

                <label class="flex items-start gap-3 cursor-pointer select-none">
                    <input
                        type="checkbox"
                        class="mt-1 rounded border-gray-300 dark:border-gray-600 text-primary-600 focus:ring-primary-500"
                        :checked="featureRequestIncludeAddons"
                        :disabled="featureRequestBatchForm.processing || !anyCatalogAddons"
                        @change="onFeatureRequestIncludeAddonsChange($event.target.checked)"
                    />
                    <span class="text-sm text-gray-800 dark:text-gray-200">
                        Include add-ons
                        <span v-if="!anyCatalogAddons" class="block text-sm font-normal text-amber-600 dark:text-amber-400 mt-1">
                            No add-ons in your catalog yet — create them under Inventory → Add-Ons first.
                        </span>
                        <span v-else class="block text-sm font-normal text-gray-500 dark:text-gray-400 mt-1">
                            Choose from all tenant add-ons for each boat line below.
                        </span>
                    </span>
                </label>

                <div v-if="featureRequestIncludeAddons && anyCatalogAddons" class="space-y-4">
                    <input
                        v-model="featureRequestAddonSearch"
                        type="search"
                        placeholder="Search add-ons…"
                        class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 shadow-sm text-sm focus:border-primary-500 focus:ring-primary-500"
                    />

                    <div
                        v-if="filteredCatalogAddons.length === 0 && catalogAddonsList.length > 0"
                        class="text-sm text-gray-500 dark:text-gray-400 py-2 text-center"
                    >
                        No add-ons match your search.
                    </div>

                    <template v-else>
                        <div
                            class="flex flex-wrap items-center justify-between gap-2 text-sm text-gray-600 dark:text-gray-400"
                        >
                            <span>{{ featureRequestAddonRangeLabel }}</span>
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    class="rounded-md border border-gray-300 dark:border-gray-600 px-2 py-1 font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-40 disabled:pointer-events-none"
                                    :disabled="featureRequestAddonEffectivePage <= 1 || featureRequestBatchForm.processing"
                                    @click="featureRequestAddonPage = Math.max(1, featureRequestAddonPage - 1)"
                                >
                                    Previous
                                </button>
                                <span class="tabular-nums">
                                    Page {{ featureRequestAddonEffectivePage }} / {{ featureRequestAddonPageCount }}
                                </span>
                                <button
                                    type="button"
                                    class="rounded-md border border-gray-300 dark:border-gray-600 px-2 py-1 font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-40 disabled:pointer-events-none"
                                    :disabled="
                                        featureRequestAddonEffectivePage >= featureRequestAddonPageCount ||
                                        featureRequestBatchForm.processing
                                    "
                                    @click="
                                        featureRequestAddonPage = Math.min(
                                            featureRequestAddonPageCount,
                                            featureRequestAddonPage + 1
                                        )
                                    "
                                >
                                    Next
                                </button>
                            </div>
                        </div>

                        <div
                            v-for="pid in featureRequestSelectedPivotIds"
                            :key="`fr-addons-${pid}`"
                            class="space-y-2 rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/40 p-3"
                        >
                            <div class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                                {{ assetLabelForPivot(pid) }}
                            </div>
                            <div class="space-y-1 pr-1">
                                <label
                                    v-for="addon in paginatedCatalogAddons"
                                    :key="`${pid}-cat-${addon.id}`"
                                    class="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm text-gray-800 dark:text-gray-200 hover:bg-white/80 dark:hover:bg-gray-800/80 cursor-pointer"
                                >
                                    <input
                                        type="checkbox"
                                        class="rounded border-gray-300 dark:border-gray-600 text-primary-600 focus:ring-primary-500 shrink-0"
                                        :checked="featureRequestCatalogAddonSelected(pid, addon.id)"
                                        @change="toggleFeatureRequestCatalogAddonId(pid, addon.id)"
                                    />
                                    <span class="flex-1 truncate">{{ addon.name }}</span>
                                    <span
                                        v-if="addon.default_price != null"
                                        class="text-sm text-gray-500 dark:text-gray-400 tabular-nums shrink-0"
                                    >
                                        {{ formatCurrency(addon.default_price) }}
                                    </span>
                                </label>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="flex flex-col-reverse sm:flex-row sm:flex-wrap gap-2 sm:justify-end pt-2">
                    <button
                        type="button"
                        class="inline-flex justify-center rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700"
                        :disabled="featureRequestBatchForm.processing"
                        @click="closeFeatureRequestModal"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="inline-flex justify-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50"
                        :disabled="featureRequestBatchForm.processing || featureRequestSubmitDisabled"
                        @click="submitFeatureRequestModal"
                    >
                        Send {{ featureRequestSelectedPivotIds.length === 1 ? 'link' : 'links' }} to customer
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
