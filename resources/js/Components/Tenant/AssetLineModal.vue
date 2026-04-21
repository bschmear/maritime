<script setup>
import { computed, ref, Teleport, watch } from 'vue';
import AssetLineVariantSelect from '@/Components/Tenant/AssetLineVariantSelect.vue';
import AssetLineUnitSelect from '@/Components/Tenant/AssetLineUnitSelect.vue';

/**
 * Reusable asset line-item modal. Usable from Estimate, Opportunity, Contract,
 * Transaction, Invoice, Delivery forms. The parent owns the asset line `model`
 * object (search, selection, and detail inputs all mutate it in place). When the
 * user clicks Save we emit `save`; the parent decides whether to push/update the
 * line-items array and close the modal.
 */

const open = defineModel('open', { type: Boolean, default: false });
const model = defineModel({
    type: Object,
    default: () => ({
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
        asset_unit_id: null,
        unit_display_name: '',
        asset_description: '',
        catalog_description: '',
    }),
});

const props = defineProps({
    /** When true, the "Change" asset button is hidden (we are editing an existing line). */
    editing: { type: Boolean, default: false },
    /** Delivery lines are always quantity 1 — hide the field and keep totals in sync. */
    hideQuantity: { type: Boolean, default: false },
    /**
     * Service ticket: pick catalog asset → variant → unit only; hide pricing, discounts, and notes.
     */
    pickAssetOnly: { type: Boolean, default: false },
    /**
     * Restrict serialized units to this customer's fleet OR unassigned (stock) units.
     */
    customerId: { type: [Number, String, null], default: null },
});

const emit = defineEmits(['save', 'close']);

const debounce = (fn, delay) => {
    let timer;
    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), delay);
    };
};

const searchQuery = ref('');
const records = ref([]);
const currentPage = ref(1);
const totalPages = ref(1);
const isLoading = ref(false);

const fetchAssets = async (resetPage = false) => {
    if (resetPage) currentPage.value = 1;
    isLoading.value = true;
    try {
        const url = new URL(route('records.lookup'), window.location.origin);
        url.searchParams.append('type', 'Asset');
        url.searchParams.append('page', currentPage.value);
        url.searchParams.append('per_page', 10);
        if (searchQuery.value.trim()) url.searchParams.append('search', searchQuery.value.trim());
        const response = await fetch(url.toString(), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            credentials: 'same-origin',
        });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        const data = await response.json();
        records.value = data.records || data.data || [];
        totalPages.value = data.meta?.last_page || 1;
    } catch (err) {
        console.error('Failed to fetch assets:', err);
        records.value = [];
    } finally {
        isLoading.value = false;
    }
};

const debouncedFetchAssets = debounce(() => fetchAssets(true), 300);

watch(open, (isOpen) => {
    if (!isOpen) return;
    if (!model.value?.itemable_id) {
        searchQuery.value = '';
        currentPage.value = 1;
        fetchAssets(true);
    }
});

const selectAsset = (asset) => {
    model.value.itemable_id = asset.id;
    model.value.asset_id = asset.id;
    model.value.name = asset.display_name;
    model.value.year = asset.year || '';
    model.value.make = asset.make?.display_name || '';
    model.value.unit_price = Number(asset.default_price) || 0;
    model.value.has_variants = Boolean(asset.has_variants);
    model.value.asset_variant_id = null;
    model.value.variant_display_name = '';
    model.value.asset_unit_id = null;
    model.value.unit_display_name = '';
    model.value.asset_description = (asset.description || '').trim() || '';
    model.value.catalog_description = '';
    if (!model.value.has_variants) {
        model.value.catalog_description = model.value.asset_description || '';
    }
};

const clearSelectedAssetForChange = () => {
    model.value.itemable_id = null;
    model.value.asset_id = null;
    model.value.name = '';
    model.value.has_variants = false;
    model.value.asset_variant_id = null;
    model.value.variant_display_name = '';
    model.value.asset_unit_id = null;
    model.value.unit_display_name = '';
    model.value.asset_description = '';
    model.value.catalog_description = '';
};

