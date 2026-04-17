<script setup>
import { useForm } from '@inertiajs/vue3';
import RecordSelect from '@/Components/Tenant/RecordSelect.vue';
import AddonSelect from '@/Components/Tenant/AddonSelect.vue';
import AssetLineModal from '@/Components/Tenant/AssetLineModal.vue';
import AddressAutocomplete from '@/Components/AddressAutocomplete.vue';
import { useTaxRateByAddress } from '@/composables/useTaxRateByAddress';
import { computed, onMounted, ref, watch, watchEffect } from 'vue';

const debounce = (fn, delay) => {
    let timer;
    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), delay);
    };
};

const props = defineProps({
    record: { type: Object, default: null },
    formSchema: { type: Object, default: null },
    fieldsSchema: { type: Object, required: true },
    enumOptions: { type: Object, default: () => ({}) },
    account: { type: Object, default: null },
    timezones: { type: Array, default: () => [] },
    mode: {
        type: String,
        default: 'create',
        validator: (v) => ['create', 'edit', 'show'].includes(v),
    },
    initialData: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['cancel']);

// ─── Status helpers ───────────────────────────────────────────────────────────

const statusEnumKey = 'App\\Enums\\Transaction\\TransactionStatus';
const statusOptions = computed(() => props.enumOptions[statusEnumKey] || []);

const resolveStatusId = (raw) => {
    if (raw == null) return null;
    const byId = statusOptions.value.find((o) => o.id === raw);
    if (byId) return byId.id;
    const byValue = statusOptions.value.find((o) => o.value === raw);
    return byValue ? byValue.id : null;
};

const statusMetaFor = (idOrValue) =>
    statusOptions.value.find((o) => o.id === idOrValue || o.value === idOrValue) ?? {
        name: String(idOrValue || '—'),
        bgClass: 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
    };

// ─── Form ─────────────────────────────────────────────────────────────────────

const merged = { ...(props.record || {}), ...(props.initialData || {}) };

/** Must be defined before `useForm` (used for needs_contract / line items). */
const normalizeTaxable = (v) => v !== false && v !== 0 && v !== '0' && v !== 'false';

const form = useForm({
    customer_id: merged.customer_id ?? null,
    user_id: merged.user_id ?? null,
    estimate_id: merged.estimate_id ?? null,
    opportunity_id: merged.opportunity_id ?? null,
    subsidiary_id: merged.subsidiary_id ?? null,
    location_id: merged.location_id ?? null,
    status: resolveStatusId(merged.status) ?? statusOptions.value[0]?.id ?? null,
    title: merged.title ?? '',
    customer_name: merged.customer_name ?? '',
    customer_email: merged.customer_email ?? '',
    customer_phone: merged.customer_phone ?? '',
    subtotal: merged.subtotal ?? '',
    tax_total: merged.tax_total ?? '',
    total: merged.total ?? '',
    currency: String(merged.currency ?? 'USD')
        .trim()
        .toUpperCase() || 'USD',
    loss_reason_category: merged.loss_reason_category ?? '',
    loss_reason: merged.loss_reason ?? '',
    notes: merged.notes ?? '',
    billing_address_line1: merged.billing_address_line1 ?? '',
    billing_address_line2: merged.billing_address_line2 ?? '',
    billing_city: merged.billing_city ?? '',
    billing_state: merged.billing_state ?? '',
    billing_postal: merged.billing_postal ?? '',
    billing_country: merged.billing_country ?? '',
    billing_latitude: merged.billing_latitude ?? null,
    billing_longitude: merged.billing_longitude ?? null,
    tax_rate: merged.tax_rate ?? 0,
    tax_jurisdiction: merged.tax_jurisdiction ?? '',
    discount_total: merged.discount_total ?? '',
    fees_total: merged.fees_total ?? '',
    needs_contract: normalizeTaxable(merged.needs_contract ?? true),
    needs_delivery:
        merged.needs_delivery === true ||
        merged.needs_delivery === 1 ||
        merged.needs_delivery === '1' ||
        merged.needs_delivery === 'true',
});

/** Expand when multi-currency is supported. */
const supportedCurrencies = [{ code: 'USD', label: 'US Dollar (USD)' }];

const recordForSelect = computed(() =>
    props.record ?? (Object.keys(props.initialData).length > 0 ? props.initialData : null)
);

// ─── Billing address (same pattern as EstimateForm) ───────────────────────────

const { fetchTaxRate, isFetching: isFetchingTaxRate } = useTaxRateByAddress();

const applyAddressToForm = (src) => {
    form.billing_address_line1 = src.billing_address_line1 || src.address_line_1 || '';
    form.billing_address_line2 = src.billing_address_line2 || src.address_line_2 || '';
    form.billing_city = src.billing_city || src.city || '';
    form.billing_state = src.billing_state || src.state || '';
    form.billing_postal = src.billing_postal || src.postal_code || '';
    form.billing_country = src.billing_country || src.country || '';
    form.billing_latitude = src.billing_latitude ?? src.latitude ?? null;
    form.billing_longitude = src.billing_longitude ?? src.longitude ?? null;
};

const showAddressConfirm = ref(false);
const pendingCustomerAddress = ref(null);

const handleCustomerSelected = (customer) => {
    const street = customer.address_line_1 || customer.billing_address_line1 || '';
    if (!street) return;
    pendingCustomerAddress.value = customer;
    showAddressConfirm.value = true;
};

const confirmUseBillingAddress = () => {
    applyAddressToForm(pendingCustomerAddress.value);
    showAddressConfirm.value = false;
    pendingCustomerAddress.value = null;
};

const dismissAddressConfirm = () => {
    showAddressConfirm.value = false;
    pendingCustomerAddress.value = null;
};

/** Human-readable jurisdiction label from current billing fields (state required for lookup). */
const buildTaxJurisdictionFromBilling = () => {
    const city = (form.billing_city || '').trim();
    const st = (form.billing_state || '').trim();
    const pc = (form.billing_postal || '').trim();
    const country = (form.billing_country || '').trim();
    if (city && st) {
        return [city, st, pc].filter(Boolean).join(', ') + (country && country !== 'US' ? ` (${country})` : '');
    }
    if (st && pc) return `${st} ${pc}`;
    if (st) return country && country !== 'US' ? `${st} (${country})` : st;
    return '';
};

const handleAddressUpdate = (data) => {
    form.billing_address_line1 = data.street ?? '';
    form.billing_address_line2 = data.unit ?? '';
    form.billing_city = data.city ?? '';
    form.billing_state = data.stateCode || data.state || '';
    form.billing_postal = data.postalCode ?? '';
    form.billing_country = data.countryCode || data.country || '';
    form.billing_latitude = data.latitude ?? null;
    form.billing_longitude = data.longitude ?? null;
    if (data.stateCode || data.state) {
        form.tax_jurisdiction = buildTaxJurisdictionFromBilling();
    }
};

const findTaxRateFromBillingAddress = async () => {
    const state = (form.billing_state || '').trim();
    if (!state) {
        window.alert('Add a billing state (or complete the billing address) before looking up tax rate.');
        return;
    }
    const jurisdiction = buildTaxJurisdictionFromBilling();
    if (jurisdiction) {
        form.tax_jurisdiction = jurisdiction;
    }

    const rate = await fetchTaxRate({
        state,
        city: form.billing_city || undefined,
        postal_code: form.billing_postal || undefined,
        line1: form.billing_address_line1 || undefined,
        country: form.billing_country || undefined,
        latitude: form.billing_latitude ?? undefined,
        longitude: form.billing_longitude ?? undefined,
    });
    if (rate !== null) {
        form.tax_rate = rate;
    }
};

watch(() => form.billing_state, async (newState) => {
    if (!newState) return;
    await findTaxRateFromBillingAddress();
});

watch(() => form.subsidiary_id, (newVal, oldVal) => {
    if (oldVal === undefined) return;
    if (newVal !== oldVal) {
        form.location_id = null;
    }
});

// ─── Line items (assets + parts only in UI; legacy generic lines load into inventoryItems) ─

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

const normalizeItemBase = (item, isNew = false) => ({
    id: isNew ? null : (item.id ?? null),
    name: item.name ?? '',
    description: item.description ?? '',
    quantity: item.quantity ?? 1,
    unit_price: Number(item.unit_price) || 0,
    discount: Number(item.discount) || 0,
    position: item.position ?? 0,
    taxable: normalizeTaxable(item.taxable ?? true),
    addons: normalizeAddons(item.addons, isNew),
});

// ─── Asset items ──────────────────────────────────────────────────────────────
const assetItems = ref([]);
const showAssetModal = ref(false);
const editingAssetIndex = ref(null);

const emptyAssetForm = () => ({
    itemable_type: 'App\\Domain\\Asset\\Models\\Asset',
    itemable_id: null,
    asset_id: null,
    name: '',
    year: '',
    make: '',
    quantity: 1,
    unit_price: 0,
    discount: 0,
    notes: '',
    addons: [],
    has_variants: false,
    asset_variant_id: null,
    variant_display_name: '',
    asset_description: '',
    catalog_description: '',
    asset_unit_id: null,
    unit_display_name: '',
});
const assetForm = ref(emptyAssetForm());

// The stored row shape uses `description` and carries `taxable`; the shared modal
// uses `notes` and doesn't manage taxable. Bridge the two here.
const rowToModalForm = (row) => ({
    ...emptyAssetForm(),
    ...row,
    notes: row.description ?? '',
    addons: [...(row.addons || [])],
});

const modalFormToRow = (form, existing = {}) => {
    const { notes, catalog_description: _catalog, ...rest } = form;
    return {
        ...rest,
        description: notes ?? '',
        taxable: existing.taxable ?? true,
        addons: [...(form.addons || [])],
        id: existing.id ?? null,
    };
};

const openAddAssetModal = () => {
    editingAssetIndex.value = null;
    assetForm.value = emptyAssetForm();
    showAssetModal.value = true;
};
const openEditAssetModal = (index) => {
    editingAssetIndex.value = index;
    assetForm.value = rowToModalForm(assetItems.value[index]);
    showAssetModal.value = true;
};
const saveAssetItem = () => {
    const existing = editingAssetIndex.value !== null
        ? assetItems.value[editingAssetIndex.value]
        : {};
    const row = modalFormToRow(assetForm.value, existing);
    if (editingAssetIndex.value !== null) {
        assetItems.value[editingAssetIndex.value] = row;
    } else {
        assetItems.value.push(row);
    }
    showAssetModal.value = false;
};
const removeAssetItem = (index) => assetItems.value.splice(index, 1);

// ─── Inventory items ─────────────────────────────────────────────────────────
const inventoryItems = ref([]);
const showInventoryModal = ref(false);
const editingInventoryIndex = ref(null);
const inventorySearchQuery = ref('');
const inventoryRecords = ref([]);
const inventoryCurrentPage = ref(1);
const inventoryTotalPages = ref(1);
const inventoryIsLoading = ref(false);
const emptyLineItemForm = () => ({
    itemable_type: 'App\\Domain\\InventoryItem\\Models\\InventoryItem',
    itemable_id: null,
    inventory_item_id: null,
    name: '',
    sku: '',
    quantity: 1,
    unit_price: 0,
    discount: 0,
    description: '',
    taxable: true,
    addons: [],
});
const lineItemForm = ref(emptyLineItemForm());

const fetchInventoryItems = async (resetPage = false) => {
    if (resetPage) inventoryCurrentPage.value = 1;
    inventoryIsLoading.value = true;
    try {
        const url = new URL(route('records.lookup'), window.location.origin);
        url.searchParams.append('type', 'InventoryItem');
        url.searchParams.append('page', inventoryCurrentPage.value);
        url.searchParams.append('per_page', 10);
        if (inventorySearchQuery.value.trim()) url.searchParams.append('search', inventorySearchQuery.value.trim());
        const res = await fetch(url.toString(), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '' },
            credentials: 'same-origin',
        });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();
        inventoryRecords.value = data.records || data.data || [];
        inventoryTotalPages.value = data.meta?.last_page || 1;
    } catch (err) {
        console.error('Failed to fetch inventory items:', err);
        inventoryRecords.value = [];
    } finally {
        inventoryIsLoading.value = false;
    }
};
const debouncedFetchInventory = debounce(() => fetchInventoryItems(true), 300);

