<script setup>
/**
 * Deal-style line items (assets, services, parts) for invoices. Mirrors TransactionForm behaviour:
 * separate add/edit modals, add-ons, taxable + tax rate from parent form.
 */
import { computed, onMounted, ref, watch, watchEffect } from 'vue';
import AddonSelect from '@/Components/Tenant/AddonSelect.vue';
import { LINE_ITEM_ADDONS_UI_ENABLED } from '@/config/lineItemFeatures';

const lineItemAddonsUiEnabled = LINE_ITEM_ADDONS_UI_ENABLED;
import AssetLineModal from '@/Components/Tenant/AssetLineModal.vue';
import { lineAssetSelectedOptions, selectedOptionLabel } from '@/Utils/lineItemsFromEstimate';

const debounce = (fn, delay) => {
    let timer;
    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), delay);
    };
};

const props = defineProps({
    /** Inertia useForm (or compatible) — subtotal, tax_total, total, tax_rate, discount_total, fees_total */
    form: { type: Object, required: true },
    readonly: { type: Boolean, default: false },
    /** Rows from transaction prefill or invoice record.items */
    initialItems: { type: Array, default: () => [] },
    /** When false, subtotal/tax/fees/total and tax rate are rendered by the parent (e.g. invoice sidebar). */
    showTotalsPanel: { type: Boolean, default: true },
});

const normalizeTaxable = (v) => v !== false && v !== 0 && v !== '0' && v !== 'false';
const normalizeWarrantyType = (v) => (v === 'manufacturer' || v === 'dealership' ? v : null);
const deriveBillableTo = (item) => {
    if (item.billable_to === 'customer' || item.billable_to === 'manufacturer' || item.billable_to === 'internal') {
        return item.billable_to;
    }
    const isWarranty = item.is_warranty ?? item.warranty;
    const warrantyType = item.warranty_type;
    if (!isWarranty) return 'customer';
    return warrantyType === 'manufacturer' ? 'manufacturer' : 'internal';
};

const normalizeAddons = (addons, isNew = false) =>
    (addons || []).map((a) => ({
        id: isNew ? null : (a.id ?? null),
        addon_id: a.addon_id ?? null,
        name: a.name ?? '',
        price: Number(a.price) || 0,
        quantity: Number(a.quantity) || 1,
        notes: a.notes ?? '',
        taxable: normalizeTaxable(a.taxable ?? true),
    }));

/** Boat options from prefill JSON or from the linked deal line when editing an invoice row. */
const hydrateInvoiceLineAssetOptions = (item) => {
    const direct = item.selected_asset_options ?? item.selectedAssetOptions ?? [];
    if (direct.length) {
        return direct.map((r) => ({
            option_id: Number(r.option_id),
            option_value_id: Number(r.option_value_id),
            option_name: r.option_name ?? '',
            value_label: r.value_label ?? '',
            price: r.price != null ? Number(r.price) : 0,
            taxable: normalizeTaxable(r.taxable ?? true),
        }));
    }
    const tli = item.transaction_line_item ?? item.transactionLineItem;
    if (tli) {
        return lineAssetSelectedOptions(tli).map((r) => ({
            option_id: Number(r.option_id),
            option_value_id: Number(r.option_value_id),
            option_name: r.option_name ?? '',
            value_label: r.value_label ?? '',
            price: r.price != null ? Number(r.price) : 0,
            taxable: normalizeTaxable(r.taxable ?? true),
        }));
    }
    return [];
};

/** Suffix after "Primary line — " on flattened invoice child rows. */
const flatRowSuffix = (primaryName, rowName) => {
    const prefix = String(primaryName ?? '') + INVOICE_CHILD_SEP;
    const n = String(rowName ?? '');
    return n.startsWith(prefix) ? n.slice(prefix.length) : n;
};

/**
 * Invoice items persist boat options and add-ons as separate rows. Map them back onto the
 * parent asset line so the editor matches invoice show / deal line UX.
 */
const rehydratePersistedFlatRows = (primaryName, flatRows, transactionLineItem) => {
    const addons = [];
    const options = [];
    const tli = transactionLineItem;
    const txOptions = tli ? lineAssetSelectedOptions(tli) : [];
    const txAddons = tli?.addons ?? tli?.Addons ?? [];

    for (const row of flatRows) {
        const suffix = flatRowSuffix(primaryName, row.name);
        const matchedOpt = txOptions.find((opt) => selectedOptionLabel(opt) === suffix);
        if (matchedOpt) {
            options.push({
                option_id: Number(matchedOpt.option_id),
                option_value_id: Number(matchedOpt.option_value_id),
                option_name: matchedOpt.option_name ?? '',
                value_label: matchedOpt.value_label ?? '',
                price: Number(row.unit_price) || Number(matchedOpt.price) || 0,
                taxable: normalizeTaxable(row.taxable ?? matchedOpt.taxable ?? true),
            });
            continue;
        }

        const matchedAddon = txAddons.find((a) => String(a.name ?? '').trim() === suffix);
        if (matchedAddon) {
            addons.push({
                id: row.id ?? null,
                addon_id: matchedAddon.addon_id ?? null,
                name: suffix,
                price: Number(row.unit_price) || Number(matchedAddon.price) || 0,
                quantity: Number(row.quantity) || Number(matchedAddon.quantity) || 1,
                notes: row.description ?? matchedAddon.notes ?? '',
                taxable: normalizeTaxable(row.taxable ?? matchedAddon.taxable ?? true),
            });
            continue;
        }

        if (suffix.includes(':')) {
            const colonIdx = suffix.indexOf(':');
            options.push({
                option_id: 0,
                option_value_id: 0,
                option_name: suffix.slice(0, colonIdx).trim(),
                value_label: suffix.slice(colonIdx + 1).trim(),
                price: Number(row.unit_price) || 0,
                taxable: normalizeTaxable(row.taxable ?? true),
            });
            continue;
        }

        addons.push({
            id: row.id ?? null,
            addon_id: null,
            name: suffix || row.name || 'Add-on',
            price: Number(row.unit_price) || 0,
            quantity: Number(row.quantity) || 1,
            notes: row.description ?? '',
            taxable: normalizeTaxable(row.taxable ?? true),
        });
    }

    return { addons, options };
};

const normalizeItemBase = (item, isNew = false) => ({
    name: item.name ?? '',
    description: item.description ?? '',
    quantity: item.quantity ?? 1,
    unit_price: Number(item.unit_price) || 0,
    discount: Number(item.discount) || 0,
    cost: Number(item.cost ?? item.unit_cost) || 0,
    is_warranty: Boolean(item.is_warranty ?? item.warranty ?? false),
    warranty_type: normalizeWarrantyType(item.warranty_type),
    billable_to: deriveBillableTo(item),
    position: item.position ?? 0,
    taxable: normalizeTaxable(item.taxable ?? true),
    addons: normalizeAddons(item.addons, isNew),
    selected_asset_options: hydrateInvoiceLineAssetOptions(item),
    transaction_line_item_id: item.transaction_line_item_id ?? item.transaction_item_id ?? null,
    // Variant — can come from invoice item directly or from prefill via estimate_line_item
    asset_variant_id: item.asset_variant_id
        ?? item.estimate_line_item?.asset_variant_id
        ?? null,
    variant_name: item.assetVariant?.name
        ?? item.assetVariant?.display_name
        ?? item.estimate_line_item?.asset_variant?.name
        ?? item.estimate_line_item?.asset_variant?.display_name
        ?? '',
    asset_unit_id: item.asset_unit_id
        ?? item.assetUnitId
        ?? item.estimate_line_item?.asset_unit_id
        ?? null,
    unit_display_name: item.asset_unit?.display_name
        ?? item.assetUnit?.display_name
        ?? item.estimate_line_item?.asset_unit?.display_name
        ?? '',
    service_item_id: item.service_item_id ? Number(item.service_item_id) : null,
});

const lines = ref([]);

const INVOICE_CHILD_SEP = ' — ';

