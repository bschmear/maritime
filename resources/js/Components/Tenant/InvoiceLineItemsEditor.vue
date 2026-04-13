<script setup>
/**
 * Deal-style line items (assets + parts) for invoices. Mirrors TransactionForm behaviour:
 * separate add/edit modals, add-ons, taxable + tax rate from parent form.
 */
import { computed, onMounted, ref, watchEffect } from 'vue';
import AddonSelect from '@/Components/Tenant/AddonSelect.vue';

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
});

const normalizeTaxable = (v) => v !== false && v !== 0 && v !== '0' && v !== 'false';

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
    name: item.name ?? '',
    description: item.description ?? '',
    quantity: item.quantity ?? 1,
    unit_price: Number(item.unit_price) || 0,
    discount: Number(item.discount) || 0,
    position: item.position ?? 0,
    taxable: normalizeTaxable(item.taxable ?? true),
    addons: normalizeAddons(item.addons, isNew),
    transaction_item_id: item.transaction_item_id ?? item.id ?? null,
});

const lines = ref([]);

const applyItemRows = (src, { preserveIds = false } = {}) => {
    lines.value = [];
    const isNew = !preserveIds;
    (src || []).forEach((item) => {
        const base = normalizeItemBase(item, isNew);
        if (item.itemable_type === 'App\\Domain\\Asset\\Models\\Asset') {
            lines.value.push({
                kind: 'asset',
                ...base,
                itemable_type: item.itemable_type,
                itemable_id: item.itemable_id ?? null,
                asset_id: item.itemable_id ?? null,
                year: item.itemable?.year || item.year || '',
                make: item.itemable?.make?.display_name || item.make || '',
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
    });
};

onMounted(() => {
    applyItemRows(props.initialItems, { preserveIds: props.readonly });
});

const roundMoney = (n) => Math.round((Number(n) + Number.EPSILON) * 100) / 100;
const lineBaseTotal = (item) =>
    Math.max(0, Number(item.unit_price || 0) * Number(item.quantity || 1) - Number(item.discount || 0));
const addonPreTaxTotal = (a) => Number(a.price || 0) * Number(a.quantity || 1);
const lineAddonsTotal = (item) => (item.addons || []).reduce((s, a) => s + addonPreTaxTotal(a), 0);
const lineTotal = (item) => lineBaseTotal(item) + lineAddonsTotal(item);

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
const itemLineTaxTotal = (item) =>
    taxOnItemBase(item) + (item.addons || []).reduce((s, a) => s + taxOnAddon(a), 0);
const lineCoreTotalWithTax = (item) => lineBaseTotal(item) + taxOnItemBase(item);

const computedSubtotal = computed(() => lines.value.reduce((s, i) => s + lineTotal(i), 0));
const computedLineItemsTax = computed(() => lines.value.reduce((s, i) => s + itemLineTaxTotal(i), 0));
const allItemsCount = computed(() => lines.value.length);

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
const assetSearchQuery = ref('');
const assetRecords = ref([]);
const assetCurrentPage = ref(1);
const assetTotalPages = ref(1);
const assetIsLoading = ref(false);
const emptyAssetForm = () => ({
    kind: 'asset',
    itemable_type: 'App\\Domain\\Asset\\Models\\Asset',
    itemable_id: null,
    asset_id: null,
    name: '',
    year: '',
    make: '',
    quantity: 1,
    unit_price: 0,
    discount: 0,
    description: '',
    taxable: true,
    addons: [],
    transaction_item_id: null,
});
const assetForm = ref(emptyAssetForm());

const fetchAssets = async (resetPage = false) => {
    if (resetPage) assetCurrentPage.value = 1;
    assetIsLoading.value = true;
    try {
        const url = new URL(route('records.lookup'), window.location.origin);
        url.searchParams.append('type', 'Asset');
        url.searchParams.append('page', assetCurrentPage.value);
        url.searchParams.append('per_page', '10');
        if (assetSearchQuery.value.trim()) url.searchParams.append('search', assetSearchQuery.value.trim());
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
        assetRecords.value = data.records || data.data || [];
        assetTotalPages.value = data.meta?.last_page || 1;
    } catch (e) {
        console.error(e);
        assetRecords.value = [];
    } finally {
        assetIsLoading.value = false;
    }
};
const debouncedFetchAssets = debounce(() => fetchAssets(true), 300);

const openAddAssetModal = () => {
    editingLineIndex.value = null;
    assetForm.value = emptyAssetForm();
    assetSearchQuery.value = '';
    assetCurrentPage.value = 1;
    fetchAssets(true);
    showAssetModal.value = true;
};
const openEditLineModal = (index) => {
    const row = lines.value[index];
    editingLineIndex.value = index;
    if (row.kind === 'asset') {
        assetForm.value = {
            ...emptyAssetForm(),
            ...row,
            addons: [...(row.addons || [])],
        };
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
const selectAsset = (asset) => {
    assetForm.value.itemable_id = asset.id;
    assetForm.value.asset_id = asset.id;
    assetForm.value.name = asset.display_name;
    assetForm.value.year = asset.year || '';
    assetForm.value.make = asset.make?.display_name || '';
    assetForm.value.unit_price = Number(asset.default_price) || 0;
};
const saveAssetItem = () => {
    if (!assetForm.value.itemable_id) return;
    const row = { ...assetForm.value, kind: 'asset' };
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
    discount: 0,
    description: '',
    taxable: true,
    addons: [],
    transaction_item_id: null,
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

const kindLabel = (row) => {
    if (row.kind === 'asset') return 'Asset';
    if (row.kind === 'inventory') return 'Part';
    return 'Line';
};

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
            transaction_item_id: line.transaction_item_id || null,
            itemable_type: line.itemable_type || null,
            itemable_id: line.itemable_id || null,
            name: line.name,
            description: line.description || null,
            quantity: Number(line.quantity) || 1,
            unit_price: Number(line.unit_price) || 0,
            discount: Number(line.discount) || 0,
            position: pos++,
            taxable: !!line.taxable,
            tax_rate: line.taxable ? rate : 0,
        });
        (line.addons || []).forEach((a) => {
            out.push({
                transaction_item_id: null,
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
    });
    return out;
}
</script>

<template>
    <div class="border-t border-gray-200 dark:border-gray-700 pt-6 space-y-8">
        <div class="flex items-center justify-between border-b pb-2 border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wide">
                Line items
            </h3>
            <div v-if="!readonly" class="flex gap-2">
                <button
                    type="button"
                    class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-900/20 rounded-lg"
                    @click="openAddAssetModal"
                >
                    <span class="material-icons text-base">add_circle</span>
                    Add asset
                </button>
                <button
                    type="button"
                    class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-900/20 rounded-lg"
                    @click="openAddInventoryModal"
                >
                    <span class="material-icons text-base">add_circle</span>
                    Add part
                </button>
            </div>
        </div>

        <div
            v-if="lines.length === 0"
            class="text-center py-10 rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-600 text-sm text-gray-500"
        >
            No line items yet. Add assets or parts, or select a deal to load its lines.
        </div>

        <div v-else class="overflow-x-auto -mx-2 sm:mx-0">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900/50">
                    <tr>
                        <th class="px-3 py-2 text-left font-medium text-gray-500">Type</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-500">Item</th>
                        <th class="px-3 py-2 text-right font-medium text-gray-500">Qty</th>
                        <th class="px-3 py-2 text-right font-medium text-gray-500">Price</th>
                        <th class="px-3 py-2 text-right font-medium text-gray-500">Disc.</th>
                        <th class="px-3 py-2 text-center font-medium text-gray-500">Tax</th>
                        <th class="px-3 py-2 text-right font-medium text-gray-500">Pre-tax</th>
                        <th class="px-3 py-2 text-right font-medium text-gray-500">Tax $</th>
                        <th class="px-3 py-2 text-right font-medium text-gray-500">Total</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-500">Add-ons</th>
                        <th v-if="!readonly" class="px-3 py-2 w-16"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <template v-for="(line, idx) in lines" :key="`ln-${idx}`">
                        <tr class="bg-white dark:bg-gray-800">
                            <td class="px-3 py-2">
                                <span class="rounded bg-gray-100 dark:bg-gray-700 px-2 py-0.5 text-xs font-medium">
                                    {{ kindLabel(line) }}
                                </span>
                            </td>
                            <td class="px-3 py-2">
                                <div class="font-medium text-gray-900 dark:text-white">{{ line.name }}</div>
                                <div v-if="line.kind === 'asset' && (line.year || line.make)" class="text-xs text-gray-500">
                                    {{ [line.year, line.make].filter(Boolean).join(' ') }}
                                </div>
                                <div v-if="line.sku" class="text-xs text-gray-500">SKU: {{ line.sku }}</div>
                                <div v-if="line.description" class="text-xs text-gray-500">{{ line.description }}</div>
                            </td>
                            <td class="px-3 py-2 text-right">{{ +line.quantity }}</td>
                            <td class="px-3 py-2 text-right">{{ formatMoney(line.unit_price) }}</td>
                            <td class="px-3 py-2 text-right">{{ formatMoney(line.discount) }}</td>
                            <td class="px-3 py-2 text-center">
                                <input
                                    v-if="!readonly"
                                    v-model="line.taxable"
                                    type="checkbox"
                                    class="rounded border-gray-300 text-primary-600"
                                >
                                <span v-else class="text-xs">{{ line.taxable ? 'Y' : 'N' }}</span>
                            </td>
                            <td class="px-3 py-2 text-right">{{ formatMoney(lineBaseTotal(line)) }}</td>
                            <td class="px-3 py-2 text-right">{{ formatMoney(taxOnItemBase(line)) }}</td>
                            <td class="px-3 py-2 text-right font-semibold">{{ formatMoney(lineCoreTotalWithTax(line)) }}</td>
                            <td class="px-3 py-2">
                                <button
                                    v-if="!readonly"
                                    type="button"
                                    class="text-primary-600 text-xs hover:underline"
                                    @click="openAddonModal(line)"
                                >
                                    + Add-ons ({{ (line.addons || []).length }})
                                </button>
                                <span v-else class="text-xs text-gray-400">{{ (line.addons || []).length }}</span>
                            </td>
                            <td v-if="!readonly" class="px-3 py-2 text-right">
                                <button type="button" class="text-primary-600 p-1" @click="openEditLineModal(idx)">
                                    <span class="material-icons text-base">edit</span>
                                </button>
                                <button type="button" class="text-red-600 p-1" @click="removeLine(idx)">
                                    <span class="material-icons text-base">delete</span>
                                </button>
                            </td>
                        </tr>
                        <tr
                            v-for="(addon, aidx) in (line.addons || [])"
                            :key="`ad-${idx}-${aidx}`"
                            class="bg-primary-50/30 dark:bg-primary-900/10"
                        >
                            <td class="px-3 py-1"></td>
                            <td class="px-3 py-1 pl-6 text-xs italic text-gray-600 dark:text-gray-400">
                                ↳ {{ addon.name || 'Add-on' }}
                            </td>
                            <td class="px-3 py-1 text-right text-xs">{{ addon.quantity }}</td>
                            <td class="px-3 py-1 text-right">
                                <input
                                    v-if="!readonly && isAddonEditing(idx, aidx)"
                                    v-model.number="addon.price"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="input-style w-24 py-0.5 text-right text-xs"
                                >
                                <span v-else class="text-xs">{{ formatMoney(addon.price) }}</span>
                            </td>
                            <td class="px-3 py-1 text-right text-xs">—</td>
                            <td class="px-3 py-1 text-center">
                                <input
                                    v-if="!readonly && isAddonEditing(idx, aidx)"
                                    v-model="addon.taxable"
                                    type="checkbox"
                                    class="rounded border-gray-300 text-primary-600"
                                >
                                <span v-else class="text-xs">{{ addon.taxable ? 'Y' : 'N' }}</span>
                            </td>
                            <td class="px-3 py-1 text-right text-xs">{{ formatMoney(addonPreTaxTotal(addon)) }}</td>
                            <td class="px-3 py-1 text-right text-xs">{{ formatMoney(taxOnAddon(addon)) }}</td>
                            <td class="px-3 py-1 text-right text-xs font-medium">
                                {{ formatMoney(addonPreTaxTotal(addon) + taxOnAddon(addon)) }}
                            </td>
                            <td class="px-3 py-1"></td>
                            <td v-if="!readonly" class="px-3 py-1 text-right">
                                <button type="button" class="text-primary-600 p-0.5" @click="toggleAddonEdit(idx, aidx, addon)">
                                    <span class="material-icons text-sm">{{ isAddonEditing(idx, aidx) ? 'check' : 'edit' }}</span>
                                </button>
                                <button type="button" class="text-red-600 p-0.5" @click="removeAddon(line, aidx)">
                                    <span class="material-icons text-sm">close</span>
                                </button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
            <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-3">Invoice totals</h4>
            <div class="max-w-sm ml-auto space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-gray-500">Subtotal (lines)</span>
                    <span class="font-medium">{{ formatMoney(form.subtotal) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Tax ({{ Number(form.tax_rate) || 0 }}%)</span>
                    <span class="font-medium">{{ formatMoney(form.tax_total) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-gray-500">Fees</span>
                    <input
                        v-if="!readonly"
                        v-model.number="form.fees_total"
                        type="number"
                        min="0"
                        step="0.01"
                        class="input-style w-28 py-1 text-right text-sm"
                    >
                    <span v-else>{{ formatMoney(form.fees_total) }}</span>
                </div>
                <div class="flex justify-between text-lg font-bold border-t pt-2">
                    <span>Total</span>
                    <span>{{ formatMoney(form.total) }}</span>
                </div>
            </div>
            <p v-if="!readonly" class="mt-2 text-xs text-gray-500 text-right">
                Set tax rate below; line tax uses this rate for taxable rows.
            </p>
        </div>

        <div v-if="!readonly" class="flex flex-wrap gap-4 items-end">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Tax rate (%)</label>
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

        <AddonSelect v-model:open="showAddonModal" accent="primary" @picked="onAddonPicked" />

        <!-- Asset modal -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition ease-out duration-200"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition ease-in duration-150"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="showAssetModal" class="fixed inset-0 z-50 flex items-start justify-center pt-16 px-4 pb-8">
                    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="showAssetModal = false" />
                    <div class="relative bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-2xl max-h-[80vh] flex flex-col">
                        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                {{ editingLineIndex !== null ? 'Edit asset line' : 'Add asset' }}
                            </h3>
                            <button type="button" class="text-gray-400 hover:text-gray-600 p-1" @click="showAssetModal = false">
                                <span class="material-icons">close</span>
                            </button>
                        </div>
                        <div class="overflow-y-auto p-6 space-y-4 flex-1">
                            <div v-if="editingLineIndex === null">
                                <label class="block text-sm font-medium mb-1">Search assets</label>
                                <input v-model="assetSearchQuery" type="text" class="input-style" @input="debouncedFetchAssets">
                                <div v-if="assetIsLoading" class="text-xs text-gray-500 mt-2">Loading…</div>
                                <div
                                    v-else-if="assetRecords.length"
                                    class="mt-2 border border-gray-200 dark:border-gray-600 rounded-lg divide-y max-h-48 overflow-y-auto"
                                >
                                    <button
                                        v-for="asset in assetRecords"
                                        :key="asset.id"
                                        type="button"
                                        class="w-full px-4 py-2 text-left text-sm hover:bg-gray-50 dark:hover:bg-gray-700"
                                        @click="selectAsset(asset)"
                                    >
                                        {{ asset.display_name }}
                                        <span v-if="asset.year" class="text-gray-400 text-xs ml-2">{{ asset.year }}</span>
                                    </button>
                                </div>
                                <div v-if="assetTotalPages > 1" class="flex justify-end gap-2 mt-2">
                                    <button
                                        type="button"
                                        class="text-xs px-2 py-1 border rounded"
                                        :disabled="assetCurrentPage <= 1"
                                        @click="assetCurrentPage--; fetchAssets()"
                                    >
                                        Prev
                                    </button>
                                    <button
                                        type="button"
                                        class="text-xs px-2 py-1 border rounded"
                                        :disabled="assetCurrentPage >= assetTotalPages"
                                        @click="assetCurrentPage++; fetchAssets()"
                                    >
                                        Next
                                    </button>
                                </div>
                            </div>
                            <template v-if="assetForm.asset_id">
                                <div>
                                    <label class="block text-sm font-medium mb-1">Name</label>
                                    <input v-model="assetForm.name" type="text" class="input-style">
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium mb-1">Qty</label>
                                        <input v-model.number="assetForm.quantity" type="number" min="1" class="input-style">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium mb-1">Unit price</label>
                                        <input v-model.number="assetForm.unit_price" type="number" min="0" step="0.01" class="input-style">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium mb-1">Discount</label>
                                        <input v-model.number="assetForm.discount" type="number" min="0" step="0.01" class="input-style">
                                    </div>
                                    <div class="flex items-center gap-2 pt-6">
                                        <input id="a-tax" v-model="assetForm.taxable" type="checkbox" class="rounded border-gray-300 text-primary-600">
                                        <label for="a-tax" class="text-sm">Taxable</label>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Notes</label>
                                    <textarea v-model="assetForm.description" rows="2" class="input-style resize-none" />
                                </div>
                            </template>
                        </div>
                        <div class="flex justify-end gap-2 px-6 py-4 border-t bg-gray-50 dark:bg-gray-700/30">
                            <button type="button" class="px-4 py-2 text-sm border rounded-lg" @click="showAssetModal = false">Cancel</button>
                            <button
                                type="button"
                                class="px-4 py-2 text-sm bg-primary-600 text-white rounded-lg disabled:opacity-40"
                                :disabled="!assetForm.asset_id"
                                @click="saveAssetItem"
                            >
                                Save
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

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
                                {{ editingLineIndex !== null ? 'Edit part line' : 'Add part' }}
                            </h3>
                            <button type="button" class="text-gray-400 p-1" @click="showInventoryModal = false">
                                <span class="material-icons">close</span>
                            </button>
                        </div>
                        <div class="overflow-y-auto p-6 space-y-4 flex-1">
                            <div v-if="editingLineIndex === null">
                                <label class="block text-sm font-medium mb-1">Search parts</label>
                                <input v-model="inventorySearchQuery" type="text" class="input-style" @input="debouncedFetchInventory">
                                <div v-if="inventoryIsLoading" class="text-xs text-gray-500 mt-2">Loading…</div>
                                <div
                                    v-else-if="inventoryRecords.length"
                                    class="mt-2 border rounded-lg divide-y max-h-48 overflow-y-auto"
                                >
                                    <button
                                        v-for="inv in inventoryRecords"
                                        :key="inv.id"
                                        type="button"
                                        class="w-full px-4 py-2 text-left text-sm hover:bg-gray-50 dark:hover:bg-gray-700"
                                        @click="selectInventoryItem(inv)"
                                    >
                                        {{ inv.display_name }}
                                        <span v-if="inv.sku" class="text-gray-400 text-xs ml-2">{{ inv.sku }}</span>
                                    </button>
                                </div>
                                <div v-if="inventoryTotalPages > 1" class="flex justify-end gap-2 mt-2">
                                    <button
                                        type="button"
                                        class="text-xs px-2 py-1 border rounded"
                                        :disabled="inventoryCurrentPage <= 1"
                                        @click="inventoryCurrentPage--; fetchInventoryItems()"
                                    >
                                        Prev
                                    </button>
                                    <button
                                        type="button"
                                        class="text-xs px-2 py-1 border rounded"
                                        :disabled="inventoryCurrentPage >= inventoryTotalPages"
                                        @click="inventoryCurrentPage++; fetchInventoryItems()"
                                    >
                                        Next
                                    </button>
                                </div>
                            </div>
                            <template v-if="lineItemForm.inventory_item_id">
                                <div>
                                    <label class="block text-sm font-medium mb-1">Name</label>
                                    <input v-model="lineItemForm.name" type="text" class="input-style">
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium mb-1">Qty</label>
                                        <input v-model.number="lineItemForm.quantity" type="number" min="1" class="input-style">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium mb-1">Unit price</label>
                                        <input v-model.number="lineItemForm.unit_price" type="number" min="0" step="0.01" class="input-style">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium mb-1">Discount</label>
                                        <input v-model.number="lineItemForm.discount" type="number" min="0" step="0.01" class="input-style">
                                    </div>
                                    <div class="flex items-center gap-2 pt-6">
                                        <input id="p-tax" v-model="lineItemForm.taxable" type="checkbox" class="rounded border-gray-300 text-primary-600">
                                        <label for="p-tax" class="text-sm">Taxable</label>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Notes</label>
                                    <textarea v-model="lineItemForm.description" rows="2" class="input-style resize-none" />
                                </div>
                            </template>
                        </div>
                        <div class="flex justify-end gap-2 px-6 py-4 border-t bg-gray-50 dark:bg-gray-700/30">
                            <button type="button" class="px-4 py-2 text-sm border rounded-lg" @click="showInventoryModal = false">Cancel</button>
                            <button
                                type="button"
                                class="px-4 py-2 text-sm bg-primary-600 text-white rounded-lg disabled:opacity-40"
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