const openAddInventoryModal = () => {
    editingInventoryIndex.value = null;
    lineItemForm.value = emptyLineItemForm();
    inventorySearchQuery.value = '';
    inventoryCurrentPage.value = 1;
    fetchInventoryItems(true);
    showInventoryModal.value = true;
};
const openEditInventoryModal = (index) => {
    editingInventoryIndex.value = index;
    lineItemForm.value = { ...inventoryItems.value[index], addons: [...(inventoryItems.value[index].addons || [])] };
    showInventoryModal.value = true;
};
const selectInventoryItem = (inv) => {
    lineItemForm.value.itemable_id = inv.id;
    lineItemForm.value.inventory_item_id = inv.id;
    lineItemForm.value.name = inv.display_name;
    lineItemForm.value.sku = inv.sku || '';
    lineItemForm.value.unit_price = Number(inv.default_price) || 0;
};
const saveInventoryItem = () => {
    if (!lineItemForm.value.itemable_id) return;
    if (editingInventoryIndex.value !== null) {
        inventoryItems.value[editingInventoryIndex.value] = { ...lineItemForm.value };
    } else {
        inventoryItems.value.push({ ...lineItemForm.value });
    }
    showInventoryModal.value = false;
};
const removeInventoryItem = (index) => inventoryItems.value.splice(index, 1);

onMounted(() => {
    const src = props.record?.items?.length
        ? props.record.items
        : (Array.isArray(props.initialData?.items) ? props.initialData.items : []);
    const isNew = !props.record?.items?.length;

    src.forEach((item) => {
        const base = normalizeItemBase(item, isNew);
        if (item.itemable_type === 'App\\Domain\\Asset\\Models\\Asset') {
            const variantId = item.asset_variant_id
                ?? item.estimate_line_item?.asset_variant_id
                ?? null;
            const variantDisplayName = item.asset_variant?.display_name
                ?? item.assetVariant?.display_name
                ?? item.estimate_line_item?.asset_variant?.display_name
                ?? '';
            const unitId = item.asset_unit_id
                ?? item.estimate_line_item?.asset_unit_id
                ?? null;
            const unitDisplayName = item.asset_unit?.display_name
                ?? item.assetUnit?.display_name
                ?? item.estimate_line_item?.asset_unit?.display_name
                ?? '';
            assetItems.value.push({
                ...base,
                itemable_type: item.itemable_type,
                itemable_id: item.itemable_id ?? null,
                asset_id: item.itemable_id ?? null,
                year: item.itemable?.year || item.year || '',
                make: item.itemable?.make?.display_name || item.make || '',
                has_variants: Boolean(item.itemable?.has_variants || variantId),
                asset_description: (item.itemable?.description || '').trim() || '',
                asset_variant_id: variantId,
                variant_display_name: variantDisplayName,
                asset_unit_id: unitId,
                unit_display_name: unitDisplayName,
            });
        } else if (item.itemable_type === 'App\\Domain\\InventoryItem\\Models\\InventoryItem') {
            inventoryItems.value.push({
                ...base,
                itemable_type: item.itemable_type,
                itemable_id: item.itemable_id ?? null,
                inventory_item_id: item.itemable_id ?? null,
                sku: item.itemable?.sku || item.sku || '',
            });
        } else {
            // Legacy generic line items (no itemable): editable under Parts & Accessories
            inventoryItems.value.push({
                ...base,
                itemable_type: null,
                itemable_id: null,
                inventory_item_id: null,
                sku: '',
                _txnLineType: 'line',
            });
        }
    });
});

// ─── Totals ───────────────────────────────────────────────────────────────────

const roundMoney = (n) => Math.round((Number(n) + Number.EPSILON) * 100) / 100;

// Works for all item types; discount defaults to 0 for generic items.
const lineBaseTotal = (item) => Math.max(0, Number(item.unit_price || 0) * Number(item.quantity || 1) - Number(item.discount || 0));
const lineAddonsTotal = (item) => (item.addons || []).reduce((s, a) => s + Number(a.price || 0) * Number(a.quantity || 1), 0);
const lineTotal = (item) => lineBaseTotal(item) + lineAddonsTotal(item);

const dealTaxRatePercent = () => Number(form.tax_rate) || 0;
const addonPreTaxTotal = (a) => Number(a.price || 0) * Number(a.quantity || 1);

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
const itemLineTaxTotal = (item) =>
    taxOnItemBase(item) + (item.addons || []).reduce((s, a) => s + taxOnAddon(a), 0);

/** Main line row “Total” only: base line + tax on base. Add-ons are separate rows and must not be included here. */
const lineCoreTotalWithTax = (item) => lineBaseTotal(item) + taxOnItemBase(item);