const applyItemRows = (src, { preserveIds = false } = {}) => {
    lines.value = [];
    const isNew = !preserveIds;
    const rows = src || [];
    const skipped = new Set();

    for (let i = 0; i < rows.length; i++) {
        if (skipped.has(i)) {
            continue;
        }

        const item = rows[i];
        const base = normalizeItemBase(item, isNew);

        if (item.itemable_type === 'App\\Domain\\Asset\\Models\\Asset') {
            const persistedFlatRows = [];
            for (let j = i + 1; j < rows.length; j++) {
                const child = rows[j];
                if (child.itemable_type) {
                    break;
                }
                if (String(child.name ?? '').startsWith(String(item.name ?? '') + INVOICE_CHILD_SEP)) {
                    persistedFlatRows.push(child);
                    skipped.add(j);
                }
            }

            if (persistedFlatRows.length > 0) {
                const rehydrated = rehydratePersistedFlatRows(
                    item.name,
                    persistedFlatRows,
                    item.transaction_line_item ?? item.transactionLineItem,
                );
                base.addons = rehydrated.addons;
                base.selected_asset_options = rehydrated.options;
            }

            lines.value.push({
                kind: 'asset',
                ...base,
                itemable_type: item.itemable_type,
                itemable_id: item.itemable_id ?? null,
                asset_id: item.itemable_id ?? null,
                year: item.itemable?.year || item.year || '',
                make: item.itemable?.make?.display_name || item.make || '',
                has_variants: Boolean(item.itemable?.has_variants),
                asset_description: (item.itemable?.description || '').trim() || '',
                persisted_flat_rows: [],
            });
        } else if (item.itemable_type === 'App\\Domain\\InventoryItem\\Models\\InventoryItem') {
            lines.value.push({
                kind: 'inventory',
                ...base,
                itemable_type: item.itemable_type,
                itemable_id: item.itemable_id ?? null,
                inventory_item_id: item.itemable_id ?? null,
                sku: item.itemable?.sku || item.sku || '',
            });
        } else if (item.service_item_id) {
            lines.value.push({
                kind: 'service',
                ...base,
                itemable_type: null,
                itemable_id: null,
                service_item_id: Number(item.service_item_id),
            });
        } else {
            lines.value.push({
                kind: 'legacy',
                ...base,
                itemable_type: null,
                itemable_id: null,
                inventory_item_id: null,
                sku: '',
            });
        }
    }
};

onMounted(() => {
    applyItemRows(props.initialItems, { preserveIds: true });
});

watch(
    () => props.initialItems,
    (items) => {
        if (Array.isArray(items) && items.length > 0) {
            applyItemRows(items, { preserveIds: true });
        }
    },
    { deep: true },
);

const roundMoney = (n) => Math.round((Number(n) + Number.EPSILON) * 100) / 100;
const lineBaseTotal = (item) =>
    item.billable_to && item.billable_to !== 'customer'
        ? 0
        : Math.max(0, Number(item.unit_price || 0) * Number(item.quantity || 1) - Number(item.discount || 0));
const addonPreTaxTotal = (a) => Number(a.price || 0) * Number(a.quantity || 1);
const lineAddonsTotal = (item) => (item.addons || []).reduce((s, a) => s + addonPreTaxTotal(a), 0);
const selectedOptionUnitPrice = (opt) => Number(opt?.price ?? 0);
const optionRowTaxable = (opt) => opt.taxable !== false && opt.taxable !== 0 && opt.taxable !== '0';
const lineAssetOptionsPreTaxTotal = (item) =>
    (item.selected_asset_options ?? []).reduce((s, o) => s + selectedOptionUnitPrice(o), 0);
const lineTotal = (item) => lineBaseTotal(item) + lineAddonsTotal(item) + lineAssetOptionsPreTaxTotal(item);

const dealTaxRatePercent = () => Number(props.form.tax_rate) || 0;
const taxOnItemBase = (item) => {
    const r = dealTaxRatePercent();
    if (!item.taxable || r <= 0) return 0;
    return roundMoney(lineBaseTotal(item) * (r / 100));
};
const taxOnAddon = (addon) => {
    const r = dealTaxRatePercent();
    if (!addon.taxable || r <= 0) return 0;
    return roundMoney(addonPreTaxTotal(addon) * (r / 100));
};
const taxOnAssetOptionRow = (opt) => {
    const r = dealTaxRatePercent();
    if (!optionRowTaxable(opt) || r <= 0) return 0;
    return roundMoney(selectedOptionUnitPrice(opt) * (r / 100));
};
const itemLineTaxTotal = (item) =>
    taxOnItemBase(item)
    + (item.addons || []).reduce((s, a) => s + taxOnAddon(a), 0)
    + (item.selected_asset_options ?? []).reduce((s, o) => s + taxOnAssetOptionRow(o), 0);
const lineCoreTotalWithTax = (item) => lineBaseTotal(item) + taxOnItemBase(item);

const computedSubtotal = computed(() => lines.value.reduce((s, i) => s + lineTotal(i), 0));
const computedLineItemsTax = computed(() => lines.value.reduce((s, i) => s + itemLineTaxTotal(i), 0));
const allItemsCount = computed(() => lines.value.length);

watchEffect(() => {
    lines.value.forEach((line) => {
        if (!line.is_warranty) {
            line.warranty_type = null;
            line.billable_to = 'customer';
            return;
        }

        if (line.billable_to !== 'manufacturer' && line.billable_to !== 'internal') {
            line.billable_to = 'internal';
        }

        line.warranty_type = line.billable_to === 'manufacturer' ? 'manufacturer' : 'dealership';
    });
});

watchEffect(() => {
    const f = props.form;
    const hasItems = allItemsCount.value > 0;
    let sub = hasItems ? computedSubtotal.value : Number(f.subtotal || 0);
    if (hasItems) {
        const nextSub = sub.toFixed(2);
        const nextTax = computedLineItemsTax.value.toFixed(2);
        if (String(f.subtotal) !== nextSub) f.subtotal = nextSub;
        if (String(f.tax_total) !== nextTax) f.tax_total = nextTax;
    }
    sub = hasItems ? computedSubtotal.value : Number(f.subtotal || 0);
    const tax = Number(f.tax_total || 0);
    const fees = Number(f.fees_total || 0);
    const nextTotal = (sub + tax + fees).toFixed(2);
    if (String(f.total) !== nextTotal) f.total = nextTotal;
});

const formatUnitCell = (raw) => {
    if (!raw) return '';
    const parts = String(raw).split(' - ');
    return parts.length > 1 ? parts.slice(1).join(' - ') : parts[0];
};

const formatMoney = (value) => {
    if (value == null || value === '') return '—';
    const n = parseFloat(value);
    if (Number.isNaN(n)) return '—';
    try {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: props.form.currency || 'USD',
        }).format(n);
    } catch {
        return `${props.form.currency || 'USD'} ${n.toFixed(2)}`;
    }
};

// ─── Asset modal ─────────────────────────────────────────────────────────────
const showAssetModal = ref(false);
const editingLineIndex = ref(null);

const emptyAssetForm = () => ({
    itemable_type: 'App\\Domain\\Asset\\Models\\Asset',
    itemable_id: null,
    asset_id: null,
    name: '',
    year: '',
    make: '',
    quantity: 1,
    unit_price: 0,
    cost: 0,
    discount: 0,
    is_warranty: false,
    warranty_type: null,
    billable_to: 'customer',
    notes: '',
    addons: [],
    has_variants: false,
    asset_variant_id: null,
    variant_display_name: '',
    asset_description: '',
    catalog_description: '',
    asset_unit_id: null,
    unit_display_name: '',
    selected_asset_options: [],
});
const assetForm = ref(emptyAssetForm());

// Row shape uses `description`, `variant_name`, and carries `taxable` + `kind` + `transaction_line_item_id`.
// The shared modal uses `notes` + `variant_display_name`. Bridge between the two.
const rowToModalForm = (row) => ({
    ...emptyAssetForm(),
    ...row,
    notes: row.description ?? '',
    variant_display_name: row.variant_name ?? row.variant_display_name ?? '',
    has_variants: !!(row.has_variants || row.asset_variant_id),
    addons: [...(row.addons || [])],
});

const modalFormToRow = (form, existing = {}) => {
    const { notes, variant_display_name, catalog_description: _catalog, ...rest } = form;
    return {
        ...rest,
        kind: 'asset',
        description: notes ?? '',
        variant_name: variant_display_name ?? '',
        taxable: existing.taxable ?? true,
        transaction_line_item_id: existing.transaction_line_item_id ?? existing.transaction_item_id ?? null,
        addons: [...(form.addons || [])],
        selected_asset_options: [...(form.selected_asset_options ?? existing.selected_asset_options ?? [])],
    };
};

const openAddAssetModal = () => {
    editingLineIndex.value = null;
    assetForm.value = emptyAssetForm();
    showAssetModal.value = true;
};
const openEditLineModal = (index) => {
    const row = lines.value[index];
    editingLineIndex.value = index;
    if (row.kind === 'asset') {
        assetForm.value = rowToModalForm(row);
        showAssetModal.value = true;
    } else {
        lineItemForm.value = {
            ...emptyLineItemForm(),
            ...row,
            addons: [...(row.addons || [])],
        };
        inventorySearchQuery.value = '';
        inventoryCurrentPage.value = 1;
        fetchInventoryItems(true);
        showInventoryModal.value = true;
    }
};