/** Computed bridges so AssetLineVariantSelect / AssetLineUnitSelect can v-model nested fields. */
const variantId = computed({
    get: () => model.value.asset_variant_id,
    set: (v) => { model.value.asset_variant_id = v; },
});
const variantDisplayName = computed({
    get: () => model.value.variant_display_name,
    set: (v) => { model.value.variant_display_name = v; },
});
const catalogDescription = computed({
    get: () => model.value.catalog_description,
    set: (v) => { model.value.catalog_description = v; },
});
const unitId = computed({
    get: () => model.value.asset_unit_id,
    set: (v) => { model.value.asset_unit_id = v; },
});
const unitDisplayName = computed({
    get: () => model.value.unit_display_name,
    set: (v) => { model.value.unit_display_name = v; },
});

// Reset unit when the selected variant changes — a unit belongs to a specific variant.
watch(variantId, (newVid, oldVid) => {
    if (newVid !== oldVid) {
        model.value.asset_unit_id = null;
        model.value.unit_display_name = '';
    }
});

const formatCurrency = (value) =>
    value != null
        ? `$${parseFloat(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
        : '$0.00';

const effectiveQty = computed(() => (props.hideQuantity ? 1 : Number(model.value.quantity || 0)));

const lineTotal = computed(
    () => Number(model.value.unit_price || 0) * effectiveQty.value - Number(model.value.discount || 0),
);

watch(
    () => [open.value, props.hideQuantity],
    () => {
        if (open.value && props.hideQuantity) {
            model.value.quantity = 1;
        }
    },
);

const close = () => {
    open.value = false;
    emit('close');
};

const save = () => {
    if (!model.value.itemable_id) return;
    if (model.value.has_variants && !model.value.asset_variant_id) {
        window.alert('This asset uses variants — select a variant before saving the line.');
        return;
    }
    emit('save', model.value);
};
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
            @click.self="close"
        >
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col overflow-hidden">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{
                            props.pickAssetOnly
                                ? (model.asset_id && props.editing ? 'Edit equipment' : 'Select equipment')
                                : model.asset_id && props.editing
                                  ? 'Edit Asset'
                                  : 'Add Asset'
                        }}
                    </h3>
                    <button type="button" @click="close" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto p-6 space-y-5">
                    <!-- Search (shown when no asset selected) -->
                    <div v-if="!model.itemable_id">
                        <div class="relative mb-3">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                            </div>
                            <input
                                v-model="searchQuery"
                                @input="debouncedFetchAssets"
                                type="text"
                                placeholder="Search assets by name, year, or make..."
                                class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                            />
                        </div>

                        <div v-if="isLoading" class="flex justify-center py-8">
                            <svg class="w-6 h-6 animate-spin text-primary-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                        </div>

                        <div v-else-if="records.length > 0" class="space-y-1.5 max-h-56 overflow-y-auto">
                            <button
                                v-for="asset in records"
                                :key="asset.id"
                                type="button"
                                @click="selectAsset(asset)"
                                class="w-full text-left p-3 border border-gray-200 dark:border-gray-700 rounded-lg hover:border-primary-400 dark:hover:border-primary-500 hover:bg-primary-50 dark:hover:bg-primary-900/20 transition-all group"
                            >
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <div class="font-medium text-gray-900 dark:text-white text-sm group-hover:text-primary-700 dark:group-hover:text-primary-300">
                                            {{ asset.display_name }}
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 flex gap-3">
                                            <span v-if="asset.year">{{ asset.year }}</span>
                                            <span v-if="asset.make?.display_name">{{ asset.make.display_name }}</span>
                                            <span v-if="asset.default_price">{{ formatCurrency(asset.default_price) }}</span>
                                        </div>
                                    </div>
                                    <svg class="w-4 h-4 text-gray-400 group-hover:text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </div>
                            </button>
                        </div>

                        <div v-else class="text-center py-8 text-gray-400 dark:text-gray-500 text-sm">
                            {{ searchQuery.trim() ? 'No assets match your search' : 'No assets available' }}
                        </div>

                        <div v-if="totalPages > 1" class="flex items-center justify-between mt-3 pt-3 border-t border-gray-100 dark:border-gray-700">
                            <button type="button" @click="currentPage--; fetchAssets()" :disabled="currentPage <= 1" class="text-xs text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 disabled:opacity-40">← Prev</button>
                            <span class="text-xs text-gray-400">Page {{ currentPage }} / {{ totalPages }}</span>
                            <button type="button" @click="currentPage++; fetchAssets()" :disabled="currentPage >= totalPages" class="text-xs text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 disabled:opacity-40">Next →</button>
                        </div>
                    </div>

                    <!-- Selected asset + details form -->
                    <div v-if="model.itemable_id" class="space-y-4">
                        <div class="flex items-center justify-between p-3 bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-800 rounded-lg">
                            <div>
                                <div class="font-medium text-primary-900 dark:text-primary-200 text-sm">{{ model.name }}</div>
                                <div class="text-xs text-primary-600 dark:text-primary-400 mt-0.5">
                                    {{ [model.year, model.make].filter(Boolean).join(' · ') || 'No details' }}
                                </div>
                            </div>
                            <button
                                v-if="!editing"
                                type="button"
                                @click="clearSelectedAssetForChange"
                                class="text-xs text-primary-500 hover:text-primary-700 dark:hover:text-primary-300"
                            >
                                Change
                            </button>
                        </div>

                        <AssetLineVariantSelect
                            v-if="model.has_variants && model.itemable_id"
                            v-model="variantId"
                            v-model:variant-display-name="variantDisplayName"
                            v-model:catalog-description="catalogDescription"
                            :asset-id="model.asset_id"
                            :has-variants="model.has_variants"
                            :asset-description="model.asset_description"
                            :sync-catalog-description="true"
                            :apply-default-price="!props.pickAssetOnly"
                            :show-catalog-preview="!props.pickAssetOnly"
                            @update:unit-price="model.unit_price = $event"
                        />

                        <AssetLineUnitSelect
                            v-if="model.itemable_id"
                            v-model="unitId"
                            v-model:unit-display-name="unitDisplayName"
                            :asset-id="model.asset_id"
                            :variant-id="model.has_variants ? model.asset_variant_id : null"
                            :customer-id="props.customerId"
                        />

                        <div
                            v-if="!props.pickAssetOnly"
                            :class="props.hideQuantity ? 'grid grid-cols-2 gap-4' : 'grid grid-cols-3 gap-4'"
                        >
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Unit Price</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">$</span>
                                    <input type="number" v-model="model.unit_price" min="0" step="0.01"
                                        class="w-full pl-7 pr-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent" />
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Discount</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">$</span>
                                    <input type="number" v-model="model.discount" min="0" step="0.01"
                                        class="w-full pl-7 pr-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent" />
                                </div>
                            </div>
                            <div v-if="!props.hideQuantity">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Quantity <span class="text-red-500">*</span></label>
                                <input type="number" v-model="model.quantity" min="1" step="1"
                                    class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent" />
                            </div>
                        </div>

                        <div
                            v-if="!props.pickAssetOnly"
                            class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg"
                        >
                            <span class="text-sm text-gray-600 dark:text-gray-400">Line Total</span>
                            <span class="text-base font-bold text-gray-900 dark:text-white">
                                {{ formatCurrency(lineTotal) }}
                            </span>
                        </div>

                        <div v-if="!props.pickAssetOnly">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Notes</label>
                            <textarea v-model="model.notes" rows="2"
                                class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent resize-none"
                                placeholder="Optional notes for this asset..." />
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
                    <button type="button" @click="close" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">Cancel</button>
                    <button type="button" @click="save" :disabled="!model.itemable_id"
                        class="px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg transition-colors">
                        {{
                            props.pickAssetOnly
                                ? props.editing
                                    ? 'Update'
                                    : 'Use this equipment'
                                : props.editing
                                  ? 'Update Asset'
                                  : 'Add Asset'
                        }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