// Per-section subtotals (table footers)
const computedAssetSubtotal = computed(() => assetItems.value.reduce((s, i) => s + lineTotal(i), 0));
const computedAssetTax = computed(() => assetItems.value.reduce((s, i) => s + itemLineTaxTotal(i), 0));
const computedInventorySubtotal = computed(() => inventoryItems.value.reduce((s, i) => s + lineTotal(i), 0));
const computedInventoryTax = computed(() => inventoryItems.value.reduce((s, i) => s + itemLineTaxTotal(i), 0));

// Combined (used for deal-total sync)
const computedSubtotal = computed(() => computedAssetSubtotal.value + computedInventorySubtotal.value);
const computedLineItemsTax = computed(() => computedAssetTax.value + computedInventoryTax.value);

const allItemsCount = computed(() => assetItems.value.length + inventoryItems.value.length);

// Keep subtotal / tax_total / total in sync across all item types
watchEffect(() => {
    const hasItems = allItemsCount.value > 0;
    let sub = hasItems ? computedSubtotal.value : Number(form.subtotal || 0);
    if (hasItems) {
        const nextSub = sub.toFixed(2);
        const nextTax = computedLineItemsTax.value.toFixed(2);
        if (String(form.subtotal) !== nextSub) form.subtotal = nextSub;
        if (String(form.tax_total) !== nextTax) form.tax_total = nextTax;
    }
    sub = hasItems ? computedSubtotal.value : Number(form.subtotal || 0);
    const tax = Number(form.tax_total || 0);
    const disc = Number(form.discount_total || 0);
    const fees = Number(form.fees_total || 0);
    const nextTotal = (sub + tax - disc + fees).toFixed(2);
    if (String(form.total) !== nextTotal) form.total = nextTotal;
});

// ─── Add-on picker (AddonSelect → saved catalog rows + create via Form) ────────

const showAddonModal = ref(false);
const currentAddonTargetItem = ref(null);

const openAddonModal = (lineItem) => {
    currentAddonTargetItem.value = lineItem;
    showAddonModal.value = true;
};

const onTransactionAddonPicked = (payload) => {
    if (!currentAddonTargetItem.value) return;
    if (!currentAddonTargetItem.value.addons) currentAddonTargetItem.value.addons = [];
    currentAddonTargetItem.value.addons.push(payload);
};

/** Which transaction line add-on row is in edit mode (`asset-0-1` / `inventory-2-0`). */
const editingTransactionAddonKey = ref(null);

const transactionAddonEditKey = (section, lineIndex, addonIndex) =>
    `${section}-${lineIndex}-${addonIndex}`;

const isTransactionAddonEditing = (section, lineIndex, addonIndex) =>
    editingTransactionAddonKey.value === transactionAddonEditKey(section, lineIndex, addonIndex);

/** Toggle edit for price + taxable on this line only (does not change the catalog add-on). */
const toggleTransactionAddonEdit = (section, lineIndex, addonIndex, addon) => {
    const key = transactionAddonEditKey(section, lineIndex, addonIndex);
    if (editingTransactionAddonKey.value === key) {
        addon.price = Number(addon.price) || 0;
        editingTransactionAddonKey.value = null;
    } else {
        editingTransactionAddonKey.value = key;
    }
};

const removeAddon = (item, addonIdx) => {
    if (item.addons) item.addons.splice(addonIdx, 1);
    editingTransactionAddonKey.value = null;
};

// ─── Computed / helpers ───────────────────────────────────────────────────────

const currentStatusMeta = computed(() => statusMetaFor(form.status));
const isLostOrCancelled = computed(() => {
    const opt = statusOptions.value.find((o) => o.id === form.status);
    return opt && (opt.value === 'lost' || opt.value === 'cancelled');
});

const getRecordOptions = (fieldKey) => props.enumOptions[fieldKey] || [];

const formatMoney = (value, currency) => {
    if (value == null || value === '') return '—';
    const n = parseFloat(value);
    if (isNaN(n)) return '—';
    try {
        return new Intl.NumberFormat('en-US', { style: 'currency', currency: currency || form.currency || 'USD' }).format(n);
    } catch {
        return `${currency || 'USD'} ${n.toFixed(2)}`;
    }
};

const formatDateTime = (value) => {
    if (!value) return '—';
    try {
        return new Date(value).toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
    } catch { return '—'; }
};

// ─── Submit ───────────────────────────────────────────────────────────────────

const numOrNull = (v) => (v === '' || v === null || v === undefined ? null : Number(v));

const submit = () => {
    form.transform((data) => ({
        ...data,
        discount_total: numOrNull(data.discount_total),
        fees_total: numOrNull(data.fees_total),
        tax_rate: data.tax_rate === '' || data.tax_rate == null ? null : Number(data.tax_rate),
        billing_latitude: data.billing_latitude === '' || data.billing_latitude == null ? null : Number(data.billing_latitude),
        billing_longitude: data.billing_longitude === '' || data.billing_longitude == null ? null : Number(data.billing_longitude),
        items: (() => {
            const mapAddons = (addons) => (addons || []).map((a) => ({
                addon_id: a.addon_id || null,
                name: a.name || null,
                price: Number(a.price) || 0,
                quantity: Number(a.quantity) || 1,
                notes: a.notes || null,
                taxable: !!a.taxable,
            }));
            const all = [];
            assetItems.value.forEach((item, idx) => all.push({
                id: item.id || null,
                type: 'asset',
                itemable_type: item.itemable_type,
                itemable_id: item.itemable_id || null,
                asset_variant_id: item.asset_variant_id || null,
                asset_unit_id: item.asset_unit_id || null,
                name: item.name,
                description: item.description || null,
                quantity: Number(item.quantity) || 1,
                unit_price: Number(item.unit_price) || 0,
                discount: Number(item.discount) || 0,
                position: idx,
                taxable: !!item.taxable,
                addons: mapAddons(item.addons),
            }));
            inventoryItems.value.forEach((item, idx) => {
                const legacyLine = item._txnLineType === 'line';
                all.push({
                    id: item.id || null,
                    type: legacyLine ? 'line' : 'inventory',
                    itemable_type: legacyLine ? null : item.itemable_type,
                    itemable_id: legacyLine ? null : (item.itemable_id || null),
                    name: item.name,
                    description: item.description || null,
                    quantity: Number(item.quantity) || 1,
                    unit_price: Number(item.unit_price) || 0,
                    discount: Number(item.discount) || 0,
                    position: assetItems.value.length + idx,
                    taxable: !!item.taxable,
                    addons: mapAddons(item.addons),
                });
            });
            return all;
        })(),
    }));

    if (props.mode === 'edit') {
        form.put(route('transactions.update', props.record.id), {
            onSuccess: () => { window.location.href = route('transactions.show', props.record.id); },
        });
    } else {
        form.post(route('transactions.store'));
    }
};

const handleCancel = () => emit('cancel');
</script>