const saveAssetItem = () => {
    const existing = editingLineIndex.value !== null
        ? lines.value[editingLineIndex.value]
        : {};
    const row = modalFormToRow(assetForm.value, existing);
    if (editingLineIndex.value !== null) {
        lines.value[editingLineIndex.value] = row;
    } else {
        lines.value.push(row);
    }
    showAssetModal.value = false;
};
const removeLine = (index) => lines.value.splice(index, 1);

// ─── Inventory modal ─────────────────────────────────────────────────────────
const showInventoryModal = ref(false);
const inventorySearchQuery = ref('');
const inventoryRecords = ref([]);
const inventoryCurrentPage = ref(1);
const inventoryTotalPages = ref(1);
const inventoryIsLoading = ref(false);
const emptyLineItemForm = () => ({
    kind: 'inventory',
    itemable_type: 'App\\Domain\\InventoryItem\\Models\\InventoryItem',
    itemable_id: null,
    inventory_item_id: null,
    name: '',
    sku: '',
    quantity: 1,
    unit_price: 0,
    cost: 0,
    discount: 0,
    is_warranty: false,
    warranty_type: null,
    billable_to: 'customer',
    description: '',
    taxable: true,
    addons: [],
    transaction_line_item_id: null,
});
const lineItemForm = ref(emptyLineItemForm());

const fetchInventoryItems = async (resetPage = false) => {
    if (resetPage) inventoryCurrentPage.value = 1;
    inventoryIsLoading.value = true;
    try {
        const url = new URL(route('records.lookup'), window.location.origin);
        url.searchParams.append('type', 'InventoryItem');
        url.searchParams.append('page', inventoryCurrentPage.value);
        url.searchParams.append('per_page', '10');
        if (inventorySearchQuery.value.trim()) url.searchParams.append('search', inventorySearchQuery.value.trim());
        const res = await fetch(url.toString(), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            credentials: 'same-origin',
        });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();
        inventoryRecords.value = data.records || data.data || [];
        inventoryTotalPages.value = data.meta?.last_page || 1;
    } catch (e) {
        console.error(e);
        inventoryRecords.value = [];
    } finally {
        inventoryIsLoading.value = false;
    }
};
const debouncedFetchInventory = debounce(() => fetchInventoryItems(true), 300);

const openAddInventoryModal = () => {
    editingLineIndex.value = null;
    lineItemForm.value = emptyLineItemForm();
    inventorySearchQuery.value = '';
    inventoryCurrentPage.value = 1;
    fetchInventoryItems(true);
    showInventoryModal.value = true;
};
const selectInventoryItem = (inv) => {
    lineItemForm.value.itemable_id = inv.id;
    lineItemForm.value.inventory_item_id = inv.id;
    lineItemForm.value.name = inv.display_name;
    lineItemForm.value.sku = inv.sku || '';
    lineItemForm.value.unit_price = Number(inv.default_price) || 0;
    lineItemForm.value.cost = Number(inv.default_cost) || 0;
};
const saveInventoryItem = () => {
    if (!lineItemForm.value.itemable_id) return;
    const row = { ...lineItemForm.value, kind: 'inventory' };
    if (editingLineIndex.value !== null) {
        lines.value[editingLineIndex.value] = row;
    } else {
        lines.value.push(row);
    }
    showInventoryModal.value = false;
};

// ─── Add-ons ─────────────────────────────────────────────────────────────────
const showAddonModal = ref(false);
const currentAddonTargetItem = ref(null);
const openAddonModal = (lineItem) => {
    currentAddonTargetItem.value = lineItem;
    showAddonModal.value = true;
};
const onAddonPicked = (payload) => {
    if (!currentAddonTargetItem.value) return;
    if (!currentAddonTargetItem.value.addons) currentAddonTargetItem.value.addons = [];
    currentAddonTargetItem.value.addons.push(payload);
};

const editingAddonKey = ref(null);
const addonEditKey = (lineIndex, addonIndex) => `${lineIndex}-${addonIndex}`;
const isAddonEditing = (lineIndex, addonIndex) =>
    editingAddonKey.value === addonEditKey(lineIndex, addonIndex);
const toggleAddonEdit = (lineIndex, addonIndex, addon) => {
    const k = addonEditKey(lineIndex, addonIndex);
    if (editingAddonKey.value === k) {
        addon.price = Number(addon.price) || 0;
        editingAddonKey.value = null;
    } else {
        editingAddonKey.value = k;
    }
};
const removeAddon = (item, addonIdx) => {
    if (item.addons) item.addons.splice(addonIdx, 1);
    editingAddonKey.value = null;
};

// ─── Per-section filtered lists & subtotals ──────────────────────────────────
const assetLines = computed(() => lines.value.filter((l) => l.kind === 'asset'));
const serviceLines = computed(() => lines.value.filter((l) => l.kind === 'service'));
const inventoryLines = computed(() =>
    lines.value.filter((l) => l.kind === 'inventory' || l.kind === 'legacy'),
);
const computedAssetSubtotal = computed(() =>
    assetLines.value.reduce((s, i) => s + lineTotal(i), 0),
);
const computedAssetTax = computed(() =>
    assetLines.value.reduce((s, i) => s + itemLineTaxTotal(i), 0),
);
const computedServiceSubtotal = computed(() =>
    serviceLines.value.reduce((s, i) => s + lineTotal(i), 0),
);
const computedServiceTax = computed(() =>
    serviceLines.value.reduce((s, i) => s + itemLineTaxTotal(i), 0),
);
const computedInventorySubtotal = computed(() =>
    inventoryLines.value.reduce((s, i) => s + lineTotal(i), 0),
);
const computedInventoryTax = computed(() =>
    inventoryLines.value.reduce((s, i) => s + itemLineTaxTotal(i), 0),
);

defineExpose({
    hydrateFromItems: (items, opts) => applyItemRows(items, opts ?? {}),
    buildItemsForSubmit: (taxRatePercent) => buildItemsForSubmitInternal(taxRatePercent),
});

function buildItemsForSubmitInternal(taxRatePercent) {
    const rate = Number(taxRatePercent) || 0;
    const out = [];
    let pos = 0;
    lines.value.forEach((line) => {
        out.push({
            transaction_line_item_id: line.transaction_line_item_id ?? line.transaction_item_id ?? null,
            service_item_id: line.service_item_id || null,
            itemable_type: line.itemable_type || null,
            itemable_id: line.itemable_id || null,
            asset_variant_id: line.asset_variant_id || null,
            asset_unit_id: line.asset_unit_id || null,
            name: line.name,
            description: line.description || null,
            quantity: Number(line.quantity) || 1,
            unit_price: Number(line.unit_price) || 0,
            cost: Number(line.cost) || 0,
            discount: Number(line.discount) || 0,
            is_warranty: !!line.is_warranty,
            warranty_type: line.is_warranty ? (line.warranty_type ?? null) : null,
            billable_to: line.billable_to ?? 'customer',
            position: pos++,
            taxable: !!line.taxable,
            tax_rate: line.taxable ? rate : 0,
        });
        (line.addons || []).forEach((a) => {
            out.push({
                transaction_line_item_id: null,
                itemable_type: null,
                itemable_id: null,
                name: `${line.name} — ${a.name || 'Add-on'}`,
                description: a.notes || null,
                quantity: Number(a.quantity) || 1,
                unit_price: Number(a.price) || 0,
                discount: 0,
                position: pos++,
                taxable: !!a.taxable,
                tax_rate: a.taxable ? rate : 0,
            });
        });
        (line.selected_asset_options || []).forEach((opt) => {
            out.push({
                transaction_line_item_id: null,
                itemable_type: null,
                itemable_id: null,
                name: `${line.name} — ${selectedOptionLabel(opt)}`,
                description: null,
                quantity: 1,
                unit_price: Number(opt.price) || 0,
                discount: 0,
                position: pos++,
                taxable: optionRowTaxable(opt),
                tax_rate: optionRowTaxable(opt) ? rate : 0,
            });
        });
        (line.persisted_flat_rows || []).forEach((row) => {
            out.push({
                transaction_line_item_id: null,
                itemable_type: null,
                itemable_id: null,
                name: row.name,
                description: row.description || null,
                quantity: Number(row.quantity) || 1,
                unit_price: Number(row.unit_price) || 0,
                discount: Number(row.discount) || 0,
                position: pos++,
                taxable: !!row.taxable,
                tax_rate: row.taxable ? rate : 0,
            });
        });
    });
    return out;
}
</script>

