<script setup>
import { useForm } from '@inertiajs/vue3';
import AssetLineVariantCell from '@/Components/Tenant/AssetLineVariantCell.vue';
import AssetLineVariantSelect from '@/Components/Tenant/AssetLineVariantSelect.vue';
import RecordSelect from '@/Components/Tenant/RecordSelect.vue';
import { buildResourceRouteParams } from '@/utils/resourceRoutes.js';
import { computed, ref, watch } from 'vue';

const debounce = (fn, delay) => {
    let timer;
    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), delay);
    };
};

const props = defineProps({
    record: {
        type: Object,
        default: null,
    },
    formSchema: {
        type: Object,
        required: true,
    },
    fieldsSchema: {
        type: Object,
        required: true,
    },
    enumOptions: {
        type: Object,
        default: () => ({}),
    },
    account: {
        type: Object,
        default: null,
    },
    timezones: {
        type: Array,
        default: () => [],
    },
    initialData: {
        type: Object,
        default: () => ({}),
    },
    mode: {
        type: String,
        default: 'create',
        validator: (v) => ['create', 'edit'].includes(v),
    },
    fromQualification: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['saved', 'cancelled']);

// ==============================
// Inventory Line Items
// ==============================
const showItemModal = ref(false);
const editingLineIndex = ref(null);
const lineItems = ref([]);

const itemSearchQuery = ref('');
const itemRecords = ref([]);
const itemCurrentPage = ref(1);
const itemTotalPages = ref(1);
const itemIsLoading = ref(false);

const lineItemForm = ref({
    inventory_item_id: null,
    display_name: '',
    sku: '',
    quantity: 1,
    unit_price: 0,
    estimated_cost: 0,
    notes: '',
});

const lineTotal = (item) => Number(item.unit_price || 0) * Number(item.quantity || 0);
const lineCost = (item) => Number(item.estimated_cost || 0) * Number(item.quantity || 0);

const lineItemsSubtotal = computed(() =>
    lineItems.value.reduce((sum, item) => sum + lineTotal(item), 0)
);
const lineItemsCostTotal = computed(() =>
    lineItems.value.reduce((sum, item) => sum + lineCost(item), 0)
);

const formatCurrency = (value) =>
    value != null ? `$${parseFloat(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}` : '$0.00';

// ==============================
// Item Lookup
// ==============================
const fetchItems = async (resetPage = false) => {
    if (resetPage) itemCurrentPage.value = 1;
    itemIsLoading.value = true;

    try {
        const url = new URL(route('records.lookup'), window.location.origin);
        url.searchParams.append('type', 'InventoryItem');
        url.searchParams.append('page', itemCurrentPage.value);
        url.searchParams.append('per_page', 10);
        if (itemSearchQuery.value.trim()) {
            url.searchParams.append('search', itemSearchQuery.value.trim());
        }

        const response = await fetch(url.toString(), {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) throw new Error(`HTTP ${response.status}`);

        const data = await response.json();
        itemRecords.value = data.records || data.data || [];
        itemTotalPages.value = data.meta?.last_page || 1;
    } catch (err) {
        console.error('Failed to fetch inventory items:', err);
        itemRecords.value = [];
    } finally {
        itemIsLoading.value = false;
    }
};

const debouncedFetchItems = debounce(() => fetchItems(true), 300);

const openAddItemModal = () => {
    editingLineIndex.value = null;
    lineItemForm.value = { inventory_item_id: null, display_name: '', sku: '', quantity: 1, unit_price: 0, estimated_cost: 0, notes: '' };
    itemSearchQuery.value = '';
    itemCurrentPage.value = 1;
    fetchItems(true);
    showItemModal.value = true;
};

const openEditItemModal = (index) => {
    editingLineIndex.value = index;
    const item = lineItems.value[index];
    lineItemForm.value = { ...item };
    showItemModal.value = true;
};

const selectInventoryItem = (item) => {
    lineItemForm.value.inventory_item_id = item.id;
    lineItemForm.value.display_name = item.display_name;
    lineItemForm.value.sku = item.sku || '';
    lineItemForm.value.unit_price = Number(item.default_price) || 0;
    lineItemForm.value.estimated_cost = Number(item.default_cost) || 0;
};

const saveLineItem = () => {
    if (!lineItemForm.value.inventory_item_id) return;

    if (editingLineIndex.value !== null) {
        lineItems.value[editingLineIndex.value] = { ...lineItemForm.value };
    } else {
        lineItems.value.push({ ...lineItemForm.value });
    }
    showItemModal.value = false;
};

const removeLineItem = (index) => {
    lineItems.value.splice(index, 1);
};

// ==============================
// Asset Line Items
// ==============================
const showAssetModal = ref(false);
const editingAssetIndex = ref(null);
const assetItems = ref([]);

const assetSearchQuery = ref('');
const assetRecords = ref([]);
const assetCurrentPage = ref(1);
const assetTotalPages = ref(1);
const assetIsLoading = ref(false);

const assetForm = ref({
    asset_id: null,
    display_name: '',
    year: '',
    make: '',
    quantity: 1,
    unit_price: 0,
    estimated_cost: 0,
    notes: '',
    has_variants: false,
    asset_variant_id: null,
    variant_display_name: '',
});

/** Computed bridges so v-model on AssetLineVariantSelect syncs (nested ref keys + defineModel are unreliable). */
const assetFormVariantId = computed({
    get: () => assetForm.value.asset_variant_id,
    set: (v) => {
        assetForm.value.asset_variant_id = v;
    },
});
const assetFormVariantDisplayName = computed({
    get: () => assetForm.value.variant_display_name,
    set: (v) => {
        assetForm.value.variant_display_name = v;
    },
});

const assetLineTotal = (item) => Number(item.unit_price || 0) * Number(item.quantity || 0);
const assetLineCost = (item) => Number(item.estimated_cost || 0) * Number(item.quantity || 0);

const assetSubtotal = computed(() =>
    assetItems.value.reduce((sum, item) => sum + assetLineTotal(item), 0)
);
const assetCostTotal = computed(() =>
    assetItems.value.reduce((sum, item) => sum + assetLineCost(item), 0)
);

const combinedSubtotal = computed(() => lineItemsSubtotal.value + assetSubtotal.value);
const combinedCostTotal = computed(() => lineItemsCostTotal.value + assetCostTotal.value);
const grossProfit = computed(() => combinedSubtotal.value - combinedCostTotal.value);

// ==============================
// Asset Lookup
// ==============================
const fetchAssets = async (resetPage = false) => {
    if (resetPage) assetCurrentPage.value = 1;
    assetIsLoading.value = true;

    try {
        const url = new URL(route('records.lookup'), window.location.origin);
        url.searchParams.append('type', 'Asset');
        url.searchParams.append('page', assetCurrentPage.value);
        url.searchParams.append('per_page', 10);
        if (assetSearchQuery.value.trim()) {
            url.searchParams.append('search', assetSearchQuery.value.trim());
        }

        const response = await fetch(url.toString(), {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) throw new Error(`HTTP ${response.status}`);

        const data = await response.json();
        assetRecords.value = data.records || data.data || [];
        assetTotalPages.value = data.meta?.last_page || 1;
    } catch (err) {
        console.error('Failed to fetch assets:', err);
        assetRecords.value = [];
    } finally {
        assetIsLoading.value = false;
    }
};

const debouncedFetchAssets = debounce(() => fetchAssets(true), 300);

const openAddAssetModal = () => {
    editingAssetIndex.value = null;
    assetForm.value = {
        asset_id: null,
        display_name: '',
        year: '',
        make: '',
        quantity: 1,
        unit_price: 0,
        estimated_cost: 0,
        notes: '',
        has_variants: false,
        asset_variant_id: null,
        variant_display_name: '',
    };
    assetSearchQuery.value = '';
    assetCurrentPage.value = 1;
    fetchAssets(true);
    showAssetModal.value = true;
};

const openEditAssetModal = (index) => {
    editingAssetIndex.value = index;
    assetForm.value = { ...assetItems.value[index] };
    showAssetModal.value = true;
};

const selectAsset = (asset) => {
    assetForm.value.asset_id = asset.id;
    assetForm.value.display_name = asset.display_name;
    assetForm.value.year = asset.year || '';
    assetForm.value.make = asset.make?.display_name || asset.make || '';
    assetForm.value.unit_price = Number(asset.default_price) || 0;
    assetForm.value.estimated_cost = Number(asset.default_cost) || 0;
    assetForm.value.has_variants = Boolean(asset.has_variants);
    assetForm.value.asset_variant_id = null;
    assetForm.value.variant_display_name = '';
};

const clearSelectedAssetForChange = () => {
    assetForm.value.asset_id = null;
    assetForm.value.display_name = '';
    assetForm.value.has_variants = false;
    assetForm.value.asset_variant_id = null;
    assetForm.value.variant_display_name = '';
};

const saveAssetItem = () => {
    if (!assetForm.value.asset_id) return;
    if (assetForm.value.has_variants && !assetForm.value.asset_variant_id) {
        window.alert('This asset uses variants — select a variant before saving the line.');
        return;
    }

    if (editingAssetIndex.value !== null) {
        assetItems.value[editingAssetIndex.value] = { ...assetForm.value };
    } else {
        assetItems.value.push({ ...assetForm.value });
    }
    showAssetModal.value = false;
};

const removeAssetItem = (index) => {
    assetItems.value.splice(index, 1);
};

// ==============================
// Helper: enum options
// ==============================
const getEnumOptions = (fieldKey) => {
    const fieldDef = props.fieldsSchema[fieldKey];
    if (fieldDef?.enum) {
        return props.enumOptions[fieldDef.enum] || [];
    }
    return [];
};

const getEnumLabel = (fieldKey, value) => {
    const opts = getEnumOptions(fieldKey);
    const opt = opts.find(o => o.id === value || o.value === value);
    return opt ? opt.name : (value ?? '—');
};

const isReadonly = computed(() => props.mode === 'edit' && false); // future: locked check

// ==============================
// Pseudo-record for RecordSelect display resolution in create mode
// ==============================
const pseudoRecord = computed(() => props.record ?? (Object.keys(props.initialData).length > 0 ? props.initialData : null));

// ==============================
// Initialize Form
// ==============================
const buildInitialFormData = () => {
    const data = {};

    // Start from fieldsSchema defaults
    Object.keys(props.fieldsSchema).forEach(key => {
        const field = props.fieldsSchema[key];
        if (field.readOnly && !props.record) {
            return;
        }
        if (props.record && props.record[key] !== undefined) {
            data[key] = props.record[key];
        } else if (props.initialData[key] !== undefined) {
            data[key] = props.initialData[key];
        } else if (field.default !== undefined && field.default !== null) {
            data[key] = field.default;
        } else if (field.type === 'boolean' || field.type === 'checkbox') {
            data[key] = false;
        } else if (field.type === 'select') {
            const opts = getEnumOptions(key);
            data[key] = opts.length > 0 ? (opts[0].id ?? opts[0].value) : null;
        } else if (field.type === 'record') {
            data[key] = null;
        } else {
            data[key] = '';
        }
    });

    // Overwrite with initialData IDs for record fields
    if (props.initialData.customer_id) data.customer_id = props.initialData.customer_id;
    if (props.initialData.qualification_id) data.qualification_id = props.initialData.qualification_id;
    if (props.initialData.user_id) data.user_id = props.initialData.user_id;

    return data;
};

const form = useForm(buildInitialFormData());

// Initialize line items when editing
watch(() => props.record?.inventory_items, (items) => {
    if (items && Array.isArray(items)) {
        lineItems.value = items.map(item => ({
            inventory_item_id: item.id,
            display_name: item.display_name,
            sku: item.sku || '',
            quantity: item.pivot?.quantity ?? 1,
            unit_price: item.pivot?.unit_price != null ? Number(item.pivot.unit_price) : (Number(item.default_price) || 0),
            estimated_cost: item.pivot?.estimated_cost != null ? Number(item.pivot.estimated_cost) : (Number(item.default_cost) || 0),
            notes: item.pivot?.notes || '',
        }));
    }
}, { immediate: true });

// Initialize asset items when editing
watch(() => props.record?.assets, (items) => {
    if (items && Array.isArray(items)) {
        assetItems.value = items.map((item) => {
            const vRel = item.asset_variant ?? item.assetVariant;
            const pivotVid = item.pivot?.asset_variant_id ?? null;
            return {
                asset_id: item.id,
                display_name: item.display_name,
                year: item.year || '',
                make: item.make?.display_name || '',
                quantity: item.pivot?.quantity ?? 1,
                unit_price: item.pivot?.unit_price != null ? Number(item.pivot.unit_price) : (Number(item.default_price) || 0),
                estimated_cost: item.pivot?.estimated_cost != null ? Number(item.pivot.estimated_cost) : (Number(item.default_cost) || 0),
                notes: item.pivot?.notes || '',
                has_variants: Boolean(item.has_variants),
                asset_variant_id: pivotVid,
                variant_display_name:
                    vRel?.display_name || vRel?.name || (pivotVid ? `Variant #${pivotVid}` : ''),
            };
        });
    }
}, { immediate: true });

// ==============================
// Submit
// ==============================
const omitReadonlySchemaFields = (data) => {
    const out = { ...data };
    Object.entries(props.fieldsSchema).forEach(([key, def]) => {
        if (def?.readOnly) {
            delete out[key];
        }
    });
    return out;
};

const submit = () => {
    form.transform((data) => ({
        ...omitReadonlySchemaFields(data),
        estimated_value: combinedSubtotal.value > 0 ? combinedSubtotal.value : (data.estimated_value || 0),
        inventory_items: lineItems.value.map(item => ({
            inventory_item_id: item.inventory_item_id,
            quantity: Number(item.quantity) || 1,
            unit_price: Number(item.unit_price) || 0,
            estimated_cost: Number(item.estimated_cost) || 0,
            notes: item.notes || '',
        })),
        assets: assetItems.value.map((item) => ({
            asset_id: item.asset_id,
            quantity: Number(item.quantity) || 1,
            unit_price: Number(item.unit_price) || 0,
            estimated_cost: Number(item.estimated_cost) || 0,
            notes: item.notes || '',
            asset_variant_id: item.asset_variant_id || null,
        })),
    }));

    if (props.mode === 'edit') {
        form.put(route('opportunities.update', buildResourceRouteParams('opportunities', props.record.id)), {
            onSuccess: () => {
                window.location.href = route('opportunities.show', buildResourceRouteParams('opportunities', props.record.id));
            },
            onError: (errors) => console.error('Update failed:', errors),
        });
    } else {
        // Server returns redirect to opportunities.show; Inertia follows it (no client-side URL needed).
        form.post(route('opportunities.store'), {
            onError: (errors) => console.error('Create failed:', errors),
        });
    }
};

const handleCancel = () => emit('cancelled');
</script>

<template>
    <div class="w-full flex flex-col space-y-6">
        <form @submit.prevent="submit">
            <div class="grid gap-6 lg:grid-cols-12">

                <!-- ============================
                     Main Form
                     ============================ -->
                <div class="lg:col-span-8 space-y-6">

                    <!-- Header Card -->
                    <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden">
                        <div class="bg-gradient-to-r from-primary-600 to-primary-700 dark:from-primary-700 dark:to-primary-800 px-6 py-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h1 class="text-2xl font-bold text-white">{{ mode === 'edit' ? 'EDIT OPPORTUNITY' : 'NEW OPPORTUNITY' }}</h1>
                                    <p class="text-primary-100 text-sm mt-1">{{ mode === 'edit' ? 'Update opportunity details' : 'Create a new sales opportunity' }}</p>
                                </div>
                                <div v-if="record?.id" class="text-right">
                                    <div class="text-primary-200 text-xs font-medium">Opportunity #</div>
                                    <div class="text-white text-lg font-mono">{{ record.id }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="p-6 space-y-6">
                            <!-- Customer + Qualification + Salesperson -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                                <!-- Left: People -->
                                <div class="space-y-4">
                                    <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700">
                                        Customer & Lead
                                    </h3>

                                    <!-- Customer -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                            {{ fieldsSchema.customer_id?.label || 'Customer' }} <span class="text-red-500">*</span>
                                        </label>
                                        <RecordSelect
                                            id="customer_id"
                                            :field="fieldsSchema.customer_id"
                                            v-model="form.customer_id"
                                            :enum-options="getEnumOptions('customer_id')"
                                            :record="pseudoRecord"
                                            field-key="customer_id"
                                            :disabled="fromQualification"
                                        />
                                        <p v-if="fromQualification" class="mt-1 text-xs text-gray-400 dark:text-gray-500 flex items-center gap-1">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m0 0v2m0-2h2m-2 0H9m3-10a9 9 0 110 18A9 9 0 0112 5z"/></svg>
                                            Set from qualification
                                        </p>
                                        <p v-if="form.errors.customer_id" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ form.errors.customer_id }}</p>
                                    </div>

                                    <!-- Qualification -->
                                    <div v-if="fieldsSchema.qualification_id">
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                            {{ fieldsSchema.qualification_id?.label || 'Qualification' }}
                                        </label>
                                        <RecordSelect
                                            id="qualification_id"
                                            :field="fieldsSchema.qualification_id"
                                            v-model="form.qualification_id"
                                            :enum-options="getEnumOptions('qualification_id')"
                                            :record="pseudoRecord"
                                            field-key="qualification_id"
                                            :disabled="fromQualification"
                                        />
                                        <p v-if="fromQualification" class="mt-1 text-xs text-gray-400 dark:text-gray-500 flex items-center gap-1">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m0 0v2m0-2h2m-2 0H9m3-10a9 9 0 110 18A9 9 0 0112 5z"/></svg>
                                            Set from qualification
                                        </p>
                                    </div>

                                    <!-- Salesperson -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                            {{ fieldsSchema.user_id?.label || 'Salesperson' }} <span class="text-red-500">*</span>
                                        </label>
                                        <RecordSelect
                                            id="user_id"
                                            :field="fieldsSchema.user_id"
                                            v-model="form.user_id"
                                            :enum-options="getEnumOptions('user_id')"
                                            :record="pseudoRecord"
                                            field-key="user_id"
                                        />
                                        <p v-if="form.errors.user_id" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ form.errors.user_id }}</p>
                                    </div>
                                </div>

                                <!-- Right: Deal Details -->
                                <div class="space-y-4">
                                    <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700">
                                        Deal Details
                                    </h3>

                                    <!-- Stage -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                            {{ fieldsSchema.stage?.label || 'Stage' }} <span class="text-red-500">*</span>
                                        </label>
                                        <select
                                            v-model="form.stage"
                                            class="input-style"
                                        >
                                            <option v-for="opt in getEnumOptions('stage')" :key="opt.id" :value="opt.id">
                                                {{ opt.name }}
                                            </option>
                                        </select>
                                        <p v-if="form.errors.stage" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ form.errors.stage }}</p>
                                    </div>

                                    <!-- Status -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                            {{ fieldsSchema.status?.label || 'Status' }} <span class="text-red-500">*</span>
                                        </label>
                                        <select
                                            v-model="form.status"
                                            class="input-style"
                                        >
                                            <option v-for="opt in getEnumOptions('status')" :key="opt.id" :value="opt.id">
                                                {{ opt.name }}
                                            </option>
                                        </select>
                                        <p v-if="form.errors.status" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ form.errors.status }}</p>
                                    </div>

                                    <!-- Expected Close Date -->
                                    <div v-if="fieldsSchema.expected_close_date">
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                            {{ fieldsSchema.expected_close_date?.label || 'Expected Close Date' }}
                                        </label>
                                        <input
                                            type="date"
                                            v-model="form.expected_close_date"
                                            class="input-style"
                                        />
                                    </div>

                                    <!-- Probability -->
                                    <div v-if="fieldsSchema.probability">
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                            {{ fieldsSchema.probability?.label || 'Probability %' }}
                                        </label>
                                        <input
                                            type="number"
                                            v-model="form.probability"
                                            min="0"
                                            max="100"
                                            class="input-style"
                                        />
                                    </div>
                                </div>
                            </div>

                            <!-- Product Requirements -->
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-5">
                                <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-4">Product Requirements</h3>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" v-model="form.needs_engine" class="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500" />
                                        <span class="text-sm text-gray-700 dark:text-gray-300">{{ fieldsSchema.needs_engine?.label || 'Needs Engine' }}</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" v-model="form.needs_trailer" class="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500" />
                                        <span class="text-sm text-gray-700 dark:text-gray-300">{{ fieldsSchema.needs_trailer?.label || 'Needs Trailer' }}</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Notes -->
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-5 grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                        {{ fieldsSchema.customer_notes?.label || 'Customer Notes' }}
                                    </label>
                                    <textarea
                                        v-model="form.customer_notes"
                                        rows="3"
                                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent resize-none"
                                        placeholder="Notes visible to the customer..."
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                        {{ fieldsSchema.internal_notes?.label || 'Internal Notes' }}
                                    </label>
                                    <textarea
                                        v-model="form.internal_notes"
                                        rows="3"
                                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent resize-none"
                                        placeholder="Internal team notes..."
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ============================
                         Assets
                         ============================ -->
                    <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden">
                        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h2 class="text-base font-semibold text-gray-900 dark:text-white">Assets</h2>
                            <button
                                type="button"
                                @click="openAddAssetModal"
                                class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                Add Asset
                            </button>
                        </div>

                        <div v-if="assetItems.length > 0" class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-700/50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Asset</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide min-w-[7rem]">Variant</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide w-24">Year</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide w-28">Unit Price</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide w-20">Qty</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide w-28">Total</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Notes</th>
                                        <th class="px-4 py-3 w-20"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    <tr
                                        v-for="(item, index) in assetItems"
                                        :key="`${item.asset_id}-${item.asset_variant_id ?? 'x'}-${index}`"
                                        class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors"
                                    >
                                        <td class="px-4 py-3">
                                            <div class="font-medium text-gray-900 dark:text-white">{{ item.display_name }}</div>
                                            <div v-if="item.make" class="text-xs text-gray-400 dark:text-gray-500">{{ item.make }}</div>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                            <AssetLineVariantCell
                                                :label="item.variant_display_name"
                                                :has-variants="item.has_variants"
                                            />
                                        </td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ item.year || '—' }}</td>
                                        <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">{{ formatCurrency(item.unit_price) }}</td>
                                        <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">{{ item.quantity }}</td>
                                        <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">{{ formatCurrency(assetLineTotal(item)) }}</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400 text-xs truncate max-w-[160px]">{{ item.notes || '—' }}</td>
                                        <td class="px-4 py-3">
                                            <div class="flex items-center justify-end gap-1">
                                                <button type="button" @click="openEditAssetModal(index)" class="p-1 text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 rounded transition-colors" title="Edit">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                    </svg>
                                                </button>
                                                <button type="button" @click="removeAssetItem(index)" class="p-1 text-gray-400 hover:text-red-600 dark:hover:text-red-400 rounded transition-colors" title="Remove">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot class="bg-gray-50 dark:bg-gray-700/50 border-t-2 border-gray-200 dark:border-gray-600">
                                    <tr>
                                        <td colspan="5" class="px-4 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">Assets Subtotal</td>
                                        <td class="px-4 py-3 text-right text-base font-bold text-gray-900 dark:text-white">{{ formatCurrency(assetSubtotal) }}</td>
                                        <td colspan="2"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <div v-else class="flex flex-col items-center justify-center py-14 text-center px-6">
                            <svg class="w-12 h-12 text-gray-300 dark:text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">No assets added yet</p>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Click "Add Asset" to attach assets to this opportunity</p>
                        </div>
                    </div>

                    <!-- ============================
                         Inventory Line Items (Parts & Accessories)
                         ============================ -->
                    <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden">
                        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h2 class="text-base font-semibold text-gray-900 dark:text-white">Parts &amp; Accessories</h2>
                            <button
                                type="button"
                                @click="openAddItemModal"
                                class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                Add Item
                            </button>
                        </div>

                        <!-- Items Table -->
                        <div v-if="lineItems.length > 0" class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-700/50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Item</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide w-24">SKU</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide w-24">Unit Price</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide w-20">Qty</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide w-28">Total</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Notes</th>
                                        <th class="px-4 py-3 w-20"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    <tr
                                        v-for="(item, index) in lineItems"
                                        :key="index"
                                        class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors"
                                    >
                                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ item.display_name }}</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400 font-mono text-xs">{{ item.sku || '—' }}</td>
                                        <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">{{ formatCurrency(item.unit_price) }}</td>
                                        <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">{{ item.quantity }}</td>
                                        <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">{{ formatCurrency(lineTotal(item)) }}</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400 text-xs truncate max-w-[160px]">{{ item.notes || '—' }}</td>
                                        <td class="px-4 py-3">
                                            <div class="flex items-center justify-end gap-1">
                                                <button type="button" @click="openEditItemModal(index)" class="p-1 text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 rounded transition-colors" title="Edit">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                    </svg>
                                                </button>
                                                <button type="button" @click="removeLineItem(index)" class="p-1 text-gray-400 hover:text-red-600 dark:hover:text-red-400 rounded transition-colors" title="Remove">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot class="bg-gray-50 dark:bg-gray-700/50 border-t-2 border-gray-200 dark:border-gray-600">
                                    <tr>
                                        <td colspan="4" class="px-4 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">Parts &amp; Accessories Subtotal</td>
                                        <td class="px-4 py-3 text-right text-base font-bold text-gray-900 dark:text-white">{{ formatCurrency(lineItemsSubtotal) }}</td>
                                        <td colspan="2"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <!-- Empty State -->
                        <div v-else class="flex flex-col items-center justify-center py-14 text-center px-6">
                            <svg class="w-12 h-12 text-gray-300 dark:text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">No parts or accessories added yet</p>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Click "Add Item" to attach parts &amp; accessories</p>
                        </div>
                    </div>
                </div>

                <!-- ============================
                     Sidebar
                     ============================ -->
                <div class="lg:col-span-4 space-y-6">

                    <!-- Actions -->
                    <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden sticky top-5">
                        <div class="flex justify-between items-center px-5 py-4 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">Actions</span>
                        </div>

                        <div class="p-5 space-y-3">
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg transition-colors"
                            >
                                <svg v-if="form.processing" class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                                </svg>
                                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                {{ form.processing ? 'Saving...' : (mode === 'edit' ? 'Save Changes' : 'Create Opportunity') }}
                            </button>

                            <button
                                type="button"
                                @click="handleCancel"
                                class="w-full inline-flex items-center justify-center px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 rounded-lg transition-colors"
                            >
                                Cancel
                            </button>
                        </div>
                    </div>

                    <!-- Estimated Value -->
                    <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-600">
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">Deal Value</span>
                        </div>
                        <div class="p-5 space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                    {{ fieldsSchema.estimated_value?.label || 'Estimated Value' }}
                                </label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">$</span>
                                    <input
                                        type="number"
                                        v-model="form.estimated_value"
                                        min="0"
                                        step="0.01"
                                        class="input-style !pl-7"
                                        placeholder="0.00"
                                    />
                                </div>
                            </div>

                            <div v-if="assetItems.length > 0 || lineItems.length > 0" class="pt-3 border-t border-gray-100 dark:border-gray-700 space-y-2">
                                <div v-if="assetItems.length > 0" class="flex justify-between items-center text-sm">
                                    <span class="text-gray-500 dark:text-gray-400">Assets Revenue</span>
                                    <span class="text-gray-700 dark:text-gray-300">{{ formatCurrency(assetSubtotal) }}</span>
                                </div>
                                <div v-if="lineItems.length > 0" class="flex justify-between items-center text-sm">
                                    <span class="text-gray-500 dark:text-gray-400">Parts &amp; Acc. Revenue</span>
                                    <span class="text-gray-700 dark:text-gray-300">{{ formatCurrency(lineItemsSubtotal) }}</span>
                                </div>
                                <div class="flex justify-between items-center text-sm pt-1 border-t border-gray-100 dark:border-gray-700">
                                    <span class="font-medium text-gray-700 dark:text-gray-300">Total Revenue</span>
                                    <span class="font-semibold text-gray-900 dark:text-white">{{ formatCurrency(combinedSubtotal) }}</span>
                                </div>
                                <div class="flex justify-between items-center text-sm">
                                    <span class="text-gray-500 dark:text-gray-400">Total Cost</span>
                                    <span class="text-red-600 dark:text-red-400">- {{ formatCurrency(combinedCostTotal) }}</span>
                                </div>
                                <div class="flex justify-between items-center text-sm pt-1 border-t border-gray-100 dark:border-gray-700">
                                    <span class="font-medium text-gray-700 dark:text-gray-300">Gross Profit</span>
                                    <span :class="grossProfit >= 0 ? 'font-bold text-green-600 dark:text-green-400' : 'font-bold text-red-600 dark:text-red-400'">
                                        {{ formatCurrency(grossProfit) }}
                                    </span>
                                </div>
                                <button
                                    type="button"
                                    @click="form.estimated_value = grossProfit"
                                    class="w-full text-xs text-primary-600 dark:text-primary-400 hover:underline text-left"
                                >
                                    Use gross profit as estimated value
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <!-- ============================
             Add/Edit Asset Modal
             ============================ -->
        <Teleport to="body">
            <div
                v-if="showAssetModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
                @click.self="showAssetModal = false"
            >
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col overflow-hidden">

                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            {{ assetForm.asset_id ? 'Edit Asset' : 'Add Asset' }}
                        </h3>
                        <button @click="showAssetModal = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <div class="flex-1 overflow-y-auto p-6 space-y-5">

                        <!-- Asset Search (only shown when no asset selected) -->
                        <div v-if="!assetForm.asset_id">
                            <div class="relative mb-3">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                    </svg>
                                </div>
                                <input
                                    v-model="assetSearchQuery"
                                    @input="debouncedFetchAssets"
                                    type="text"
                                    placeholder="Search assets by name, year, or make..."
                                    class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                />
                            </div>

                            <div v-if="assetIsLoading" class="flex justify-center py-8">
                                <svg class="w-6 h-6 animate-spin text-primary-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>
                            </div>

                            <div v-else-if="assetRecords.length > 0" class="space-y-1.5 max-h-56 overflow-y-auto">
                                <button
                                    v-for="asset in assetRecords"
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
                                {{ assetSearchQuery.trim() ? 'No assets match your search' : 'No assets available' }}
                            </div>

                            <div v-if="assetTotalPages > 1" class="flex items-center justify-between mt-3 pt-3 border-t border-gray-100 dark:border-gray-700">
                                <button type="button" @click="assetCurrentPage--; fetchAssets()" :disabled="assetCurrentPage <= 1" class="text-xs text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 disabled:opacity-40">← Prev</button>
                                <span class="text-xs text-gray-400">Page {{ assetCurrentPage }} / {{ assetTotalPages }}</span>
                                <button type="button" @click="assetCurrentPage++; fetchAssets()" :disabled="assetCurrentPage >= assetTotalPages" class="text-xs text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 disabled:opacity-40">Next →</button>
                            </div>
                        </div>

                        <!-- Selected Asset + Quantity/Price/Notes form -->
                        <div v-if="assetForm.asset_id" class="space-y-4">

                            <div class="flex items-center justify-between p-3 bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-800 rounded-lg">
                                <div>
                                    <div class="font-medium text-primary-900 dark:text-primary-200 text-sm">{{ assetForm.display_name }}</div>
                                    <div class="text-xs text-primary-600 dark:text-primary-400 mt-0.5">
                                        {{ [assetForm.year, assetForm.make].filter(Boolean).join(' · ') || 'No details' }}
                                    </div>
                                </div>
                                <button
                                    v-if="editingAssetIndex === null"
                                    type="button"
                                    @click="clearSelectedAssetForChange"
                                    class="text-xs text-primary-500 hover:text-primary-700 dark:hover:text-primary-300"
                                >
                                    Change
                                </button>
                            </div>

                            <AssetLineVariantSelect
                                v-if="assetForm.has_variants && assetForm.asset_id"
                                v-model="assetFormVariantId"
                                v-model:variant-display-name="assetFormVariantDisplayName"
                                :asset-id="assetForm.asset_id"
                                :has-variants="assetForm.has_variants"
                                :sync-catalog-description="false"
                                :apply-default-price="true"
                                :show-catalog-preview="false"
                                @update:unit-price="assetForm.unit_price = $event"
                            />

                            <div class="grid grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Unit Price</label>
                                    <div class="relative">
                                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">$</span>
                                        <input
                                            type="number"
                                            v-model="assetForm.unit_price"
                                            min="0"
                                            step="0.01"
                                            class="w-full pl-7 pr-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                        />
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Unit Cost</label>
                                    <div class="relative">
                                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">$</span>
                                        <input
                                            type="number"
                                            v-model="assetForm.estimated_cost"
                                            min="0"
                                            step="0.01"
                                            class="w-full pl-7 pr-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                        />
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Quantity <span class="text-red-500">*</span></label>
                                    <input
                                        type="number"
                                        v-model="assetForm.quantity"
                                        min="1"
                                        step="1"
                                        class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                    />
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">Line Total</span>
                                    <span class="text-base font-bold text-gray-900 dark:text-white">
                                        {{ formatCurrency(Number(assetForm.unit_price || 0) * Number(assetForm.quantity || 0)) }}
                                    </span>
                                </div>
                                <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">Line Profit</span>
                                    <span :class="(Number(assetForm.unit_price || 0) - Number(assetForm.estimated_cost || 0)) * Number(assetForm.quantity || 0) >= 0 ? 'text-base font-bold text-green-600 dark:text-green-400' : 'text-base font-bold text-red-600 dark:text-red-400'">
                                        {{ formatCurrency((Number(assetForm.unit_price || 0) - Number(assetForm.estimated_cost || 0)) * Number(assetForm.quantity || 0)) }}
                                    </span>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Notes</label>
                                <textarea
                                    v-model="assetForm.notes"
                                    rows="2"
                                    class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent resize-none"
                                    placeholder="Optional notes for this asset..."
                                />
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
                        <button type="button" @click="showAssetModal = false" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">Cancel</button>
                        <button
                            type="button"
                            @click="saveAssetItem"
                            :disabled="!assetForm.asset_id"
                            class="px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg transition-colors"
                        >
                            {{ editingAssetIndex !== null ? 'Update Asset' : 'Add Asset' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ============================
             Add/Edit Item Modal
             ============================ -->
        <Teleport to="body">
            <div
                v-if="showItemModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
                @click.self="showItemModal = false"
            >
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col overflow-hidden">

                    <!-- Modal Header -->
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            {{ lineItemForm.inventory_item_id ? 'Edit Item' : 'Add Inventory Item' }}
                        </h3>
                        <button @click="showItemModal = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <div class="flex-1 overflow-y-auto p-6 space-y-5">

                        <!-- Item Search (only shown when no item selected yet) -->
                        <div v-if="!lineItemForm.inventory_item_id">
                            <div class="relative mb-3">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                    </svg>
                                </div>
                                <input
                                    v-model="itemSearchQuery"
                                    @input="debouncedFetchItems"
                                    type="text"
                                    placeholder="Search inventory items by name or SKU..."
                                    class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                />
                            </div>

                            <!-- Loading -->
                            <div v-if="itemIsLoading" class="flex justify-center py-8">
                                <svg class="w-6 h-6 animate-spin text-primary-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>
                            </div>

                            <!-- Item List -->
                            <div v-else-if="itemRecords.length > 0" class="space-y-1.5 max-h-56 overflow-y-auto">
                                <button
                                    v-for="item in itemRecords"
                                    :key="item.id"
                                    type="button"
                                    @click="selectInventoryItem(item)"
                                    class="w-full text-left p-3 border border-gray-200 dark:border-gray-700 rounded-lg hover:border-primary-400 dark:hover:border-primary-500 hover:bg-primary-50 dark:hover:bg-primary-900/20 transition-all group"
                                >
                                    <div class="flex items-center justify-between gap-3">
                                        <div>
                                            <div class="font-medium text-gray-900 dark:text-white text-sm group-hover:text-primary-700 dark:group-hover:text-primary-300">
                                                {{ item.display_name }}
                                            </div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 flex gap-3">
                                                <span v-if="item.sku">SKU: {{ item.sku }}</span>
                                                <span v-if="item.default_price">{{ formatCurrency(item.default_price) }}</span>
                                            </div>
                                        </div>
                                        <svg class="w-4 h-4 text-gray-400 group-hover:text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                        </svg>
                                    </div>
                                </button>
                            </div>

                            <!-- Empty State -->
                            <div v-else class="text-center py-8 text-gray-400 dark:text-gray-500 text-sm">
                                {{ itemSearchQuery.trim() ? 'No items match your search' : 'No inventory items available' }}
                            </div>

                            <!-- Pagination -->
                            <div v-if="itemTotalPages > 1" class="flex items-center justify-between mt-3 pt-3 border-t border-gray-100 dark:border-gray-700">
                                <button type="button" @click="itemCurrentPage--; fetchItems()" :disabled="itemCurrentPage <= 1" class="text-xs text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 disabled:opacity-40">← Prev</button>
                                <span class="text-xs text-gray-400">Page {{ itemCurrentPage }} / {{ itemTotalPages }}</span>
                                <button type="button" @click="itemCurrentPage++; fetchItems()" :disabled="itemCurrentPage >= itemTotalPages" class="text-xs text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 disabled:opacity-40">Next →</button>
                            </div>
                        </div>

                        <!-- Selected Item + Quantity/Notes form -->
                        <div v-if="lineItemForm.inventory_item_id" class="space-y-4">

                            <!-- Selected Item Badge -->
                            <div class="flex items-center justify-between p-3 bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-800 rounded-lg">
                                <div>
                                    <div class="font-medium text-primary-900 dark:text-primary-200 text-sm">{{ lineItemForm.display_name }}</div>
                                    <div class="text-xs text-primary-600 dark:text-primary-400 mt-0.5">{{ lineItemForm.sku || 'No SKU' }}</div>
                                </div>
                                <button
                                    v-if="editingLineIndex === null"
                                    type="button"
                                    @click="lineItemForm.inventory_item_id = null; lineItemForm.display_name = ''"
                                    class="text-xs text-primary-500 hover:text-primary-700 dark:hover:text-primary-300"
                                >
                                    Change
                                </button>
                            </div>

                            <div class="grid grid-cols-3 gap-4">
                                <!-- Unit Price -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Unit Price</label>
                                    <div class="relative">
                                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">$</span>
                                        <input
                                            type="number"
                                            v-model="lineItemForm.unit_price"
                                            min="0"
                                            step="0.01"
                                            class="w-full pl-7 pr-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                        />
                                    </div>
                                </div>

                                <!-- Estimated Cost -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Unit Cost</label>
                                    <div class="relative">
                                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">$</span>
                                        <input
                                            type="number"
                                            v-model="lineItemForm.estimated_cost"
                                            min="0"
                                            step="0.01"
                                            class="w-full pl-7 pr-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                        />
                                    </div>
                                </div>

                                <!-- Quantity -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Quantity <span class="text-red-500">*</span></label>
                                    <input
                                        type="number"
                                        v-model="lineItemForm.quantity"
                                        min="1"
                                        step="1"
                                        class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                    />
                                </div>
                            </div>

                            <!-- Line Total / Profit Preview -->
                            <div class="grid grid-cols-2 gap-3">
                                <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">Line Total</span>
                                    <span class="text-base font-bold text-gray-900 dark:text-white">
                                        {{ formatCurrency(Number(lineItemForm.unit_price || 0) * Number(lineItemForm.quantity || 0)) }}
                                    </span>
                                </div>
                                <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">Line Profit</span>
                                    <span :class="(Number(lineItemForm.unit_price || 0) - Number(lineItemForm.estimated_cost || 0)) * Number(lineItemForm.quantity || 0) >= 0 ? 'text-base font-bold text-green-600 dark:text-green-400' : 'text-base font-bold text-red-600 dark:text-red-400'">
                                        {{ formatCurrency((Number(lineItemForm.unit_price || 0) - Number(lineItemForm.estimated_cost || 0)) * Number(lineItemForm.quantity || 0)) }}
                                    </span>
                                </div>
                            </div>

                            <!-- Notes -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Notes</label>
                                <textarea
                                    v-model="lineItemForm.notes"
                                    rows="2"
                                    class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent resize-none"
                                    placeholder="Optional notes for this item..."
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Modal Footer -->
                    <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
                        <button
                            type="button"
                            @click="showItemModal = false"
                            class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            @click="saveLineItem"
                            :disabled="!lineItemForm.inventory_item_id"
                            class="px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg transition-colors"
                        >
                            {{ editingLineIndex !== null ? 'Update Item' : 'Add Item' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