<template>
    <div class="w-full flex flex-col space-y-4 md:space-y-6">
        <form @submit.prevent="submit">
            <div class="grid gap-4 lg:gap-6 lg:grid-cols-12">

                <!-- ─── MAIN COLUMN ─── -->
                <div :class="[mode !== 'show' ? 'lg:col-span-9' : 'lg:col-span-12', 'space-y-6']">

                    <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden">

                        <!-- Blue header — matches ServiceTicket style -->
                        <div class="bg-gradient-to-r from-blue-600 to-blue-700 dark:from-blue-700 dark:to-blue-800 px-6 py-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h1 class="text-2xl font-bold text-white">
                                        {{ mode === 'edit' ? 'EDIT TRANSACTION' : 'NEW TRANSACTION' }}
                                    </h1>
                                    <p class="text-blue-100 text-sm mt-1">Deal Record</p>
                                </div>
                                <div class="text-right">
                                    <div class="text-blue-200 text-sm font-medium">Deal #</div>
                                    <div class="text-white text-lg font-mono">{{ record?.sequence || 'Auto-generated' }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="p-6 space-y-6">

                            <!-- Title + status -->
                            <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                                <div class="flex-1">
                                    <input
                                        v-if="mode !== 'show'"
                                        v-model="form.title"
                                        type="text"
                                        placeholder="Deal title  (e.g. '2024 Sea Ray Sale')"
                                        class="input-style text-base font-medium"
                                    />
                                    <p v-else class="text-lg font-semibold text-gray-900 dark:text-white">
                                        {{ record?.title || `Deal #${record?.sequence}` }}
                                    </p>
                                    <p v-if="form.errors.title" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.title }}</p>
                                </div>
                                <span class="inline-flex shrink-0 items-center rounded-full px-3 py-1 text-sm font-medium" :class="currentStatusMeta.bgClass">
                                    {{ currentStatusMeta.name }}
                                </span>
                            </div>

                            <!-- ─── Customer & Relations ─────────────────────────────── -->
                            <div class="border-gray-200 dark:border-gray-700 pt-6">
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700 mb-4">
                                    Customer &amp; Relations
                                </h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Customer <span class="text-red-500">*</span>
                                        </label>
                                        <RecordSelect
                                            v-if="mode !== 'show'"
                                            id="customer_id"
                                            :field="fieldsSchema.customer_id"
                                            v-model="form.customer_id"
                                            :enum-options="getRecordOptions('customer_id')"
                                            :record="recordForSelect"
                                            field-key="customer_id"
                                            @record-selected="handleCustomerSelected"
                                        />
                                        <p v-else class="text-sm text-gray-900 dark:text-white">{{ record?.customer?.display_name || '—' }}</p>
                                        <p v-if="form.errors.customer_id" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.customer_id }}</p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Salesperson <span class="text-red-500">*</span>
                                        </label>
                                        <RecordSelect
                                            v-if="mode !== 'show'"
                                            id="user_id"
                                            :field="fieldsSchema.user_id"
                                            v-model="form.user_id"
                                            :enum-options="getRecordOptions('user_id')"
                                            :record="recordForSelect"
                                            field-key="user_id"
                                        />
                                        <p v-else class="text-sm text-gray-900 dark:text-white">{{ record?.user?.display_name || '—' }}</p>
                                        <p v-if="form.errors.user_id" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.user_id }}</p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Estimate</label>
                                        <RecordSelect
                                            v-if="mode !== 'show'"
                                            id="estimate_id"
                                            :field="fieldsSchema.estimate_id"
                                            v-model="form.estimate_id"
                                            :enum-options="getRecordOptions('estimate_id')"
                                            :record="recordForSelect"
                                            field-key="estimate_id"
                                        />
                                        <p v-else class="text-sm text-gray-900 dark:text-white">{{ record?.estimate?.display_name || '—' }}</p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Opportunity</label>
                                        <RecordSelect
                                            v-if="mode !== 'show'"
                                            id="opportunity_id"
                                            :field="fieldsSchema.opportunity_id"
                                            v-model="form.opportunity_id"
                                            :enum-options="getRecordOptions('opportunity_id')"
                                            :record="recordForSelect"
                                            field-key="opportunity_id"
                                        />
                                        <p v-else class="text-sm text-gray-900 dark:text-white">{{ record?.opportunity?.display_name || '—' }}</p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            {{ fieldsSchema.subsidiary_id?.label || 'Subsidiary' }}
                                        </label>
                                        <RecordSelect
                                            v-if="mode !== 'show'"
                                            id="subsidiary_id"
                                            :field="fieldsSchema.subsidiary_id"
                                            v-model="form.subsidiary_id"
                                            :enum-options="getRecordOptions('subsidiary_id')"
                                            :record="recordForSelect"
                                            field-key="subsidiary_id"
                                        />
                                        <p v-else class="text-sm text-gray-900 dark:text-white">{{ record?.subsidiary?.display_name || '—' }}</p>
                                        <p v-if="form.errors.subsidiary_id" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.subsidiary_id }}</p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            {{ fieldsSchema.location_id?.label || 'Location' }}
                                        </label>
                                        <RecordSelect
                                            v-if="mode !== 'show'"
                                            id="location_id"
                                            :field="fieldsSchema.location_id"
                                            v-model="form.location_id"
                                            :enum-options="getRecordOptions('location_id')"
                                            :record="recordForSelect"
                                            field-key="location_id"
                                            filter-by="subsidiary_id"
                                            :filter-value="form.subsidiary_id"
                                            :disabled="!form.subsidiary_id"
                                        />
                                        <p v-else class="text-sm text-gray-900 dark:text-white">{{ record?.location?.display_name || '—' }}</p>
                                        <p v-if="form.errors.location_id" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.location_id }}</p>
                                        <p v-if="mode !== 'show' && !form.subsidiary_id" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            Select a subsidiary to choose a location.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- ─── Next steps (contract / delivery) ─────────────────── -->
                            <div class="border-gray-200 dark:border-gray-700 pt-6">
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700 mb-4">
                                    Next steps
                                </h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4 -mt-2">
                                    Track what still needs to happen for this deal (stored on the transaction only).
                                </p>
                                <div class="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-center">
                                    <label
                                        class="inline-flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-gray-300"
                                    >
                                        <input
                                            v-if="mode !== 'show'"
                                            v-model="form.needs_contract"
                                            type="checkbox"
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700"
                                        />
                                        <span class="font-medium">{{ fieldsSchema.needs_contract?.label || 'Needs contract' }}</span>
                                        <span v-if="mode === 'show'" class="text-gray-600 dark:text-gray-400">
                                            — {{ record?.needs_contract ? 'Yes' : 'No' }}
                                        </span>
                                    </label>
                                    <label
                                        class="inline-flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-gray-300"
                                    >
                                        <input
                                            v-if="mode !== 'show'"
                                            v-model="form.needs_delivery"
                                            type="checkbox"
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700"
                                        />
                                        <span class="font-medium">{{ fieldsSchema.needs_delivery?.label || 'Needs delivery' }}</span>
                                        <span v-if="mode === 'show'" class="text-gray-600 dark:text-gray-400">
                                            — {{ record?.needs_delivery ? 'Yes' : 'No' }}
                                        </span>
                                    </label>
                                </div>
                            </div>

                            <!-- ─── Customer Snapshot ────────────────────────────────── -->
                            <div class="border-gray-200 dark:border-gray-700 pt-6">
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700 mb-4">
                                    Customer Snapshot
                                </h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4 -mt-2">Preserved at time of deal creation</p>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Name</label>
                                        <input v-if="mode !== 'show'" v-model="form.customer_name" type="text" placeholder="Full name" class="input-style" />
                                        <p v-else class="text-sm text-gray-900 dark:text-white">{{ record?.customer_name || '—' }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Email</label>
                                        <input v-if="mode !== 'show'" v-model="form.customer_email" type="email" placeholder="email@example.com" class="input-style" />
                                        <p v-else class="text-sm text-gray-900 dark:text-white">{{ record?.customer_email || '—' }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Phone</label>
                                        <input v-if="mode !== 'show'" v-model="form.customer_phone" type="tel" placeholder="+1 (555) 000-0000" class="input-style" />
                                        <p v-else class="text-sm text-gray-900 dark:text-white">{{ record?.customer_phone || '—' }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- ─── Billing address (EstimateForm pattern) ───────────── -->
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                                <div class="flex items-center justify-between mb-3">
                                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wide">
                                        Billing Address
                                    </h3>
                                    <span v-if="isFetchingTaxRate" class="text-xs text-blue-600 dark:text-blue-400 animate-pulse">
                                        Fetching tax rate…
                                    </span>
                                </div>
                                <AddressAutocomplete
                                    v-if="mode !== 'show'"
                                    :street="form.billing_address_line1"
                                    :unit="form.billing_address_line2"
                                    :city="form.billing_city"
                                    :state="form.billing_state"
                                    :stateCode="form.billing_state"
                                    :postalCode="form.billing_postal"
                                    :country="form.billing_country"
                                    :latitude="form.billing_latitude"
                                    :longitude="form.billing_longitude"
                                    @update="handleAddressUpdate"
                                />
                                <div
                                    v-else-if="record?.billing_address_line1 || record?.billing_city"
                                    class="text-sm text-gray-900 dark:text-gray-100 space-y-0.5"
                                >
                                    <div v-if="record.billing_address_line1">{{ record.billing_address_line1 }}</div>
                                    <div v-if="record.billing_address_line2">{{ record.billing_address_line2 }}</div>
                                    <div v-if="record.billing_city || record.billing_state || record.billing_postal">
                                        {{ [record.billing_city, record.billing_state, record.billing_postal].filter(Boolean).join(', ') }}
                                    </div>
                                    <div v-if="record.billing_country">{{ record.billing_country }}</div>
                                </div>
                                <p v-else class="text-sm text-gray-500 dark:text-gray-400">—</p>
                            </div>

                            <!-- ─── Tax rate & jurisdiction ──────────────────────────── -->
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700 mb-4">
                                    Tax
                                </h3>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Tax rate (%)</label>
                                        <input
                                            v-if="mode !== 'show'"
                                            v-model.number="form.tax_rate"
                                            type="number"
                                            step="0.001"
                                            min="0"
                                            max="100"
                                            class="input-style"
                                            placeholder="0.000"
                                        />
                                        <p v-else class="text-sm text-gray-900 dark:text-white">{{ record?.tax_rate != null ? record.tax_rate : '—' }}%</p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Tax jurisdiction</label>
                                        <input
                                            v-if="mode !== 'show'"
                                            v-model="form.tax_jurisdiction"
                                            type="text"
                                            class="input-style"
                                            placeholder="e.g. state or locality code"
                                        />
                                        <p v-else class="text-sm text-gray-900 dark:text-white">{{ record?.tax_jurisdiction || '—' }}</p>
                                    </div>
                                </div>
                                <div v-if="mode !== 'show'" class="mt-4">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-2 rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                                        :disabled="!form.billing_state?.trim() || isFetchingTaxRate"
                                        @click="findTaxRateFromBillingAddress"
                                    >
                                        <span v-if="isFetchingTaxRate" class="inline-block size-4 animate-spin rounded-full border-2 border-gray-400 border-t-transparent dark:border-gray-500" aria-hidden="true" />
                                        {{ isFetchingTaxRate ? 'Looking up…' : 'Find tax rate from billing address' }}
                                    </button>
                                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                        Fills tax jurisdiction from the billing address above and sets the rate when a match is found.
                                    </p>
                                </div>
                            </div>

                            <!-- ─── Notes ────────────────────────────────────────────── -->
                            <div class="border-gray-200 dark:border-gray-700 pt-6">
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700 mb-4">
                                    Notes
                                </h3>
                                <textarea
                                    v-if="mode !== 'show'"
                                    v-model="form.notes"
                                    rows="4"
                                    placeholder="Add any notes about this deal…"
                                    class="input-style resize-none"
                                ></textarea>
                                <p v-else class="text-sm text-gray-900 dark:text-white whitespace-pre-line leading-relaxed">
                                    {{ record?.notes || '—' }}
                                </p>
                            </div>

<!-- ─── Assets ────────────────────────────────────────── -->
<div class="border-gray-200 dark:border-gray-700 pt-6">
    <div class="flex items-center justify-between border-b pb-2 border-gray-200 dark:border-gray-700 mb-4">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wide">Assets</h3>
        <button v-if="mode !== 'show'" type="button" @click="openAddAssetModal"
            class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 bg-blue-50 dark:bg-blue-900/20 hover:bg-blue-100 dark:hover:bg-blue-900/30 rounded-lg transition-colors">
            <span class="material-icons text-base">add_circle</span>
            Add Asset
        </button>
    </div>

    <div v-if="assetItems.length === 0" class="text-center py-12 bg-gray-50 dark:bg-gray-900/20 rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-700 mt-2">
        <span class="material-icons text-5xl text-gray-400 dark:text-gray-600 mb-3 block">inventory_2</span>
        <p class="text-sm text-gray-500 dark:text-gray-400">No assets added yet</p>
        <p v-if="mode !== 'show'" class="text-sm text-gray-400 dark:text-gray-500 mt-1">Click "Add Asset" to get started</p>
    </div>
    <div v-else class="overflow-x-auto -mx-6 sm:mx-0">
        <div class="inline-block min-w-full align-middle">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Asset</th>
                        <th class="px-4 py-3 text-right text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-20">Qty</th>
                        <th class="px-4 py-3 text-right text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-28">Unit Price</th>
                        <th class="px-4 py-3 text-right text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-28">Discount</th>
                        <th class="px-4 py-3 text-center text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-20">Taxable</th>
                        <th class="px-4 py-3 text-right text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-28">Pre-tax</th>
                        <th class="px-4 py-3 text-right text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-24">Tax</th>
                        <th class="px-4 py-3 text-right text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-28">Total</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Add-ons</th>
                        <th v-if="mode !== 'show'" class="px-4 py-3 w-20"></th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    <template v-for="(asset, index) in assetItems" :key="`asset-${index}`">
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-900 dark:text-white text-sm">{{ asset.name }}</div>
                                <div v-if="asset.year || asset.make" class="text-sm text-gray-400 dark:text-gray-500 mt-0.5">{{ [asset.year, asset.make].filter(Boolean).join(' ') }}</div>
                                <div v-if="asset.variant_display_name" class="flex items-center gap-1 mt-0.5">
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300">Variant</span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ asset.variant_display_name }}</span>
                                </div>
                                <div v-if="asset.asset_unit_id" class="flex items-center gap-1 mt-0.5">
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">Unit</span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ (asset.unit_display_name || '').split(' - ').slice(1).join(' - ') || asset.unit_display_name || `#${asset.asset_unit_id}` }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right text-sm text-gray-700 dark:text-gray-300">{{ +asset.quantity }}</td>
                            <td class="px-4 py-3 text-right text-sm text-gray-700 dark:text-gray-300">{{ formatMoney(asset.unit_price) }}</td>
                            <td class="px-4 py-3 text-right text-sm text-gray-700 dark:text-gray-300">{{ formatMoney(asset.discount) }}</td>
                            <td class="px-4 py-3 text-center align-middle">
                                <input v-if="mode !== 'show'" v-model="asset.taxable" type="checkbox"
                                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700"
                                    title="Tax applies to this asset at deal tax rate" />
                                <span v-else class="text-xs text-gray-500 dark:text-gray-400">{{ asset.taxable ? 'Yes' : 'No' }}</span>
                            </td>
                            <td class="px-4 py-3 text-right text-sm font-medium text-gray-900 dark:text-white">{{ formatMoney(lineBaseTotal(asset)) }}</td>
                            <td class="px-4 py-3 text-right text-sm text-gray-600 dark:text-gray-300">{{ formatMoney(taxOnItemBase(asset)) }}</td>
                            <td class="px-4 py-3 text-right text-sm font-semibold text-gray-900 dark:text-white">{{ formatMoney(lineCoreTotalWithTax(asset)) }}</td>
                            <td class="px-4 py-3">
                                <button
                                    v-if="mode !== 'show'"
                                    type="button"
                                    class="inline-flex items-center gap-1 text-sm text-blue-600 dark:text-blue-400 hover:underline"
                                    @click="openAddonModal(asset)"
                                >
                                    <span class="material-icons text-sm">add_circle_outline</span>
                                    Add-ons ({{ (asset.addons || []).length }})
                                </button>
                                <span v-else class="text-sm text-gray-400">{{ (asset.addons || []).length }} add-on(s)</span>
                            </td>
                            <td v-if="mode !== 'show'" class="px-4 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    <button type="button" @click="openEditAssetModal(index)" class="text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300">
                                        <span class="material-icons text-base">edit</span>
                                    </button>
                                    <button type="button" @click="removeAssetItem(index)" class="text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300">
                                        <span class="material-icons text-base">delete</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr
                            v-for="(addon, addonIdx) in (asset.addons || [])"
                            :key="`asset-addon-${index}-${addonIdx}`"
                            class="bg-blue-50/30 dark:bg-blue-900/10"
                        >
                            <td class="pl-10 pr-4 py-2 text-sm text-gray-600 dark:text-gray-400 italic">
                                ↳ {{ addon.name || 'Add-on' }}
                                <span v-if="addon.notes" class="block text-gray-400 dark:text-gray-500 not-italic">{{ addon.notes }}</span>
                            </td>
                            <td class="px-4 py-2 text-right text-sm text-gray-400">{{ addon.quantity }}</td>
                            <td class="px-4 py-2 text-right">
                                <input
                                    v-if="mode !== 'show' && isTransactionAddonEditing('asset', index, addonIdx)"
                                    v-model.number="addon.price"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="input-style w-28 py-1 text-right text-sm"
                                    title="Unit price for this transaction line only"
                                />
                                <span v-else class="text-sm text-gray-500 dark:text-gray-400">{{ formatMoney(addon.price) }}</span>
                            </td>
                            <td class="px-4 py-2 text-right text-sm text-gray-400">—</td>
                            <td class="px-4 py-2 text-center">
                                <input
                                    v-if="mode !== 'show' && isTransactionAddonEditing('asset', index, addonIdx)"
                                    v-model="addon.taxable"
                                    type="checkbox"
                                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700"
                                    title="Tax applies to this add-on on this deal"
                                />
                                <span v-else class="text-xs text-gray-500 dark:text-gray-400">{{ addon.taxable ? 'Yes' : 'No' }}</span>
                            </td>
                            <td class="px-4 py-2 text-right text-sm font-medium text-gray-700 dark:text-gray-300">{{ formatMoney(addonPreTaxTotal(addon)) }}</td>
                            <td class="px-4 py-2 text-right text-sm text-gray-600 dark:text-gray-400">{{ formatMoney(taxOnAddon(addon)) }}</td>
                            <td class="px-4 py-2 text-right text-sm font-medium text-gray-800 dark:text-gray-200">{{ formatMoney(addonPreTaxTotal(addon) + taxOnAddon(addon)) }}</td>
                            <td></td>
                            <td v-if="mode !== 'show'" class="px-4 py-2 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <button
                                        type="button"
                                        :title="isTransactionAddonEditing('asset', index, addonIdx) ? 'Done editing' : 'Edit price and taxable'"
                                        class="text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 p-1"
                                        @click="toggleTransactionAddonEdit('asset', index, addonIdx, addon)"
                                    >
                                        <span class="material-icons text-base">{{
                                            isTransactionAddonEditing('asset', index, addonIdx) ? 'check' : 'edit'
                                        }}</span>
                                    </button>
                                    <button
                                        type="button"
                                        title="Remove add-on from this line"
                                        class="text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300 p-1"
                                        @click="removeAddon(asset, addonIdx)"
                                    >
                                        <span class="material-icons text-base">close</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
                <tfoot class="bg-gray-50 dark:bg-gray-900/50 border-t-2 border-gray-200 dark:border-gray-600">
                    <tr>
                        <td colspan="5" class="px-4 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">Assets subtotal (pre-tax)</td>
                        <td class="px-4 py-3 text-right text-base font-bold text-gray-900 dark:text-white">{{ formatMoney(computedAssetSubtotal) }}</td>
                        <td class="px-4 py-3 text-right text-base font-bold text-gray-900 dark:text-white">{{ formatMoney(computedAssetTax) }}</td>
                        <td class="px-4 py-3 text-right text-base font-bold text-gray-900 dark:text-white">{{ formatMoney(computedAssetSubtotal + computedAssetTax) }}</td>
                        <td></td>
                        <td v-if="mode !== 'show'"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<!-- ─── Parts & Accessories ─────────────────────────────────── -->
<div class="border-gray-200 dark:border-gray-700 pt-6">
    <div class="flex items-center justify-between border-b pb-2 border-gray-200 dark:border-gray-700 mb-4">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wide">Parts &amp; Accessories</h3>
        <button v-if="mode !== 'show'" type="button" @click="openAddInventoryModal"
            class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 bg-blue-50 dark:bg-blue-900/20 hover:bg-blue-100 dark:hover:bg-blue-900/30 rounded-lg transition-colors">
            <span class="material-icons text-base">add_circle</span>
            Add Part
        </button>
    </div>

    <div v-if="inventoryItems.length === 0" class="text-center py-12 bg-gray-50 dark:bg-gray-900/20 rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-700 mt-2">
        <span class="material-icons text-5xl text-gray-400 dark:text-gray-600 mb-3 block">handyman</span>
        <p class="text-sm text-gray-500 dark:text-gray-400">No parts or accessories added yet</p>
        <p v-if="mode !== 'show'" class="text-sm text-gray-400 dark:text-gray-500 mt-1">Click "Add Part" to get started</p>
    </div>
    <div v-else class="overflow-x-auto -mx-6 sm:mx-0">
        <div class="inline-block min-w-full align-middle">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Item</th>
                        <th class="px-4 py-3 text-right text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-20">Qty</th>
                        <th class="px-4 py-3 text-right text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-28">Unit Price</th>
                        <th class="px-4 py-3 text-right text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-28">Discount</th>
                        <th class="px-4 py-3 text-center text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-20">Taxable</th>
                        <th class="px-4 py-3 text-right text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-28">Pre-tax</th>
                        <th class="px-4 py-3 text-right text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-24">Tax</th>
                        <th class="px-4 py-3 text-right text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-28">Total</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Add-ons</th>
                        <th v-if="mode !== 'show'" class="px-4 py-3 w-20"></th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    <template v-for="(inv, index) in inventoryItems" :key="`inv-${index}`">
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-900 dark:text-white text-sm">{{ inv.name }}</div>
                                <span v-if="inv._txnLineType === 'line'" class="inline-block mt-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-400 bg-amber-100 dark:bg-amber-900/40 px-1.5 py-0.5 rounded">Legacy line</span>
                                <div v-if="inv.sku" class="text-sm text-gray-400 dark:text-gray-500 mt-0.5">SKU: {{ inv.sku }}</div>
                                <!-- <div v-if="inv.description" class="text-sm text-gray-400 dark:text-gray-500 mt-0.5">{{ inv.description }}</div> -->
                            </td>
                            <td class="px-4 py-3 text-right text-sm text-gray-700 dark:text-gray-300">{{ +inv.quantity }}</td>
                            <td class="px-4 py-3 text-right text-sm text-gray-700 dark:text-gray-300">{{ formatMoney(inv.unit_price) }}</td>
                            <td class="px-4 py-3 text-right text-sm text-gray-700 dark:text-gray-300">{{ formatMoney(inv.discount) }}</td>
                            <td class="px-4 py-3 text-center align-middle">
                                <input v-if="mode !== 'show'" v-model="inv.taxable" type="checkbox"
                                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700"
                                    title="Tax applies to this item at deal tax rate" />
                                <span v-else class="text-xs text-gray-500 dark:text-gray-400">{{ inv.taxable ? 'Yes' : 'No' }}</span>
                            </td>
                            <td class="px-4 py-3 text-right text-sm font-medium text-gray-900 dark:text-white">{{ formatMoney(lineBaseTotal(inv)) }}</td>
                            <td class="px-4 py-3 text-right text-sm text-gray-600 dark:text-gray-300">{{ formatMoney(taxOnItemBase(inv)) }}</td>
                            <td class="px-4 py-3 text-right text-sm font-semibold text-gray-900 dark:text-white">{{ formatMoney(lineCoreTotalWithTax(inv)) }}</td>
                            <td class="px-4 py-3">
                                <button
                                    v-if="mode !== 'show'"
                                    type="button"
                                    class="inline-flex items-center gap-1 text-sm text-blue-600 dark:text-blue-400 hover:underline"
                                    @click="openAddonModal(inv)"
                                >
                                    <span class="material-icons text-sm">add_circle_outline</span>
                                    Add-ons ({{ (inv.addons || []).length }})
                                </button>
                                <span v-else class="text-sm text-gray-400">{{ (inv.addons || []).length }} add-on(s)</span>
                            </td>
                            <td v-if="mode !== 'show'" class="px-4 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    <button type="button" @click="openEditInventoryModal(index)" class="text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300">
                                        <span class="material-icons text-base">edit</span>
                                    </button>
                                    <button type="button" @click="removeInventoryItem(index)" class="text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300">
                                        <span class="material-icons text-base">delete</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr
                            v-for="(addon, addonIdx) in (inv.addons || [])"
                            :key="`inv-addon-${index}-${addonIdx}`"
                            class="bg-blue-50/30 dark:bg-blue-900/10"
                        >
                            <td class="pl-10 pr-4 py-2 text-sm text-gray-600 dark:text-gray-400 italic">
                                ↳ {{ addon.name || 'Add-on' }}
                                <span v-if="addon.notes" class="block text-gray-400 dark:text-gray-500 not-italic">{{ addon.notes }}</span>
                            </td>
                            <td class="px-4 py-2 text-right text-sm text-gray-400">{{ addon.quantity }}</td>
                            <td class="px-4 py-2 text-right">
                                <input
                                    v-if="mode !== 'show' && isTransactionAddonEditing('inventory', index, addonIdx)"
                                    v-model.number="addon.price"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="input-style w-28 py-1 text-right text-sm"
                                    title="Unit price for this transaction line only"
                                />
                                <span v-else class="text-sm text-gray-500 dark:text-gray-400">{{ formatMoney(addon.price) }}</span>
                            </td>
                            <td class="px-4 py-2 text-right text-sm text-gray-400">—</td>
                            <td class="px-4 py-2 text-center">
                                <input
                                    v-if="mode !== 'show' && isTransactionAddonEditing('inventory', index, addonIdx)"
                                    v-model="addon.taxable"
                                    type="checkbox"
                                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700"
                                    title="Tax applies to this add-on on this deal"
                                />
                                <span v-else class="text-xs text-gray-500 dark:text-gray-400">{{ addon.taxable ? 'Yes' : 'No' }}</span>
                            </td>
                            <td class="px-4 py-2 text-right text-sm font-medium text-gray-700 dark:text-gray-300">{{ formatMoney(addonPreTaxTotal(addon)) }}</td>
                            <td class="px-4 py-2 text-right text-sm text-gray-600 dark:text-gray-400">{{ formatMoney(taxOnAddon(addon)) }}</td>
                            <td class="px-4 py-2 text-right text-sm font-medium text-gray-800 dark:text-gray-200">{{ formatMoney(addonPreTaxTotal(addon) + taxOnAddon(addon)) }}</td>
                            <td></td>
                            <td v-if="mode !== 'show'" class="px-4 py-2 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <button
                                        type="button"
                                        :title="isTransactionAddonEditing('inventory', index, addonIdx) ? 'Done editing' : 'Edit price and taxable'"
                                        class="text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 p-1"
                                        @click="toggleTransactionAddonEdit('inventory', index, addonIdx, addon)"
                                    >
                                        <span class="material-icons text-base">{{
                                            isTransactionAddonEditing('inventory', index, addonIdx) ? 'check' : 'edit'
                                        }}</span>
                                    </button>
                                    <button
                                        type="button"
                                        title="Remove add-on from this line"
                                        class="text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300 p-1"
                                        @click="removeAddon(inv, addonIdx)"
                                    >
                                        <span class="material-icons text-base">close</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
                <tfoot class="bg-gray-50 dark:bg-gray-900/50 border-t-2 border-gray-200 dark:border-gray-600">
                    <tr>
                        <td colspan="5" class="px-4 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">Parts subtotal (pre-tax)</td>
                        <td class="px-4 py-3 text-right text-base font-bold text-gray-900 dark:text-white">{{ formatMoney(computedInventorySubtotal) }}</td>
                        <td class="px-4 py-3 text-right text-base font-bold text-gray-900 dark:text-white">{{ formatMoney(computedInventoryTax) }}</td>
                        <td class="px-4 py-3 text-right text-base font-bold text-gray-900 dark:text-white">{{ formatMoney(computedInventorySubtotal + computedInventoryTax) }}</td>
                        <td></td>
                        <td v-if="mode !== 'show'"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

                            <!-- ─── Deal Totals ──────────────────────────────────────── -->
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700 mb-6">
                                    Deal Total
                                </h3>
                                <div class="max-w-sm ml-auto space-y-3 text-sm text-gray-900 dark:text-white">
                                    <div class="flex justify-between">
                                        <span class="text-gray-500 dark:text-gray-400">Subtotal</span>
                                        <span v-if="allItemsCount > 0" class="font-medium">{{ formatMoney(computedSubtotal) }}</span>
                                        <div v-else class="relative">
                                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">$</span>
                                            <input v-model="form.subtotal" type="number" min="0" step="0.01" class="input-style pl-7 w-32 text-right text-sm py-1" />
                                        </div>
                                    </div>
                                    <div class="flex justify-between items-center text-sm">
                                        <span class="text-gray-500 dark:text-gray-400">Tax ({{ Number(form.tax_rate) || 0 }}%)</span>
                                        <div v-if="allItemsCount === 0" class="relative">
                                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">$</span>
                                            <input v-model="form.tax_total" type="number" min="0" step="0.01" class="input-style pl-7 w-28 text-right text-sm py-1" />
                                        </div>
                                        <span v-else class="text-gray-700 dark:text-gray-300 font-medium">{{ formatMoney(form.tax_total) }}</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-gray-500 dark:text-gray-400">Discount</span>
                                        <div class="relative">
                                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">$</span>
                                            <input v-model="form.discount_total" type="number" min="0" step="0.01" class="input-style pl-7 w-28 text-right text-sm py-1" />
                                        </div>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-gray-500 dark:text-gray-400">Fees</span>
                                        <div class="relative">
                                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">$</span>
                                            <input v-model="form.fees_total" type="number" min="0" step="0.01" class="input-style pl-7 w-28 text-right text-sm py-1" />
                                        </div>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-gray-500 dark:text-gray-400">Currency</span>
                                        <select
                                            v-if="mode !== 'show'"
                                            v-model="form.currency"
                                            class="input-style w-28 font-mono text-sm py-1"
                                        >
                                            <option v-for="c in supportedCurrencies" :key="c.code" :value="c.code">
                                                {{ c.label }}
                                            </option>
                                        </select>
                                        <span v-else class="font-mono text-sm font-medium">{{ record?.currency || 'USD' }}</span>
                                    </div>
                                    <div class="flex justify-between text-2xl font-bold border-t border-gray-200 dark:border-gray-600 pt-4 mt-2">
                                        <span>Total</span>
                                        <span v-if="allItemsCount > 0">{{ formatMoney(form.total) }}</span>
                                        <div v-else class="relative">
                                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">$</span>
                                            <input v-model="form.total" type="number" min="0" step="0.01" class="input-style pl-8 w-36 text-right font-bold" />
                                        </div>
                                    </div>
                                </div>
                            </div>



                            <!-- ─── Loss Tracking ─────────────────────────────────────── -->
                            <div v-if="isLostOrCancelled || record?.loss_reason || record?.loss_reason_category" class="border-t border-red-200 dark:border-red-800 pt-6">
                                <h3 class="text-sm font-semibold text-red-700 dark:text-red-300 uppercase tracking-wide border-b border-red-200 dark:border-red-800 pb-2 mb-4">
                                    Loss Tracking
                                </h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Loss Reason Category</label>
                                        <input v-if="mode !== 'show'" v-model="form.loss_reason_category" type="text" placeholder="e.g. Price, Competition, Timing" class="input-style" />
                                        <p v-else class="text-sm text-gray-900 dark:text-white">{{ record?.loss_reason_category || '—' }}</p>
                                    </div>
                                    <div class="space-y-2 text-sm">
                                        <div v-if="record?.closed_at" class="flex justify-between">
                                            <span class="text-gray-500 dark:text-gray-400">Closed</span>
                                            <span class="text-gray-900 dark:text-white">{{ formatDateTime(record.closed_at) }}</span>
                                        </div>
                                        <div v-if="record?.won_at" class="flex justify-between">
                                            <span class="text-gray-500 dark:text-gray-400">Won</span>
                                            <span class="font-medium text-green-600 dark:text-green-400">{{ formatDateTime(record.won_at) }}</span>
                                        </div>
                                        <div v-if="record?.lost_at" class="flex justify-between">
                                            <span class="text-gray-500 dark:text-gray-400">Lost</span>
                                            <span class="font-medium text-red-600 dark:text-red-400">{{ formatDateTime(record.lost_at) }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Loss Reason</label>
                                    <textarea v-if="mode !== 'show'" v-model="form.loss_reason" rows="3" placeholder="Describe why the deal was lost…" class="input-style resize-none"></textarea>
                                    <p v-else class="text-sm text-gray-900 dark:text-white whitespace-pre-line leading-relaxed">{{ record?.loss_reason || '—' }}</p>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>



                <!-- ─── SIDEBAR ─── -->
                <div v-if="mode !== 'show'" class="lg:col-span-3 w-full">
                    <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden sticky top-[140px]">

                        <div class="flex justify-between items-center p-4 sm:px-5 font-semibold text-gray-900 bg-gray-100 dark:text-white dark:bg-gray-700">
                            Actions
                        </div>

                        <div class="p-4 sm:p-5 space-y-6">

                            <!-- Status -->
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">Status</label>
                                <select v-model.number="form.status" class="input-style">
                                    <option v-for="opt in statusOptions" :key="opt.id" :value="opt.id">{{ opt.name }}</option>
                                </select>
                                <!-- Key dates (edit mode) -->
                                <div v-if="record" class="mt-4 space-y-2 text-sm border-t border-gray-200 dark:border-gray-700 pt-3">
                                    <div v-if="record.created_at" class="flex justify-between">
                                        <span class="text-gray-500 dark:text-gray-400">Created</span>
                                        <span class="text-gray-900 dark:text-white text-right">{{ formatDateTime(record.created_at) }}</span>
                                    </div>
                                    <div v-if="record.won_at" class="flex justify-between">
                                        <span class="text-gray-500 dark:text-gray-400">Won</span>
                                        <span class="font-medium text-green-600 dark:text-green-400">{{ formatDateTime(record.won_at) }}</span>
                                    </div>
                                    <div v-if="record.lost_at" class="flex justify-between">
                                        <span class="text-gray-500 dark:text-gray-400">Lost</span>
                                        <span class="font-medium text-red-600 dark:text-red-400">{{ formatDateTime(record.lost_at) }}</span>
                                    </div>
                                    <div v-if="record.closed_at" class="flex justify-between">
                                        <span class="text-gray-500 dark:text-gray-400">Closed</span>
                                        <span class="text-gray-900 dark:text-white">{{ formatDateTime(record.closed_at) }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Validation errors -->
                            <div v-if="Object.keys(form.errors).length > 0" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-3">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="material-icons text-red-600 dark:text-red-400 text-sm">error</span>
                                    <span class="text-sm font-medium text-red-800 dark:text-red-200">Please fix the following errors:</span>
                                </div>
                                <ul class="list-disc list-inside text-sm text-red-700 dark:text-red-300 space-y-1">
                                    <li v-for="(error, key) in form.errors" :key="key">{{ error }}</li>
                                </ul>
                            </div>

                            <!-- Save / cancel -->
                            <div class="space-y-3 pt-2 border-t border-gray-200 dark:border-gray-700">
                                <button
                                    type="button"
                                    :disabled="form.processing"
                                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors"
                                    @click="submit"
                                >
                                    <span v-if="form.processing" class="material-icons text-sm animate-spin">refresh</span>
                                    <span v-else class="material-icons text-sm">check_circle</span>
                                    {{ form.processing ? 'Saving…' : mode === 'edit' ? 'Save Changes' : 'Create Transaction' }}
                                </button>
                                <button
                                    type="button"
                                    :disabled="form.processing"
                                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 rounded-lg transition-colors"
                                    @click="handleCancel"
                                >
                                    <span class="material-icons text-sm">close</span>
                                    Cancel
                                </button>
                            </div>

                        </div>
                    </div>
                </div>

            </div>
        </form>

        <AddonSelect v-model:open="showAddonModal" accent="blue" @picked="onTransactionAddonPicked" />

        <Teleport to="body">
            <Transition
                enter-active-class="transition ease-out duration-150"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition ease-in duration-100"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="showAddressConfirm && pendingCustomerAddress"
                    class="fixed inset-0 z-[60] flex items-center justify-center bg-black/40 backdrop-blur-sm p-4"
                    @click.self="dismissAddressConfirm"
                >
                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden">
                        <div class="flex items-start gap-3 px-6 pt-6 pb-4">
                            <div class="flex-shrink-0 w-9 h-9 rounded-full bg-blue-100 dark:bg-blue-900/40 flex items-center justify-center">
                                <span class="material-icons text-blue-600 dark:text-blue-400 text-lg">place</span>
                            </div>
                            <div>
                                <h3 class="text-md font-semibold text-gray-900 dark:text-white">Use customer&apos;s address?</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Set this as the billing address for this deal</p>
                            </div>
                        </div>
                        <div class="mx-6 mb-5 px-3 py-2.5 bg-gray-50 dark:bg-gray-700/50 rounded-lg border border-gray-200 dark:border-gray-600 text-sm text-gray-700 dark:text-gray-300 space-y-0.5">
                            <p v-if="pendingCustomerAddress.address_line_1" class="font-medium">{{ pendingCustomerAddress.address_line_1 }}</p>
                            <p v-if="pendingCustomerAddress.address_line_2" class="text-gray-500 dark:text-gray-400">{{ pendingCustomerAddress.address_line_2 }}</p>
                            <p>
                                <span v-if="pendingCustomerAddress.city">{{ pendingCustomerAddress.city }}, </span>
                                <span v-if="pendingCustomerAddress.state">{{ pendingCustomerAddress.state }} </span>
                                <span v-if="pendingCustomerAddress.postal_code">{{ pendingCustomerAddress.postal_code }}</span>
                            </p>
                            <p v-if="pendingCustomerAddress.country" class="text-gray-500 dark:text-gray-400">{{ pendingCustomerAddress.country }}</p>
                        </div>
                        <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/30">
                            <button
                                type="button"
                                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors"
                                @click="dismissAddressConfirm"
                            >
                                No, keep blank
                            </button>
                            <button
                                type="button"
                                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors"
                                @click="confirmUseBillingAddress"
                            >
                                Yes, use this address
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- ─── Add/Edit Asset Modal ─────────────────────────────────────── -->
        <AssetLineModal
            v-model="assetForm"
            v-model:open="showAssetModal"
            :editing="editingAssetIndex !== null"
            @save="saveAssetItem"
        />

        <!-- ─── Add/Edit Inventory Item Modal ────────────────────────────── -->
        <Teleport to="body">
            <Transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="transition ease-in duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0">
                <div v-if="showInventoryModal" class="fixed inset-0 z-50 flex items-start justify-center pt-16 px-4 pb-8">
                    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="showInventoryModal = false"></div>
                    <div class="relative bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-2xl max-h-[80vh] flex flex-col">
                        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ editingInventoryIndex !== null ? 'Edit Part / Accessory' : 'Add Part / Accessory' }}</h3>
                            <button type="button" @click="showInventoryModal = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 p-1 rounded">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                        <div class="overflow-y-auto p-6 space-y-5 flex-1">
                            <!-- Search -->
                            <div v-if="editingInventoryIndex === null">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Search Parts &amp; Accessories</label>
                                <input v-model="inventorySearchQuery" @input="debouncedFetchInventory" type="text" placeholder="Search by name, SKU…" class="input-style" />
                                <div v-if="inventoryIsLoading" class="mt-2 text-xs text-gray-500">Loading…</div>
                                <div v-else-if="inventoryRecords.length" class="mt-2 border border-gray-200 dark:border-gray-600 rounded-lg overflow-hidden divide-y divide-gray-100 dark:divide-gray-700">
                                    <button
                                        v-for="inv in inventoryRecords" :key="inv.id"
                                        type="button"
                                        @click="selectInventoryItem(inv)"
                                        class="w-full px-4 py-2.5 text-left text-sm hover:bg-blue-50 dark:hover:bg-blue-900/30 transition-colors"
                                        :class="lineItemForm.inventory_item_id === inv.id ? 'bg-blue-50 dark:bg-blue-900/20 font-semibold' : ''"
                                    >
                                        <span class="font-medium text-gray-900 dark:text-white">{{ inv.display_name }}</span>
                                        <span v-if="inv.sku" class="ml-2 text-gray-400 text-xs">SKU: {{ inv.sku }}</span>
                                    </button>
                                </div>
                                <div v-else-if="inventorySearchQuery" class="mt-2 text-xs text-gray-400">No items found.</div>
                                <div v-if="inventoryTotalPages > 1" class="mt-2 flex gap-2 justify-end">
                                    <button type="button" :disabled="inventoryCurrentPage <= 1" @click="inventoryCurrentPage--; fetchInventoryItems()" class="text-xs px-2 py-1 rounded border border-gray-300 dark:border-gray-600 disabled:opacity-40">Prev</button>
                                    <span class="text-xs text-gray-500 self-center">{{ inventoryCurrentPage }} / {{ inventoryTotalPages }}</span>
                                    <button type="button" :disabled="inventoryCurrentPage >= inventoryTotalPages" @click="inventoryCurrentPage++; fetchInventoryItems()" class="text-xs px-2 py-1 rounded border border-gray-300 dark:border-gray-600 disabled:opacity-40">Next</button>
                                </div>
                            </div>
                            <!-- Item fields -->
                            <div v-if="lineItemForm.inventory_item_id">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Name</label>
                                <input v-model="lineItemForm.name" type="text" class="input-style" />
                            </div>
                            <div v-if="lineItemForm.inventory_item_id" class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Quantity</label>
                                    <input v-model.number="lineItemForm.quantity" type="number" min="1" step="1" class="input-style" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Unit Price</label>
                                    <input v-model.number="lineItemForm.unit_price" type="number" min="0" step="0.01" class="input-style" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Discount</label>
                                    <input v-model.number="lineItemForm.discount" type="number" min="0" step="0.01" class="input-style" />
                                </div>
                                <div class="flex items-center gap-3 pt-5">
                                    <input id="inv-taxable" v-model="lineItemForm.taxable" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                                    <label for="inv-taxable" class="text-sm font-medium text-gray-700 dark:text-gray-300 cursor-pointer">Taxable</label>
                                </div>
                            </div>
                            <div v-if="lineItemForm.inventory_item_id">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notes</label>
                                <textarea v-model="lineItemForm.description" rows="2" class="input-style resize-none"></textarea>
                            </div>
                        </div>
                        <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/30">
                            <button type="button" @click="showInventoryModal = false" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">Cancel</button>
                            <button type="button" @click="saveInventoryItem" :disabled="!lineItemForm.inventory_item_id" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-40 rounded-lg transition-colors">
                                {{ editingInventoryIndex !== null ? 'Update' : 'Add Part' }}
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>