<template>
    <div class="space-y-8">

        <!-- ─── Assets ──────────────────────────────────────────── -->
        <div class="border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between border-b pb-2 border-gray-200 dark:border-gray-700 mb-4">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wide">Assets</h3>
                <button
                    v-if="!readonly"
                    type="button"
                    class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 bg-blue-50 dark:bg-blue-900/20 hover:bg-blue-100 dark:hover:bg-blue-900/30 rounded-lg transition-colors"
                    @click="openAddAssetModal"
                >
                    <span class="material-icons text-base">add_circle</span>
                    Add Asset
                </button>
            </div>

            <div v-if="assetLines.length === 0" class="text-center py-12 bg-gray-50 dark:bg-gray-900/20 rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-700 mt-2">
                <span class="material-icons text-5xl text-gray-400 dark:text-gray-600 mb-3 block">inventory_2</span>
                <p class="text-sm text-gray-500 dark:text-gray-400">No assets added yet</p>
                <p v-if="!readonly" class="text-sm text-gray-400 dark:text-gray-500 mt-1">Click "Add Asset" to get started</p>
            </div>
            <template v-else>
            <div class="hidden lg:block overflow-x-auto -mx-6 sm:mx-0">
                <div class="inline-block min-w-full align-middle">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr>
                                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Asset</th>

                                <th class="px-4 py-3 text-right text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-20">Variant</th>
                                <th class="px-4 py-3 text-right text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-24">Unit</th>
                                <th class="px-4 py-3 text-right text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-20">Qty</th>
                                <th class="px-4 py-3 text-right text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-28">Unit Price</th>
                                <th class="px-4 py-3 text-right text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-28">Discount</th>
                                <th class="px-4 py-3 text-center text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-24">Warranty</th>
                                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-36">Billable To</th>
                                <th class="px-4 py-3 text-center text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-20">Taxable</th>
                                <th class="px-4 py-3 text-right text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-28">Pre-tax</th>
                                <th class="px-4 py-3 text-right text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-24">Tax</th>
                                <th class="px-4 py-3 text-right text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-28">Total</th>
                                <th v-if="lineItemAddonsUiEnabled" class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Add-ons</th>
                                <th v-if="!readonly" class="px-4 py-3 w-20"></th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <template v-for="(line, idx) in lines" :key="`asset-${idx}`">
                                <template v-if="line.kind === 'asset'">
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                        <td class="px-4 py-3">
                                            <div class="font-medium text-gray-900 dark:text-white text-sm">{{ line.name }}</div>
                                            <div v-if="line.year || line.make" class="text-sm text-gray-400 dark:text-gray-500 mt-0.5">{{ [line.year, line.make].filter(Boolean).join(' ') }}</div>
                                           
                                            <!-- <div v-if="line.description" class="text-sm text-gray-400 dark:text-gray-500 mt-0.5">{{ line.description }}</div> -->
                                        </td>
                                        <td class="px-4 py-3 text-right text-sm text-gray-700 dark:text-gray-300"> 
                                            <span v-if="line.variant_name" class="text-sm text-gray-500 dark:text-gray-400">{{ line.variant_name }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-right text-sm text-gray-700 dark:text-gray-300">
                                            <span v-if="line.asset_unit_id" class="text-sm text-gray-500 dark:text-gray-400">
                                                {{ formatUnitCell(line.unit_display_name) || `Unit #${line.asset_unit_id}` }}
                                            </span>
                                            <span v-else class="text-gray-400 dark:text-gray-500">—</span>
                                        </td>
                                        <td class="px-4 py-3 text-right text-sm text-gray-700 dark:text-gray-300"> {{ +line.quantity }}</td>
                                        <td class="px-4 py-3 text-right text-sm text-gray-700 dark:text-gray-300">{{ formatMoney(line.unit_price) }}</td>
                                        <td class="px-4 py-3 text-right text-sm text-gray-700 dark:text-gray-300">{{ formatMoney(line.discount) }}</td>
                                        <td class="px-4 py-3 text-center align-middle">
                                            <input
                                                v-if="!readonly"
                                                v-model="line.is_warranty"
                                                type="checkbox"
                                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700"
                                            />
                                            <span v-else class="text-xs text-gray-500 dark:text-gray-400">{{ line.is_warranty ? 'Yes' : 'No' }}</span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div v-if="!readonly" class="space-y-1">
                                                <select v-if="line.is_warranty" v-model="line.billable_to" class="input-style py-1 text-sm">
                                                    <option value="manufacturer">Manufacturer warranty</option>
                                                    <option value="internal">Dealership warranty (internal)</option>
                                                </select>
                                                <div v-else class="text-xs text-gray-500 dark:text-gray-400">
                                                    Customer
                                                </div>
                                            </div>
                                            <div v-else class="text-xs text-gray-500 dark:text-gray-400">
                                                <div v-if="line.is_warranty">
                                                    {{ line.billable_to === 'manufacturer' ? 'Manufacturer warranty' : 'Dealership warranty (internal)' }}
                                                </div>
                                                <div v-else>Customer</div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-center align-middle">
                                            <input
                                                v-if="!readonly"
                                                v-model="line.taxable"
                                                type="checkbox"
                                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700"
                                                title="Tax applies to this asset at invoice tax rate"
                                            />
                                            <span v-else class="text-xs text-gray-500 dark:text-gray-400">{{ line.taxable ? 'Yes' : 'No' }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-right text-sm font-medium text-gray-900 dark:text-white">{{ formatMoney(lineBaseTotal(line)) }}</td>
                                        <td class="px-4 py-3 text-right text-sm text-gray-600 dark:text-gray-300">{{ formatMoney(taxOnItemBase(line)) }}</td>
                                        <td class="px-4 py-3 text-right text-sm font-semibold text-gray-900 dark:text-white">{{ formatMoney(lineCoreTotalWithTax(line)) }}</td>
                                        <td v-if="lineItemAddonsUiEnabled" class="px-4 py-3">
                                            <button
                                                v-if="!readonly"
                                                type="button"
                                                class="inline-flex items-center gap-1 text-sm text-blue-600 dark:text-blue-400 hover:underline"
                                                @click="openAddonModal(line)"
                                            >
                                                <span class="material-icons text-sm">add_circle_outline</span>
                                                Add-ons ({{ (line.addons || []).length }})
                                            </button>
                                            <span v-else class="text-sm text-gray-400">{{ (line.addons || []).length }} add-on(s)</span>
                                        </td>
                                        <td v-if="!readonly" class="px-4 py-3">
                                            <div class="flex items-center justify-end gap-2">
                                                <button type="button" class="text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300" @click="openEditLineModal(idx)">
                                                    <span class="material-icons text-base">edit</span>
                                                </button>
                                                <button type="button" class="text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300" @click="removeLine(idx)">
                                                    <span class="material-icons text-base">delete</span>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr
                                        v-for="(opt, oidx) in (line.selected_asset_options || [])"
                                        :key="`asset-opt-${idx}-${oidx}`"
                                        class="bg-sky-50/30 dark:bg-sky-900/10"
                                    >
                                        <td class="pl-10 pr-4 py-2 text-sm text-gray-600 dark:text-gray-400 italic">
                                            ↳ {{ selectedOptionLabel(opt) }}
                                        </td>
                                        <td class="px-4 py-2 text-right text-sm text-gray-400"></td>
                                        <td class="px-4 py-2 text-right text-sm text-gray-400"></td>
                                        <td class="px-4 py-2 text-right text-sm text-gray-400">1</td>
                                        <td class="px-4 py-2 text-right text-sm text-gray-500 dark:text-gray-400">{{ formatMoney(selectedOptionUnitPrice(opt)) }}</td>
                                        <td class="px-4 py-2 text-right text-sm text-gray-400">—</td>
                                        <td class="px-4 py-2 text-center">
                                            <span class="text-xs text-gray-400 dark:text-gray-500">—</span>
                                        </td>
                                        <td class="px-4 py-2 text-xs text-gray-400 dark:text-gray-500">—</td>
                                        <td class="px-4 py-2 text-center">
                                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ optionRowTaxable(opt) ? 'Yes' : 'No' }}</span>
                                        </td>
                                        <td class="px-4 py-2 text-right text-sm font-medium text-gray-700 dark:text-gray-300">{{ formatMoney(selectedOptionUnitPrice(opt)) }}</td>
                                        <td class="px-4 py-2 text-right text-sm text-gray-600 dark:text-gray-400">{{ formatMoney(taxOnAssetOptionRow(opt)) }}</td>
                                        <td class="px-4 py-2 text-right text-sm font-medium text-gray-800 dark:text-gray-200">{{ formatMoney(selectedOptionUnitPrice(opt) + taxOnAssetOptionRow(opt)) }}</td>
                                        <td class="px-4 py-2"></td>
                                        <td v-if="!readonly" class="px-4 py-2"></td>
                                    </tr>
                                    <template v-if="lineItemAddonsUiEnabled">
                                    <tr
                                        v-for="(addon, aidx) in (line.addons || [])"
                                        :key="`asset-addon-${idx}-${aidx}`"
                                        class="bg-blue-50/30 dark:bg-blue-900/10"
                                    >
                                        <td class="pl-10 pr-4 py-2 text-sm text-gray-600 dark:text-gray-400 italic">
                                            ↳ {{ addon.name || 'Add-on' }}
                                            <span v-if="addon.notes" class="block text-gray-400 dark:text-gray-500 not-italic">{{ addon.notes }}</span>
                                        </td>
                                        <td class="px-4 py-2 text-right text-sm text-gray-400"></td>
                                        <td class="px-4 py-2 text-right text-sm text-gray-400"></td>
                                        <td class="px-4 py-2 text-right text-sm text-gray-400">{{ addon.quantity }}</td>
                                        <td class="px-4 py-2 text-right">
                                            <input
                                                v-if="!readonly && isAddonEditing(idx, aidx)"
                                                v-model.number="addon.price"
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                class="input-style w-28 py-1 text-right text-sm"
                                                title="Unit price for this invoice line only"
                                            />
                                            <span v-else class="text-sm text-gray-500 dark:text-gray-400">{{ formatMoney(addon.price) }}</span>
                                        </td>
                                        <td class="px-4 py-2 text-right text-sm text-gray-400">—</td>
                                        <td class="px-4 py-2 text-center">
                                            <input
                                                v-if="!readonly && isAddonEditing(idx, aidx)"
                                                v-model="addon.taxable"
                                                type="checkbox"
                                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700"
                                                title="Tax applies to this add-on"
                                            />
                                            <span v-else class="text-xs text-gray-500 dark:text-gray-400">{{ addon.taxable ? 'Yes' : 'No' }}</span>
                                        </td>
                                        <td class="px-4 py-2 text-right text-sm font-medium text-gray-700 dark:text-gray-300">{{ formatMoney(addonPreTaxTotal(addon)) }}</td>
                                        <td class="px-4 py-2 text-right text-sm text-gray-600 dark:text-gray-400">{{ formatMoney(taxOnAddon(addon)) }}</td>
                                        <td class="px-4 py-2 text-right text-sm font-medium text-gray-800 dark:text-gray-200">{{ formatMoney(addonPreTaxTotal(addon) + taxOnAddon(addon)) }}</td>
                                        <td></td>
                                        <td v-if="!readonly" class="px-4 py-2 text-right">
                                            <div class="flex items-center justify-end gap-1">
                                                <button
                                                    type="button"
                                                    :title="isAddonEditing(idx, aidx) ? 'Done editing' : 'Edit price and taxable'"
                                                    class="text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 p-1"
                                                    @click="toggleAddonEdit(idx, aidx, addon)"
                                                >
                                                    <span class="material-icons text-base">{{ isAddonEditing(idx, aidx) ? 'check' : 'edit' }}</span>
                                                </button>
                                                <button
                                                    type="button"
                                                    title="Remove add-on"
                                                    class="text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300 p-1"
                                                    @click="removeAddon(line, aidx)"
                                                >
                                                    <span class="material-icons text-base">close</span>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                                </template>
                            </template>
                        </tbody>
                        <tfoot class="bg-gray-50 dark:bg-gray-900/50 border-t-2 border-gray-200 dark:border-gray-600">
                            <tr>
                                <td colspan="9" class="px-4 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">Assets subtotal (pre-tax)</td>
                                <td class="px-4 py-3 text-right text-base font-bold text-gray-900 dark:text-white">{{ formatMoney(computedAssetSubtotal) }}</td>
                                <td class="px-4 py-3 text-right text-base font-bold text-gray-900 dark:text-white">{{ formatMoney(computedAssetTax) }}</td>
                                <td class="px-4 py-3 text-right text-base font-bold text-gray-900 dark:text-white">{{ formatMoney(computedAssetSubtotal + computedAssetTax) }}</td>
                                <td></td>
                                <td v-if="!readonly"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="block lg:hidden divide-y divide-gray-200 dark:divide-gray-700 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden -mx-6 sm:mx-0">
                <template v-for="(line, idx) in lines" :key="`asset-m-${idx}`">
                    <div v-if="line.kind === 'asset'" class="p-4 space-y-3 bg-white dark:bg-gray-800">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 flex-1 space-y-1">
                                <div class="font-semibold text-base text-gray-900 dark:text-white">{{ line.name }}</div>
                                <div v-if="line.year || line.make" class="text-sm text-gray-500 dark:text-gray-400">{{ [line.year, line.make].filter(Boolean).join(' ') }}</div>
                                <div v-if="line.variant_name" class="text-sm text-gray-500 dark:text-gray-400">Variant: {{ line.variant_name }}</div>
                                <div v-if="line.asset_unit_id" class="text-sm text-gray-500 dark:text-gray-400">Unit: {{ formatUnitCell(line.unit_display_name) || `Unit #${line.asset_unit_id}` }}</div>
                            </div>
                            <div v-if="!readonly" class="flex shrink-0 gap-1">
                                <button type="button" class="text-blue-600 dark:text-blue-400 p-1" @click="openEditLineModal(idx)">
                                    <span class="material-icons text-base">edit</span>
                                </button>
                                <button type="button" class="text-red-600 dark:text-red-400 p-1" @click="removeLine(idx)">
                                    <span class="material-icons text-base">delete</span>
                                </button>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                            <div>
                                <div class="text-[11px] font-semibold uppercase text-gray-500 dark:text-gray-400">Qty</div>
                                <div class="text-gray-900 dark:text-white">{{ +line.quantity }}</div>
                            </div>
                            <div>
                                <div class="text-[11px] font-semibold uppercase text-gray-500 dark:text-gray-400">Unit price</div>
                                <div class="text-gray-900 dark:text-white tabular-nums">{{ formatMoney(line.unit_price) }}</div>
                            </div>
                            <div>
                                <div class="text-[11px] font-semibold uppercase text-gray-500 dark:text-gray-400">Discount</div>
                                <div class="text-gray-900 dark:text-white tabular-nums">{{ formatMoney(line.discount) }}</div>
                            </div>
                            <div>
                                <div class="text-[11px] font-semibold uppercase text-gray-500 dark:text-gray-400">Line + tax (base)</div>
                                <div class="font-semibold text-gray-900 dark:text-white tabular-nums">{{ formatMoney(lineCoreTotalWithTax(line)) }}</div>
                            </div>
                        </div>
                        <div
                            v-if="(line.selected_asset_options || []).length"
                            class="pl-3 space-y-2 border-l-2 border-sky-200 dark:border-sky-700"
                        >
                            <div
                                v-for="(opt, oix) in line.selected_asset_options"
                                :key="`m-opt-${idx}-${oix}`"
                                class="flex justify-between gap-2 text-sm text-gray-700 dark:text-gray-300"
                            >
                                <span><span class="text-sky-600/90 dark:text-sky-400 mr-1">↳</span>{{ selectedOptionLabel(opt) }}</span>
                                <span class="font-medium tabular-nums shrink-0">{{ formatMoney(selectedOptionUnitPrice(opt) + taxOnAssetOptionRow(opt)) }}</span>
                            </div>
                        </div>
                        <div
                            v-if="lineItemAddonsUiEnabled && (line.addons || []).length"
                            class="pl-3 space-y-2 border-l-2 border-primary-200 dark:border-primary-700"
                        >
                            <div
                                v-for="(addon, aix) in line.addons"
                                :key="`m-ad-${idx}-${aix}`"
                                class="flex justify-between gap-2 text-sm"
                            >
                                <span class="text-gray-600 dark:text-gray-400 italic min-w-0">↳ {{ addon.name }} (× {{ addon.quantity }})</span>
                                <span class="font-medium tabular-nums shrink-0">{{ formatMoney(addonPreTaxTotal(addon) + taxOnAddon(addon)) }}</span>
                            </div>
                        </div>
                        <div v-if="lineItemAddonsUiEnabled && !readonly" class="pt-1">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 text-sm text-blue-600 dark:text-blue-400"
                                @click="openAddonModal(line)"
                            >
                                <span class="material-icons text-sm">add_circle_outline</span>
                                Add-ons
                            </button>
                        </div>
                    </div>
                </template>
                <div class="border-t-2 border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/50 p-4 space-y-1">
                    <div class="flex justify-between text-sm">
                        <span class="font-semibold text-gray-700 dark:text-gray-300">Assets subtotal (pre-tax)</span>
                        <span class="font-bold tabular-nums text-gray-900 dark:text-white">{{ formatMoney(computedAssetSubtotal) }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600 dark:text-gray-400">Tax on assets</span>
                        <span class="font-medium tabular-nums text-gray-900 dark:text-white">{{ formatMoney(computedAssetTax) }}</span>
                    </div>
                </div>
            </div>
            </template>
        </div>

        <!-- ─── Services ─────────────────────────────────────────── -->
        <div class="border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between border-b pb-2 border-gray-200 dark:border-gray-700 mb-4">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wide">Services</h3>
            </div>

            <div v-if="serviceLines.length === 0" class="text-center py-12 bg-gray-50 dark:bg-gray-900/20 rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-700 mt-2">
                <span class="material-icons text-5xl text-gray-400 dark:text-gray-600 mb-3 block">home_repair_service</span>
                <p class="text-sm text-gray-500 dark:text-gray-400">No service lines added yet</p>
            </div>
            <template v-else>
            <div class="hidden lg:block overflow-x-auto -mx-6 sm:mx-0">
                <div class="inline-block min-w-full align-middle">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr>
                                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Service</th>
                                <th class="px-4 py-3 text-right text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-20">Qty</th>
                                <th class="px-4 py-3 text-right text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-28">Unit Price</th>
                                <th class="px-4 py-3 text-right text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-28">Discount</th>
                                <th class="px-4 py-3 text-center text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-24">Warranty</th>
                                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-36">Billable To</th>
                                <th class="px-4 py-3 text-center text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-20">Taxable</th>
                                <th class="px-4 py-3 text-right text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-28">Pre-tax</th>
                                <th class="px-4 py-3 text-right text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-24">Tax</th>
                                <th class="px-4 py-3 text-right text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-28">Total</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <template v-for="(line, idx) in lines" :key="`svc-${idx}`">
                                <tr v-if="line.kind === 'service'" class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-gray-900 dark:text-white text-sm">{{ line.name }}</div>
                                        <div v-if="line.description" class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{{ line.description }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm text-gray-700 dark:text-gray-300">{{ +line.quantity }}</td>
                                    <td class="px-4 py-3 text-right text-sm text-gray-700 dark:text-gray-300">{{ formatMoney(line.unit_price) }}</td>
                                    <td class="px-4 py-3 text-right text-sm text-gray-700 dark:text-gray-300">{{ formatMoney(line.discount) }}</td>
                                    <td class="px-4 py-3 text-center align-middle">
                                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ line.is_warranty ? 'Yes' : 'No' }}</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            <template v-if="line.is_warranty">
                                                {{ line.billable_to === 'manufacturer' ? 'Manufacturer warranty' : 'Dealership warranty (internal)' }}
                                            </template>
                                            <template v-else>Customer</template>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-center align-middle">
                                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ line.taxable ? 'Yes' : 'No' }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm font-medium text-gray-900 dark:text-white">{{ formatMoney(lineBaseTotal(line)) }}</td>
                                    <td class="px-4 py-3 text-right text-sm text-gray-600 dark:text-gray-300">{{ formatMoney(taxOnItemBase(line)) }}</td>
                                    <td class="px-4 py-3 text-right text-sm font-semibold text-gray-900 dark:text-white">{{ formatMoney(lineCoreTotalWithTax(line)) }}</td>
                                </tr>
                            </template>
                        </tbody>
                        <tfoot class="bg-gray-50 dark:bg-gray-700/50 border-t-2 border-gray-200 dark:border-gray-600">
                            <tr>
                                <td colspan="7" class="px-4 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">Services subtotal (pre-tax)</td>
                                <td class="px-4 py-3 text-right text-sm font-bold text-gray-900 dark:text-white">{{ formatMoney(computedServiceSubtotal) }}</td>
                                <td class="px-4 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">{{ formatMoney(computedServiceTax) }}</td>
                                <td class="px-4 py-3 text-right text-sm font-bold text-gray-900 dark:text-white">{{ formatMoney(computedServiceSubtotal + computedServiceTax) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="block lg:hidden divide-y divide-gray-200 dark:divide-gray-700 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden -mx-6 sm:mx-0">
                <template v-for="(line, idx) in lines" :key="`svc-m-${idx}`">
                    <div v-if="line.kind === 'service'" class="p-4 space-y-3 bg-white dark:bg-gray-800">
                        <div class="min-w-0 space-y-1">
                            <div class="font-semibold text-base text-gray-900 dark:text-white">{{ line.name }}</div>
                            <div v-if="line.description" class="text-sm text-gray-500 dark:text-gray-400">{{ line.description }}</div>
                        </div>
                        <div class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                            <div>
                                <div class="text-[11px] font-semibold uppercase text-gray-500 dark:text-gray-400">Qty</div>
                                <div class="text-gray-900 dark:text-white">{{ +line.quantity }}</div>
                            </div>
                            <div>
                                <div class="text-[11px] font-semibold uppercase text-gray-500 dark:text-gray-400">Unit price</div>
                                <div class="text-gray-900 dark:text-white tabular-nums">{{ formatMoney(line.unit_price) }}</div>
                            </div>
                            <div>
                                <div class="text-[11px] font-semibold uppercase text-gray-500 dark:text-gray-400">Line + tax</div>
                                <div class="font-semibold text-gray-900 dark:text-white tabular-nums">{{ formatMoney(lineCoreTotalWithTax(line)) }}</div>
                            </div>
                        </div>
                    </div>
                </template>
                <div class="border-t-2 border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/50 p-4 space-y-1">
                    <div class="flex justify-between text-sm">
                        <span class="font-semibold text-gray-700 dark:text-gray-300">Services subtotal (pre-tax)</span>
                        <span class="font-bold tabular-nums text-gray-900 dark:text-white">{{ formatMoney(computedServiceSubtotal) }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600 dark:text-gray-400">Tax on services</span>
                        <span class="font-medium tabular-nums text-gray-900 dark:text-white">{{ formatMoney(computedServiceTax) }}</span>
                    </div>
                </div>
            </div>
            </template>
        </div>

        <!-- ─── Parts & Accessories ─────────────────────────────── -->
        <div class="border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between border-b pb-2 border-gray-200 dark:border-gray-700 mb-4">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wide">Parts &amp; Accessories</h3>
                <button
                    v-if="!readonly"
                    type="button"
                    class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 bg-blue-50 dark:bg-blue-900/20 hover:bg-blue-100 dark:hover:bg-blue-900/30 rounded-lg transition-colors"
                    @click="openAddInventoryModal"
                >
                    <span class="material-icons text-base">add_circle</span>
                    Add Part
                </button>
            </div>

            <div v-if="inventoryLines.length === 0" class="text-center py-12 bg-gray-50 dark:bg-gray-900/20 rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-700 mt-2">
                <span class="material-icons text-5xl text-gray-400 dark:text-gray-600 mb-3 block">handyman</span>
                <p class="text-sm text-gray-500 dark:text-gray-400">No parts or accessories added yet</p>
                <p v-if="!readonly" class="text-sm text-gray-400 dark:text-gray-500 mt-1">Click "Add Part" to get started</p>
            </div>
            <template v-else>
            <div class="hidden lg:block overflow-x-auto -mx-6 sm:mx-0">
                <div class="inline-block min-w-full align-middle">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr>
                                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Item</th>
                                <th class="px-4 py-3 text-right text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-20">Qty</th>
                                <th class="px-4 py-3 text-right text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-28">Unit Price</th>
                                <th class="px-4 py-3 text-right text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-28">Discount</th>
                                <th class="px-4 py-3 text-center text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-24">Warranty</th>
                                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-36">Billable To</th>
                                <th class="px-4 py-3 text-center text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-20">Taxable</th>
                                <th class="px-4 py-3 text-right text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-28">Pre-tax</th>
                                <th class="px-4 py-3 text-right text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-24">Tax</th>
                                <th class="px-4 py-3 text-right text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-28">Total</th>
                                <th v-if="lineItemAddonsUiEnabled" class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Add-ons</th>
                                <th v-if="!readonly" class="px-4 py-3 w-20"></th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <template v-for="(line, idx) in lines" :key="`inv-${idx}`">
                                <template v-if="line.kind === 'inventory' || line.kind === 'legacy'">
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                        <td class="px-4 py-3">
                                            <div class="font-medium text-gray-900 dark:text-white text-sm">{{ line.name }}</div>
                                            <span v-if="line.kind === 'legacy'" class="inline-block mt-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-400 bg-amber-100 dark:bg-amber-900/40 px-1.5 py-0.5 rounded">Legacy line</span>
                                            <div v-if="line.sku" class="text-sm text-gray-400 dark:text-gray-500 mt-0.5">SKU: {{ line.sku }}</div>
                                        </td>
                                        <td class="px-4 py-3 text-right text-sm text-gray-700 dark:text-gray-300">{{ +line.quantity }}</td>
                                        <td class="px-4 py-3 text-right text-sm text-gray-700 dark:text-gray-300">{{ formatMoney(line.unit_price) }}</td>
                                        <td class="px-4 py-3 text-right text-sm text-gray-700 dark:text-gray-300">{{ formatMoney(line.discount) }}</td>
                                        <td class="px-4 py-3 text-center align-middle">
                                            <input
                                                v-if="!readonly"
                                                v-model="line.is_warranty"
                                                type="checkbox"
                                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700"
                                            />
                                            <span v-else class="text-xs text-gray-500 dark:text-gray-400">{{ line.is_warranty ? 'Yes' : 'No' }}</span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div v-if="!readonly" class="space-y-1">
                                                <select v-if="line.is_warranty" v-model="line.billable_to" class="input-style py-1 text-sm">
                                                    <option value="manufacturer">Manufacturer warranty</option>
                                                    <option value="internal">Dealership warranty (internal)</option>
                                                </select>
                                                <div v-else class="text-xs text-gray-500 dark:text-gray-400">
                                                    Customer
                                                </div>
                                            </div>
                                            <div v-else class="text-xs text-gray-500 dark:text-gray-400">
                                                <div v-if="line.is_warranty">
                                                    {{ line.billable_to === 'manufacturer' ? 'Manufacturer warranty' : 'Dealership warranty (internal)' }}
                                                </div>
                                                <div v-else>Customer</div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-center align-middle">
                                            <input
                                                v-if="!readonly"
                                                v-model="line.taxable"
                                                type="checkbox"
                                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700"
                                                title="Tax applies to this item at invoice tax rate"
                                            />
                                            <span v-else class="text-xs text-gray-500 dark:text-gray-400">{{ line.taxable ? 'Yes' : 'No' }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-right text-sm font-medium text-gray-900 dark:text-white">{{ formatMoney(lineBaseTotal(line)) }}</td>
                                        <td class="px-4 py-3 text-right text-sm text-gray-600 dark:text-gray-300">{{ formatMoney(taxOnItemBase(line)) }}</td>
                                        <td class="px-4 py-3 text-right text-sm font-semibold text-gray-900 dark:text-white">{{ formatMoney(lineCoreTotalWithTax(line)) }}</td>
                                        <td v-if="lineItemAddonsUiEnabled" class="px-4 py-3">
                                            <button
                                                v-if="!readonly"
                                                type="button"
                                                class="inline-flex items-center gap-1 text-sm text-blue-600 dark:text-blue-400 hover:underline"
                                                @click="openAddonModal(line)"
                                            >
                                                <span class="material-icons text-sm">add_circle_outline</span>
                                                Add-ons ({{ (line.addons || []).length }})
                                            </button>
                                            <span v-else class="text-sm text-gray-400">{{ (line.addons || []).length }} add-on(s)</span>
                                        </td>
                                        <td v-if="!readonly" class="px-4 py-3">
                                            <div class="flex items-center justify-end gap-2">
                                                <button type="button" class="text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300" @click="openEditLineModal(idx)">
                                                    <span class="material-icons text-base">edit</span>
                                                </button>
                                                <button type="button" class="text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300" @click="removeLine(idx)">
                                                    <span class="material-icons text-base">delete</span>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <template v-if="lineItemAddonsUiEnabled">
                                    <tr
                                        v-for="(addon, aidx) in (line.addons || [])"
                                        :key="`inv-addon-${idx}-${aidx}`"
                                        class="bg-blue-50/30 dark:bg-blue-900/10"
                                    >
                                        <td class="pl-10 pr-4 py-2 text-sm text-gray-600 dark:text-gray-400 italic">
                                            ↳ {{ addon.name || 'Add-on' }}
                                            <span v-if="addon.notes" class="block text-gray-400 dark:text-gray-500 not-italic">{{ addon.notes }}</span>
                                        </td>
                                        <td class="px-4 py-2 text-right text-sm text-gray-400">{{ addon.quantity }}</td>
                                        <td class="px-4 py-2 text-right">
                                            <input
                                                v-if="!readonly && isAddonEditing(idx, aidx)"
                                                v-model.number="addon.price"
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                class="input-style w-28 py-1 text-right text-sm"
                                                title="Unit price for this invoice line only"
                                            />
                                            <span v-else class="text-sm text-gray-500 dark:text-gray-400">{{ formatMoney(addon.price) }}</span>
                                        </td>
                                        <td class="px-4 py-2 text-right text-sm text-gray-400">—</td>
                                        <td class="px-4 py-2 text-center">
                                            <input
                                                v-if="!readonly && isAddonEditing(idx, aidx)"
                                                v-model="addon.taxable"
                                                type="checkbox"
                                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700"
                                                title="Tax applies to this add-on"
                                            />
                                            <span v-else class="text-xs text-gray-500 dark:text-gray-400">{{ addon.taxable ? 'Yes' : 'No' }}</span>
                                        </td>
                                        <td class="px-4 py-2 text-right text-sm font-medium text-gray-700 dark:text-gray-300">{{ formatMoney(addonPreTaxTotal(addon)) }}</td>
                                        <td class="px-4 py-2 text-right text-sm text-gray-600 dark:text-gray-400">{{ formatMoney(taxOnAddon(addon)) }}</td>
                                        <td class="px-4 py-2 text-right text-sm font-medium text-gray-800 dark:text-gray-200">{{ formatMoney(addonPreTaxTotal(addon) + taxOnAddon(addon)) }}</td>
                                        <td></td>
                                        <td v-if="!readonly" class="px-4 py-2 text-right">
                                            <div class="flex items-center justify-end gap-1">
                                                <button
                                                    type="button"
                                                    :title="isAddonEditing(idx, aidx) ? 'Done editing' : 'Edit price and taxable'"
                                                    class="text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 p-1"
                                                    @click="toggleAddonEdit(idx, aidx, addon)"
                                                >
                                                    <span class="material-icons text-base">{{ isAddonEditing(idx, aidx) ? 'check' : 'edit' }}</span>
                                                </button>
                                                <button
                                                    type="button"
                                                    title="Remove add-on"
                                                    class="text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300 p-1"
                                                    @click="removeAddon(line, aidx)"
                                                >
                                                    <span class="material-icons text-base">close</span>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                                </template>
                            </template>
                        </tbody>
                        <tfoot class="bg-gray-50 dark:bg-gray-900/50 border-t-2 border-gray-200 dark:border-gray-600">
                            <tr>
                                <td colspan="7" class="px-4 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">Parts subtotal (pre-tax)</td>
                                <td class="px-4 py-3 text-right text-base font-bold text-gray-900 dark:text-white">{{ formatMoney(computedInventorySubtotal) }}</td>
                                <td class="px-4 py-3 text-right text-base font-bold text-gray-900 dark:text-white">{{ formatMoney(computedInventoryTax) }}</td>
                                <td class="px-4 py-3 text-right text-base font-bold text-gray-900 dark:text-white">{{ formatMoney(computedInventorySubtotal + computedInventoryTax) }}</td>
                                <td></td>
                                <td v-if="!readonly"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="block lg:hidden divide-y divide-gray-200 dark:divide-gray-700 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden -mx-6 sm:mx-0">
                <template v-for="(line, idx) in lines" :key="`inv-m-${idx}`">
                    <div v-if="line.kind === 'inventory' || line.kind === 'legacy'" class="p-4 space-y-3 bg-white dark:bg-gray-800">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 flex-1 space-y-1">
                                <div class="font-semibold text-base text-gray-900 dark:text-white">{{ line.name }}</div>
                                <span v-if="line.kind === 'legacy'" class="inline-block text-[10px] font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-400 bg-amber-100 dark:bg-amber-900/40 px-1.5 py-0.5 rounded">Legacy line</span>
                                <div v-if="line.sku" class="text-sm text-gray-500 dark:text-gray-400">SKU: {{ line.sku }}</div>
                            </div>
                            <div v-if="!readonly" class="flex shrink-0 gap-1">
                                <button type="button" class="text-blue-600 dark:text-blue-400 p-1" @click="openEditLineModal(idx)">
                                    <span class="material-icons text-base">edit</span>
                                </button>
                                <button type="button" class="text-red-600 dark:text-red-400 p-1" @click="removeLine(idx)">
                                    <span class="material-icons text-base">delete</span>
                                </button>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                            <div>
                                <div class="text-[11px] font-semibold uppercase text-gray-500 dark:text-gray-400">Qty</div>
                                <div class="text-gray-900 dark:text-white">{{ +line.quantity }}</div>
                            </div>
                            <div>
                                <div class="text-[11px] font-semibold uppercase text-gray-500 dark:text-gray-400">Unit price</div>
                                <div class="text-gray-900 dark:text-white tabular-nums">{{ formatMoney(line.unit_price) }}</div>
                            </div>
                            <div>
                                <div class="text-[11px] font-semibold uppercase text-gray-500 dark:text-gray-400">Discount</div>
                                <div class="text-gray-900 dark:text-white tabular-nums">{{ formatMoney(line.discount) }}</div>
                            </div>
                            <div>
                                <div class="text-[11px] font-semibold uppercase text-gray-500 dark:text-gray-400">Line + tax (base)</div>
                                <div class="font-semibold text-gray-900 dark:text-white tabular-nums">{{ formatMoney(lineCoreTotalWithTax(line)) }}</div>
                            </div>
                        </div>
                        <div
                            v-if="lineItemAddonsUiEnabled && (line.addons || []).length"
                            class="pl-3 space-y-2 border-l-2 border-primary-200 dark:border-primary-700"
                        >
                            <div
                                v-for="(addon, aix) in line.addons"
                                :key="`inv-m-ad-${idx}-${aix}`"
                                class="flex justify-between gap-2 text-sm"
                            >
                                <span class="text-gray-600 dark:text-gray-400 italic min-w-0">↳ {{ addon.name }} (× {{ addon.quantity }})</span>
                                <span class="font-medium tabular-nums shrink-0">{{ formatMoney(addonPreTaxTotal(addon) + taxOnAddon(addon)) }}</span>
                            </div>
                        </div>
                        <div v-if="lineItemAddonsUiEnabled && !readonly" class="pt-1">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 text-sm text-blue-600 dark:text-blue-400"
                                @click="openAddonModal(line)"
                            >
                                <span class="material-icons text-sm">add_circle_outline</span>
                                Add-ons
                            </button>
                        </div>
                    </div>
                </template>
                <div class="border-t-2 border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/50 p-4 space-y-1">
                    <div class="flex justify-between text-sm">
                        <span class="font-semibold text-gray-700 dark:text-gray-300">Parts subtotal (pre-tax)</span>
                        <span class="font-bold tabular-nums text-gray-900 dark:text-white">{{ formatMoney(computedInventorySubtotal) }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600 dark:text-gray-400">Tax on parts</span>
                        <span class="font-medium tabular-nums text-gray-900 dark:text-white">{{ formatMoney(computedInventoryTax) }}</span>
                    </div>
                </div>
            </div>
            </template>
        </div>

        <!-- ─── Invoice totals + tax rate (optional; parent can render in sidebar) ─── -->
        <template v-if="showTotalsPanel">
            <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-3">Invoice totals</h4>
                <div class="max-w-sm ml-auto space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-gray-400">Subtotal (lines)</span>
                        <span class="font-medium text-gray-900 dark:text-white">{{ formatMoney(form.subtotal) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-gray-400">Tax ({{ Number(form.tax_rate) || 0 }}%)</span>
                        <span class="font-medium text-gray-900 dark:text-white">{{ formatMoney(form.tax_total) }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-500 dark:text-gray-400">Fees</span>
                        <input
                            v-if="!readonly"
                            v-model.number="form.fees_total"
                            type="number"
                            min="0"
                            step="0.01"
                            class="input-style w-28 py-1 text-right text-sm"
                        >
                        <span v-else class="font-medium text-gray-900 dark:text-white">{{ formatMoney(form.fees_total) }}</span>
                    </div>
                    <div class="flex justify-between text-lg font-bold border-t border-gray-200 dark:border-gray-600 pt-2 text-gray-900 dark:text-white">
                        <span>Total</span>
                        <span>{{ formatMoney(form.total) }}</span>
                    </div>
                </div>
            </div>

            <div v-if="!readonly" class="flex flex-wrap gap-4 items-end">
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Tax rate (%)</label>
                    <input
                        v-model.number="form.tax_rate"
                        type="number"
                        step="0.001"
                        min="0"
                        max="100"
                        class="input-style w-32 py-2 text-sm"
                    >
                </div>
            </div>
        </template>

        <!-- LINE_ITEM_ADDONS_UI: disabled via config/lineItemFeatures.js -->
        <AddonSelect v-if="lineItemAddonsUiEnabled" v-model:open="showAddonModal" accent="primary" @picked="onAddonPicked" />

        <!-- Asset modal -->
        <AssetLineModal
            v-model="assetForm"
            v-model:open="showAssetModal"
            :editing="editingLineIndex !== null"
            @save="saveAssetItem"
        />

        <!-- Part modal -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition ease-out duration-200"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition ease-in duration-150"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="showInventoryModal" class="fixed inset-0 z-50 flex items-start justify-center pt-16 px-4 pb-8">
                    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="showInventoryModal = false" />
                    <div class="relative bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-2xl max-h-[80vh] flex flex-col">
                        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                {{ editingLineIndex !== null ? 'Edit Part' : 'Add Part' }}
                            </h3>
                            <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 p-1" @click="showInventoryModal = false">
                                <span class="material-icons">close</span>
                            </button>
                        </div>
                        <div class="overflow-y-auto p-6 space-y-4 flex-1">
                            <div v-if="editingLineIndex === null">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Search parts &amp; accessories</label>
                                <input v-model="inventorySearchQuery" type="text" placeholder="Search by name or SKU…" class="input-style" @input="debouncedFetchInventory">
                                <div v-if="inventoryIsLoading" class="text-xs text-gray-500 mt-2">Loading…</div>
                                <div
                                    v-else-if="inventoryRecords.length"
                                    class="mt-2 border border-gray-200 dark:border-gray-600 rounded-lg divide-y divide-gray-200 dark:divide-gray-600 max-h-48 overflow-y-auto"
                                >
                                    <button
                                        v-for="inv in inventoryRecords"
                                        :key="inv.id"
                                        type="button"
                                        class="w-full px-4 py-2.5 text-left text-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                                        :class="{ 'bg-blue-50 dark:bg-blue-900/20': lineItemForm.inventory_item_id === inv.id }"
                                        @click="selectInventoryItem(inv)"
                                    >
                                        <span class="font-medium text-gray-900 dark:text-white">{{ inv.display_name }}</span>
                                        <span v-if="inv.sku" class="text-gray-400 text-xs ml-2">{{ inv.sku }}</span>
                                    </button>
                                </div>
                                <p v-else-if="!inventoryIsLoading && inventorySearchQuery" class="text-xs text-gray-500 mt-2">No items found.</p>
                                <div v-if="inventoryTotalPages > 1" class="flex justify-end gap-2 mt-2">
                                    <button type="button" class="text-xs px-2 py-1 border border-gray-300 dark:border-gray-600 rounded hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-40" :disabled="inventoryCurrentPage <= 1" @click="inventoryCurrentPage--; fetchInventoryItems()">Prev</button>
                                    <span class="text-xs text-gray-500 self-center">{{ inventoryCurrentPage }} / {{ inventoryTotalPages }}</span>
                                    <button type="button" class="text-xs px-2 py-1 border border-gray-300 dark:border-gray-600 rounded hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-40" :disabled="inventoryCurrentPage >= inventoryTotalPages" @click="inventoryCurrentPage++; fetchInventoryItems()">Next</button>
                                </div>
                            </div>
                            <template v-if="lineItemForm.inventory_item_id">
                                <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-3">Line details</p>
                                    <div class="space-y-3">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Name</label>
                                            <input v-model="lineItemForm.name" type="text" class="input-style">
                                        </div>
                                        <div class="grid grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Qty</label>
                                                <input v-model.number="lineItemForm.quantity" type="number" min="1" class="input-style">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Unit price</label>
                                                <input v-model.number="lineItemForm.unit_price" type="number" min="0" step="0.01" class="input-style">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Discount</label>
                                                <input v-model.number="lineItemForm.discount" type="number" min="0" step="0.01" class="input-style">
                                            </div>
                                            <div class="flex items-center gap-2 pt-6">
                                                <input id="p-tax" v-model="lineItemForm.taxable" type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700">
                                                <label for="p-tax" class="text-sm text-gray-700 dark:text-gray-300">Taxable</label>
                                            </div>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notes</label>
                                            <textarea v-model="lineItemForm.description" rows="2" class="input-style resize-none" />
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                        <div class="flex justify-end gap-2 px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/30">
                            <button type="button" class="px-4 py-2 text-sm font-medium border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300" @click="showInventoryModal = false">Cancel</button>
                            <button
                                type="button"
                                class="px-4 py-2 text-sm font-medium bg-blue-600 hover:bg-blue-700 text-white rounded-lg disabled:opacity-40 transition-colors"
                                :disabled="!lineItemForm.inventory_item_id"
                                @click="saveInventoryItem"
                            >
                                Save
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>
