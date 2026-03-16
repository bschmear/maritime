<script setup>
import { useForm, router } from '@inertiajs/vue3';
import RecordSelect from '@/Components/Tenant/RecordSelect.vue';
import AddressAutocomplete from '@/Components/AddressAutocomplete.vue';
import { useTaxRateByAddress } from '@/composables/useTaxRateByAddress';
import { computed, ref, watch, onMounted } from 'vue';

const debounce = (fn, delay) => {
    let timer;
    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), delay);
    };
};

const props = defineProps({
    record: { type: Object, default: null },
    formSchema: { type: Object, required: true },
    fieldsSchema: { type: Object, required: true },
    enumOptions: { type: Object, default: () => ({}) },
    account: { type: Object, default: null },
    timezones: { type: Array, default: () => [] },
    initialData: { type: Object, default: () => ({}) },
    mode: {
        type: String,
        default: 'create',
        validator: (v) => ['create', 'edit', 'view'].includes(v),
    },
    opportunityLineItems: { type: Object, default: null },
});

const emit = defineEmits(['saved', 'cancelled']);

const pseudoRecord = computed(() =>
    props.record ?? (Object.keys(props.initialData).length > 0 ? props.initialData : null)
);

const extractDate = (dateString) => {
    if (!dateString) return null;
    return dateString.split('T')[0];
};

// Returns a YYYY-MM-DD string offset by `defaultDay` days from today.
// Reads the offset from formSchema.form sections when mode is 'create' and the field has no existing value.
const getDefaultDateFromSchema = (key) => {
    if (props.mode !== 'create') return null;
    const sections = props.formSchema?.form ?? props.formSchema ?? {};
    for (const section of Object.values(sections)) {
        if (!section || typeof section !== 'object') continue;
        const field = (section.fields ?? []).find(f => f?.key === key);
        if (field?.defaultDay !== undefined) {
            const d = new Date();
            d.setDate(d.getDate() + Number(field.defaultDay));
            return d.toISOString().split('T')[0];
        }
    }
    return null;
};

// ==============================
// Main Form
// ==============================
const form = useForm({
    customer_id: props.record?.customer_id || props.initialData?.customer_id || null,
    opportunity_id: props.record?.opportunity_id || props.initialData?.opportunity_id || null,
    user_id: props.record?.user_id || props.initialData?.user_id || null,
    tax_rate: props.record?.tax_rate ?? props.initialData?.tax_rate ?? 0,
    issue_date: extractDate(props.record?.issue_date || props.initialData?.issue_date) || getDefaultDateFromSchema('issue_date'),
    expiration_date: extractDate(props.record?.expiration_date || props.initialData?.expiration_date) || getDefaultDateFromSchema('expiration_date'),
    notes: props.record?.notes || props.initialData?.notes || '',
    terms: props.record?.terms || props.initialData?.terms || '',
    billing_address_line1: props.record?.billing_address_line1 || props.initialData?.billing_address_line1 || '',
    billing_address_line2: props.record?.billing_address_line2 || props.initialData?.billing_address_line2 || '',
    billing_city:          props.record?.billing_city          || props.initialData?.billing_city          || '',
    billing_state:         props.record?.billing_state         || props.initialData?.billing_state         || '',
    billing_postal:        props.record?.billing_postal        || props.initialData?.billing_postal        || '',
    billing_country:       props.record?.billing_country       || props.initialData?.billing_country       || '',
    billing_latitude:      props.record?.billing_latitude      ?? props.initialData?.billing_latitude      ?? null,
    billing_longitude:     props.record?.billing_longitude     ?? props.initialData?.billing_longitude     ?? null,
});

// ── Billing Address ──────────────────────────────────────────────────────────
const { fetchTaxRate, isFetching: isFetchingTaxRate } = useTaxRateByAddress();

const applyAddressToForm = (src) => {
    form.billing_address_line1 = src.billing_address_line1 || src.address_line_1 || '';
    form.billing_address_line2 = src.billing_address_line2 || src.address_line_2 || '';
    form.billing_city          = src.billing_city          || src.city           || '';
    form.billing_state         = src.billing_state         || src.state          || '';
    form.billing_postal        = src.billing_postal        || src.postal_code    || '';
    form.billing_country       = src.billing_country       || src.country        || '';
    form.billing_latitude      = src.billing_latitude      ?? src.latitude       ?? null;
    form.billing_longitude     = src.billing_longitude     ?? src.longitude      ?? null;
};

// Confirmation modal for billing address auto-fill
const showAddressConfirm = ref(false);
const pendingCustomerAddress = ref(null);

const handleCustomerSelected = (customer) => {
    const street = customer.address_line_1 || customer.billing_address_line1 || '';
    if (!street) return; // customer has no address on file — nothing to offer

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

const handleAddressUpdate = (data) => {
    form.billing_address_line1 = data.street      ?? '';
    form.billing_address_line2 = data.unit        ?? '';
    form.billing_city          = data.city        ?? '';
    form.billing_state         = data.stateCode   || data.state || '';
    form.billing_postal        = data.postalCode  ?? '';
    form.billing_country       = data.countryCode || data.country || '';
    form.billing_latitude      = data.latitude    ?? null;
    form.billing_longitude     = data.longitude   ?? null;
};

watch(() => form.billing_state, async (newState) => {
    if (newState) {
        const rate = await fetchTaxRate({
            state:       newState,
            city:        form.billing_city             || undefined,
            postal_code: form.billing_postal           || undefined,
            line1:       form.billing_address_line1    || undefined,
            country:     form.billing_country          || undefined,
            latitude:    form.billing_latitude         ?? undefined,
            longitude:   form.billing_longitude        ?? undefined,
        });
        if (rate !== null) form.tax_rate = rate;
    }
});

const formatCurrency = (value) =>
    value != null
        ? `$${parseFloat(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
        : '$0.00';

// ==============================
// Inventory Line Items
// ==============================
const showItemModal = ref(false);
const editingLineIndex = ref(null);
const inventoryItems = ref([]);

const itemSearchQuery = ref('');
const itemRecords = ref([]);
const itemCurrentPage = ref(1);
const itemTotalPages = ref(1);
const itemIsLoading = ref(false);

const lineItemForm = ref({
    itemable_type: 'App\\Domain\\InventoryItem\\Models\\InventoryItem',
    itemable_id: null,
    inventory_item_id: null,
    name: '',
    sku: '',
    quantity: 1,
    unit_price: 0,
    discount: 0,
    notes: '',
    addons: [],
});

const lineBaseTotal = (item) =>
    Math.max(0, Number(item.unit_price || 0) * Number(item.quantity || 1) - Number(item.discount || 0));

const lineTotal = (item) => {
    const addonsTotal = (item.addons || []).reduce(
        (sum, addon) => sum + Number(addon.price || 0) * Number(addon.quantity || 1),
        0
    );
    return lineBaseTotal(item) + addonsTotal;
};

const inventorySubtotal = computed(() =>
    inventoryItems.value.reduce((sum, item) => sum + lineTotal(item), 0)
);

const fetchItems = async (resetPage = false) => {
    if (resetPage) itemCurrentPage.value = 1;
    itemIsLoading.value = true;
    try {
        const url = new URL(route('records.lookup'), window.location.origin);
        url.searchParams.append('type', 'InventoryItem');
        url.searchParams.append('page', itemCurrentPage.value);
        url.searchParams.append('per_page', 10);
        if (itemSearchQuery.value.trim()) url.searchParams.append('search', itemSearchQuery.value.trim());
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
    lineItemForm.value = {
        itemable_type: 'App\\Domain\\InventoryItem\\Models\\InventoryItem',
        itemable_id: null,
        inventory_item_id: null,
        name: '',
        sku: '',
        quantity: 1,
        unit_price: 0,
        discount: 0,
        notes: '',
        addons: [],
    };
    itemSearchQuery.value = '';
    itemCurrentPage.value = 1;
    fetchItems(true);
    showItemModal.value = true;
};

const openEditItemModal = (index) => {
    editingLineIndex.value = index;
    const item = inventoryItems.value[index];
    lineItemForm.value = { ...item, addons: [...(item.addons || [])] };
    showItemModal.value = true;
};

const selectInventoryItem = (item) => {
    lineItemForm.value.itemable_id = item.id;
    lineItemForm.value.inventory_item_id = item.id;
    lineItemForm.value.name = item.display_name;
    lineItemForm.value.sku = item.sku || '';
    lineItemForm.value.unit_price = Number(item.default_price) || 0;
};

const saveLineItem = () => {
    if (!lineItemForm.value.itemable_id) return;
    if (editingLineIndex.value !== null) {
        inventoryItems.value[editingLineIndex.value] = { ...lineItemForm.value };
    } else {
        inventoryItems.value.push({ ...lineItemForm.value });
    }
    showItemModal.value = false;
};

const removeLineItem = (index) => inventoryItems.value.splice(index, 1);

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
});

const assetBaseLineTotal = (item) =>
    Math.max(0, Number(item.unit_price || 0) * Number(item.quantity || 1) - Number(item.discount || 0));

const assetLineTotal = (item) => {
    const addonsTotal = (item.addons || []).reduce(
        (sum, addon) => sum + Number(addon.price || 0) * Number(addon.quantity || 1),
        0
    );
    return assetBaseLineTotal(item) + addonsTotal;
};

const assetSubtotal = computed(() =>
    assetItems.value.reduce((sum, item) => sum + assetLineTotal(item), 0)
);

const fetchAssets = async (resetPage = false) => {
    if (resetPage) assetCurrentPage.value = 1;
    assetIsLoading.value = true;
    try {
        const url = new URL(route('records.lookup'), window.location.origin);
        url.searchParams.append('type', 'Asset');
        url.searchParams.append('page', assetCurrentPage.value);
        url.searchParams.append('per_page', 10);
        if (assetSearchQuery.value.trim()) url.searchParams.append('search', assetSearchQuery.value.trim());
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
    };
    assetSearchQuery.value = '';
    assetCurrentPage.value = 1;
    fetchAssets(true);
    showAssetModal.value = true;
};

const openEditAssetModal = (index) => {
    editingAssetIndex.value = index;
    assetForm.value = { ...assetItems.value[index], addons: [...(assetItems.value[index].addons || [])] };
    showAssetModal.value = true;
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
    if (editingAssetIndex.value !== null) {
        assetItems.value[editingAssetIndex.value] = { ...assetForm.value };
    } else {
        assetItems.value.push({ ...assetForm.value });
    }
    showAssetModal.value = false;
};

const removeAssetItem = (index) => assetItems.value.splice(index, 1);

// ==============================
// Add-ons
// ==============================
const showAddonModal = ref(false);
const currentLineItem = ref(null);
const currentLineItemType = ref(null);

const addonSearchQuery = ref('');
const addonRecords = ref([]);
const addonCurrentPage = ref(1);
const addonTotalPages = ref(1);
const addonIsLoading = ref(false);

const customAddonForm = ref({ name: '', price: 0, quantity: 1, notes: '' });

const fetchAddons = async (resetPage = false) => {
    if (resetPage) addonCurrentPage.value = 1;
    addonIsLoading.value = true;
    try {
        const url = new URL(route('records.lookup'), window.location.origin);
        url.searchParams.append('type', 'AddOn');
        url.searchParams.append('page', addonCurrentPage.value);
        url.searchParams.append('per_page', 10);
        if (addonSearchQuery.value.trim()) url.searchParams.append('search', addonSearchQuery.value.trim());
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
        addonRecords.value = data.records || data.data || [];
        addonTotalPages.value = data.meta?.last_page || 1;
    } catch (err) {
        console.error('Failed to fetch add-ons:', err);
        addonRecords.value = [];
    } finally {
        addonIsLoading.value = false;
    }
};

const debouncedFetchAddons = debounce(() => fetchAddons(true), 300);

const openAddonModal = (lineItem, lineType) => {
    currentLineItem.value = lineItem;
    currentLineItemType.value = lineType;
    addonSearchQuery.value = '';
    customAddonForm.value = { name: '', price: 0, quantity: 1, notes: '' };
    fetchAddons(true);
    showAddonModal.value = true;
};

const selectAddon = (addon) => {
    if (!currentLineItem.value) return;
    if (!currentLineItem.value.addons) currentLineItem.value.addons = [];
    currentLineItem.value.addons.push({
        addon_id: addon.id,
        name: addon.name,
        price: Number(addon.default_price) || 0,
        quantity: 1,
        notes: '',
    });
    showAddonModal.value = false;
};

const addCustomAddon = () => {
    if (!currentLineItem.value || !customAddonForm.value.name.trim()) return;
    if (!currentLineItem.value.addons) currentLineItem.value.addons = [];
    currentLineItem.value.addons.push({
        addon_id: null,
        name: customAddonForm.value.name,
        price: Number(customAddonForm.value.price) || 0,
        quantity: Number(customAddonForm.value.quantity) || 1,
        notes: customAddonForm.value.notes || '',
    });
    showAddonModal.value = false;
};

const removeAddon = (lineItem, addonIndex) => {
    if (lineItem.addons) lineItem.addons.splice(addonIndex, 1);
};

// ==============================
// Totals
// ==============================
const addonSubtotalForItems = (items) =>
    items.reduce((sum, item) =>
        sum + (item.addons ?? []).reduce((s, a) => s + Number(a.price || 0) * Number(a.quantity || 1), 0), 0);

const assetBaseSubtotal = computed(() =>
    assetItems.value.reduce((sum, item) => sum + assetBaseLineTotal(item), 0));

const assetAddonSubtotal = computed(() => addonSubtotalForItems(assetItems.value));

const inventoryBaseSubtotal = computed(() =>
    inventoryItems.value.reduce((sum, item) => sum + lineBaseTotal(item), 0));

const inventoryAddonSubtotal = computed(() => addonSubtotalForItems(inventoryItems.value));

const combinedSubtotal = computed(() => assetSubtotal.value + inventorySubtotal.value);
const taxAmount = computed(() => combinedSubtotal.value * (Number(form.tax_rate) / 100));
const grandTotal = computed(() => combinedSubtotal.value + taxAmount.value);

// ==============================
// Load initial data
// ==============================
onMounted(() => {
    if (props.opportunityLineItems) {
        (props.opportunityLineItems.assets || []).forEach((asset) => {
            assetItems.value.push({
                itemable_type: 'App\\Domain\\Asset\\Models\\Asset',
                itemable_id: asset.id,
                asset_id: asset.id,
                name: asset.display_name,
                year: asset.year || '',
                make: asset.make?.display_name || '',
                quantity: asset.pivot?.quantity || 1,
                unit_price: Number(asset.pivot?.unit_price) || Number(asset.default_price) || 0,
                discount: 0,
                notes: asset.pivot?.notes || '',
                addons: [],
            });
        });

        (props.opportunityLineItems.inventoryItems || []).forEach((item) => {
            inventoryItems.value.push({
                itemable_type: 'App\\Domain\\InventoryItem\\Models\\InventoryItem',
                itemable_id: item.id,
                inventory_item_id: item.id,
                name: item.display_name,
                sku: item.sku || '',
                quantity: item.pivot?.quantity || 1,
                unit_price: Number(item.pivot?.unit_price) || Number(item.default_price) || 0,
                discount: 0,
                notes: item.pivot?.notes || '',
                addons: [],
            });
        });
    }

    if (props.record?.primary_version?.line_items) {
        props.record.primary_version.line_items.forEach((lineItem) => {
            const lineData = {
                itemable_type: lineItem.itemable_type,
                itemable_id: lineItem.itemable_id,
                name: lineItem.name,
                quantity: lineItem.quantity,
                unit_price: lineItem.unit_price,
                discount: lineItem.discount || 0,
                notes: lineItem.notes || '',
                addons: (lineItem.addons || []).map((a) => ({
                    addon_id: a.addon_id,
                    name: a.name || a.addon?.name || '',
                    price: Number(a.price) || 0,
                    quantity: Number(a.quantity) || 1,
                    notes: a.notes || '',
                })),
            };

            if (lineItem.itemable_type === 'App\\Domain\\Asset\\Models\\Asset') {
                lineData.asset_id = lineItem.itemable_id;
                lineData.year = lineItem.itemable?.year || '';
                lineData.make = lineItem.itemable?.make?.display_name || '';
                assetItems.value.push(lineData);
            } else if (lineItem.itemable_type === 'App\\Domain\\InventoryItem\\Models\\InventoryItem') {
                lineData.inventory_item_id = lineItem.itemable_id;
                lineData.sku = lineItem.itemable?.sku || '';
                inventoryItems.value.push(lineData);
            }
        });
    }
});

// ==============================
// Submit
// ==============================
const submit = () => {
    if (props.mode === 'view') return;

    const lineItemsData = [];

    assetItems.value.forEach((item, idx) => {
        lineItemsData.push({
            itemable_type: item.itemable_type,
            itemable_id: item.itemable_id,
            name: item.name,
            quantity: Number(item.quantity) || 1,
            unit_price: Number(item.unit_price) || 0,
            discount: Number(item.discount) || 0,
            notes: item.notes || '',
            position: idx,
            addons: (item.addons || []).map((addon) => ({
                addon_id: addon.addon_id,
                name: addon.name,
                price: Number(addon.price) || 0,
                quantity: Number(addon.quantity) || 1,
                notes: addon.notes || '',
            })),
        });
    });

    inventoryItems.value.forEach((item, idx) => {
        lineItemsData.push({
            itemable_type: item.itemable_type,
            itemable_id: item.itemable_id,
            name: item.name,
            quantity: Number(item.quantity) || 1,
            unit_price: Number(item.unit_price) || 0,
            discount: Number(item.discount) || 0,
            notes: item.notes || '',
            position: assetItems.value.length + idx,
            addons: (item.addons || []).map((addon) => ({
                addon_id: addon.addon_id,
                name: addon.name,
                price: Number(addon.price) || 0,
                quantity: Number(addon.quantity) || 1,
                notes: addon.notes || '',
            })),
        });
    });

    form.transform((data) => {
        const transformed = { ...data, line_items: lineItemsData };
        return transformed;
    });

    if (props.mode === 'edit') {
        form.put(route('estimates.update', props.record.id), {
            onSuccess: (response) => {
                emit('saved');
            },
            onError: (errors) => {
                console.error('Update failed:', errors);
                console.error('Form errors:', form.errors);
            },
        });
    } else {
        form.post(route('estimates.store'), {
            onSuccess: (response) => {
                emit('saved');
            },
            onError: (errors) => {
                console.error('Create failed:', errors);
                console.error('Form errors:', form.errors);
            },
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
                     Main Column
                     ============================ -->
                <div class="lg:col-span-8 space-y-6">

                    <!-- Header Card -->
                    <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden">
                        <div class="bg-gradient-to-r from-primary-600 to-primary-700 dark:from-primary-700 dark:to-primary-800 px-6 py-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h1 class="text-2xl font-bold text-white">
                                        {{ mode === 'edit' ? 'EDIT ESTIMATE' : 'NEW ESTIMATE' }}
                                    </h1>
                                    <p class="text-primary-100 text-sm mt-1">
                                        {{ mode === 'edit' ? 'Update estimate details' : 'Create a new customer estimate' }}
                                    </p>
                                </div>
                                <div v-if="record?.id" class="text-right">
                                    <div class="text-primary-200 text-xs font-medium">Estimate #</div>
                                    <div class="text-white text-lg font-mono">{{ record.display_name || record.id }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="p-6 space-y-6">
                            <!-- Customer + Opportunity + Salesperson -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                                <!-- Left: People -->
                                <div class="space-y-4">
                                    <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700">
                                        Customer & Lead
                                    </h3>

                                    <!-- Customer -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                            Customer <span class="text-red-500">*</span>
                                        </label>
                                        <RecordSelect
                                            id="customer_id"
                                            :field="fieldsSchema?.customer_id || { type: 'relationship', relationship_type: 'customers', label: 'Customer', required: true }"
                                            v-model="form.customer_id"
                                            :record="pseudoRecord"
                                            field-key="customer_id"
                                            :disabled="mode === 'view' || (initialData?.opportunity_id && initialData?.customer_id)"
                                            @record-selected="handleCustomerSelected"
                                        />
                                        <p v-if="form.errors.customer_id" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ form.errors.customer_id }}</p>
                                    </div>

                                    <!-- Opportunity -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                            Opportunity
                                        </label>
                                        <RecordSelect
                                            id="opportunity_id"
                                            :field="fieldsSchema?.opportunity_id || { type: 'relationship', relationship_type: 'opportunities', label: 'Opportunity' }"
                                            v-model="form.opportunity_id"
                                            :record="pseudoRecord"
                                            field-key="opportunity_id"
                                            :disabled="mode === 'view' || !!initialData?.opportunity_id"
                                        />
                                        <p v-if="form.errors.opportunity_id" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ form.errors.opportunity_id }}</p>
                                    </div>

                                    <!-- Salesperson -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                            Salesperson
                                        </label>
                                        <RecordSelect
                                            id="user_id"
                                            :field="fieldsSchema?.user_id || { type: 'relationship', relationship_type: 'users', label: 'Salesperson' }"
                                            v-model="form.user_id"
                                            :record="pseudoRecord"
                                            field-key="user_id"
                                            :disabled="mode === 'view'"
                                        />
                                        <p v-if="form.errors.user_id" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ form.errors.user_id }}</p>
                                    </div>
                                </div>

                                <!-- Right: Estimate Details -->
                                <div class="space-y-4">
                                    <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700">
                                        Estimate Details
                                    </h3>

                                    <!-- Issue Date -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                            Issue Date
                                        </label>
                                        <input
                                            type="date"
                                            v-model="form.issue_date"
                                            :disabled="mode === 'view'"
                                            class="input-style"
                                        />
                                        <p v-if="form.errors.issue_date" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ form.errors.issue_date }}</p>
                                    </div>

                                    <!-- Expiration Date -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                            Expiration Date
                                        </label>
                                        <input
                                            type="date"
                                            v-model="form.expiration_date"
                                            :disabled="mode === 'view'"
                                            class="input-style"
                                        />
                                        <p v-if="form.errors.expiration_date" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ form.errors.expiration_date }}</p>
                                    </div>

                                    <!-- Tax Rate -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                            Tax Rate (%)
                                        </label>
                                        <input
                                            type="number"
                                            v-model.number="form.tax_rate"
                                            step="0.01"
                                            min="0"
                                            max="100"
                                            :disabled="mode === 'view'"
                                            class="input-style"
                                            placeholder="0.00"
                                        />
                                        <p v-if="form.errors.tax_rate" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ form.errors.tax_rate }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Notes & Terms -->
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-5 grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Notes</label>
                                    <textarea
                                        v-model="form.notes"
                                        rows="3"
                                        :disabled="mode === 'view'"
                                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent resize-none"
                                        placeholder="Internal notes..."
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Terms</label>
                                    <textarea
                                        v-model="form.terms"
                                        rows="3"
                                        :disabled="mode === 'view'"
                                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent resize-none"
                                        placeholder="Payment terms and conditions..."
                                    />
                                </div>
                            </div>

                            <!-- Billing Address -->
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-5">
                                <div class="flex items-center justify-between mb-3">
                                    <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                                        Billing Address
                                    </h3>
                                    <span v-if="isFetchingTaxRate" class="text-xs text-primary-600 dark:text-primary-400 animate-pulse">
                                        Fetching tax rate…
                                    </span>
                                </div>
                                <AddressAutocomplete
                                    :street="form.billing_address_line1"
                                    :unit="form.billing_address_line2"
                                    :city="form.billing_city"
                                    :state="form.billing_state"
                                    :stateCode="form.billing_state"
                                    :postalCode="form.billing_postal"
                                    :country="form.billing_country"
                                    :latitude="form.billing_latitude"
                                    :longitude="form.billing_longitude"
                                    :disabled="mode === 'view'"
                                    @update="handleAddressUpdate"
                                />
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
                                v-if="mode !== 'view'"
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
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide w-24">Year</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide w-28">Unit Price</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide w-24">Discount</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide w-20">Qty</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide w-28">Total</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Add-ons</th>
                                        <th class="px-4 py-3 w-20"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    <template v-for="(item, index) in assetItems" :key="index">
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                                            <td class="px-4 py-3">
                                                <div class="font-medium text-gray-900 dark:text-white">{{ item.name }}</div>
                                                <div v-if="item.make" class="text-xs text-gray-400 dark:text-gray-500">{{ item.make }}</div>
                                            </td>
                                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ item.year || '—' }}</td>
                                            <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">{{ formatCurrency(item.unit_price) }}</td>
                                            <td class="px-4 py-3 text-right text-red-500 dark:text-red-400">
                                                {{ item.discount > 0 ? `-${formatCurrency(item.discount)}` : '—' }}
                                            </td>
                                            <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">{{ item.quantity }}</td>
                                            <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">{{ formatCurrency(assetBaseLineTotal(item)) }}</td>
                                            <td class="px-4 py-3">
                                                <button
                                                    v-if="mode !== 'view'"
                                                    type="button"
                                                    @click="openAddonModal(item, 'asset')"
                                                    class="inline-flex items-center gap-1 text-xs text-primary-600 dark:text-primary-400 hover:underline"
                                                >
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                                    </svg>
                                                    Add-ons ({{ (item.addons || []).length }})
                                                </button>
                                                <span v-else class="text-xs text-gray-400">{{ (item.addons || []).length }} add-on(s)</span>
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="flex items-center justify-end gap-1">
                                                    <button v-if="mode !== 'view'" type="button" @click="openEditAssetModal(index)" class="p-1 text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 rounded transition-colors" title="Edit">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                        </svg>
                                                    </button>
                                                    <button v-if="mode !== 'view'" type="button" @click="removeAssetItem(index)" class="p-1 text-gray-400 hover:text-red-600 dark:hover:text-red-400 rounded transition-colors" title="Remove">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <!-- Add-ons sub-rows -->
                                        <tr v-for="(addon, addonIdx) in (item.addons || [])" :key="`asset-addon-${index}-${addonIdx}`" class="bg-primary-50/40 dark:bg-primary-900/10">
                                            <td class="pl-10 pr-4 py-2 text-xs text-gray-600 dark:text-gray-400 italic" colspan="2">
                                                ↳ {{ addon.name }}
                                            </td>
                                            <td class="px-4 py-2 text-right text-xs text-gray-500 dark:text-gray-400">{{ formatCurrency(addon.price) }}</td>
                                            <td class="px-4 py-2 text-right text-xs text-gray-400">—</td>
                                            <td class="px-4 py-2 text-right text-xs text-gray-500 dark:text-gray-400">{{ addon.quantity }}</td>
                                            <td class="px-4 py-2 text-right text-xs font-medium text-gray-700 dark:text-gray-300">{{ formatCurrency(Number(addon.price) * Number(addon.quantity)) }}</td>
                                            <td class="px-4 py-2"></td>
                                            <td class="px-4 py-2 text-right">
                                                <button v-if="mode !== 'view'" type="button" @click="removeAddon(item, addonIdx)" class="p-1 text-gray-400 hover:text-red-500 rounded">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                    </svg>
                                                </button>
                                            </td>
                                        </tr>
                                    </template>
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
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Click "Add Asset" to attach assets to this estimate</p>
                        </div>
                    </div>

                    <!-- ============================
                         Parts & Accessories
                         ============================ -->
                    <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden">
                        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h2 class="text-base font-semibold text-gray-900 dark:text-white">Parts &amp; Accessories</h2>
                            <button
                                v-if="mode !== 'view'"
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

                        <div v-if="inventoryItems.length > 0" class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-700/50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Item</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide w-24">SKU</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide w-24">Unit Price</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide w-24">Discount</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide w-20">Qty</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide w-28">Total</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Add-ons</th>
                                        <th class="px-4 py-3 w-20"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    <template v-for="(item, index) in inventoryItems" :key="index">
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ item.name }}</td>
                                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400 font-mono text-xs">{{ item.sku || '—' }}</td>
                                            <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">{{ formatCurrency(item.unit_price) }}</td>
                                            <td class="px-4 py-3 text-right text-red-500 dark:text-red-400">
                                                {{ item.discount > 0 ? `-${formatCurrency(item.discount)}` : '—' }}
                                            </td>
                                            <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">{{ item.quantity }}</td>
                                            <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">{{ formatCurrency(lineBaseTotal(item)) }}</td>
                                            <td class="px-4 py-3">
                                                <button
                                                    v-if="mode !== 'view'"
                                                    type="button"
                                                    @click="openAddonModal(item, 'inventory')"
                                                    class="inline-flex items-center gap-1 text-xs text-primary-600 dark:text-primary-400 hover:underline"
                                                >
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                                    </svg>
                                                    Add-ons ({{ (item.addons || []).length }})
                                                </button>
                                                <span v-else class="text-xs text-gray-400">{{ (item.addons || []).length }} add-on(s)</span>
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="flex items-center justify-end gap-1">
                                                    <button v-if="mode !== 'view'" type="button" @click="openEditItemModal(index)" class="p-1 text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 rounded transition-colors" title="Edit">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                        </svg>
                                                    </button>
                                                    <button v-if="mode !== 'view'" type="button" @click="removeLineItem(index)" class="p-1 text-gray-400 hover:text-red-600 dark:hover:text-red-400 rounded transition-colors" title="Remove">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <!-- Add-ons sub-rows -->
                                        <tr v-for="(addon, addonIdx) in (item.addons || [])" :key="`inv-addon-${index}-${addonIdx}`" class="bg-primary-50/40 dark:bg-primary-900/10">
                                            <td class="pl-10 pr-4 py-2 text-xs text-gray-600 dark:text-gray-400 italic" colspan="2">
                                                ↳ {{ addon.name }}
                                            </td>
                                            <td class="px-4 py-2 text-right text-xs text-gray-500 dark:text-gray-400">{{ formatCurrency(addon.price) }}</td>
                                            <td class="px-4 py-2 text-right text-xs text-gray-400">—</td>
                                            <td class="px-4 py-2 text-right text-xs text-gray-500 dark:text-gray-400">{{ addon.quantity }}</td>
                                            <td class="px-4 py-2 text-right text-xs font-medium text-gray-700 dark:text-gray-300">{{ formatCurrency(Number(addon.price) * Number(addon.quantity)) }}</td>
                                            <td class="px-4 py-2"></td>
                                            <td class="px-4 py-2 text-right">
                                                <button v-if="mode !== 'view'" type="button" @click="removeAddon(item, addonIdx)" class="p-1 text-gray-400 hover:text-red-500 rounded">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                    </svg>
                                                </button>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                                <tfoot class="bg-gray-50 dark:bg-gray-700/50 border-t-2 border-gray-200 dark:border-gray-600">
                                    <tr>
                                        <td colspan="5" class="px-4 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">Parts &amp; Accessories Subtotal</td>
                                        <td class="px-4 py-3 text-right text-base font-bold text-gray-900 dark:text-white">{{ formatCurrency(inventorySubtotal) }}</td>
                                        <td colspan="2"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

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
                        <div class="flex justify-between items-center px-5 py-4 bg-gray-700 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                            <span class="text-sm font-semibold text-white">Actions</span>
                        </div>

                        <div class="p-5 space-y-3">
                            <button
                                v-if="mode !== 'view'"
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
                                {{ form.processing ? 'Saving...' : (mode === 'edit' ? 'Save Changes' : 'Create Estimate') }}
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

                    <!-- Estimate Totals -->
                    <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-600 bg-gray-700 dark:bg-gray-700">
                            <span class="text-sm font-semibold text-white">Estimate Total</span>
                        </div>
                        <div class="p-5 space-y-2.5">
                            <!-- Assets -->
                            <template v-if="assetItems.length > 0">
                                <div class="flex justify-between items-center text-sm">
                                    <span class="text-gray-500 dark:text-gray-400">Assets</span>
                                    <span class="text-gray-700 dark:text-gray-300">{{ formatCurrency(assetBaseSubtotal) }}</span>
                                </div>
                                <div v-if="assetAddonSubtotal > 0" class="flex justify-between items-center text-sm pl-3 border-l-2 border-primary-200 dark:border-primary-800">
                                    <span class="text-gray-400 dark:text-gray-500">Asset Add-ons</span>
                                    <span class="text-gray-500 dark:text-gray-400">+ {{ formatCurrency(assetAddonSubtotal) }}</span>
                                </div>
                            </template>

                            <!-- Parts & Accessories -->
                            <template v-if="inventoryItems.length > 0">
                                <div class="flex justify-between items-center text-sm">
                                    <span class="text-gray-500 dark:text-gray-400">Parts &amp; Acc.</span>
                                    <span class="text-gray-700 dark:text-gray-300">{{ formatCurrency(inventoryBaseSubtotal) }}</span>
                                </div>
                                <div v-if="inventoryAddonSubtotal > 0" class="flex justify-between items-center text-sm pl-3 border-l-2 border-primary-200 dark:border-primary-800">
                                    <span class="text-gray-400 dark:text-gray-500">Parts Add-ons</span>
                                    <span class="text-gray-500 dark:text-gray-400">+ {{ formatCurrency(inventoryAddonSubtotal) }}</span>
                                </div>
                            </template>

                            <div class="flex justify-between items-center text-sm pt-2 border-t border-gray-100 dark:border-gray-700">
                                <span class="font-medium text-gray-700 dark:text-gray-300">Subtotal</span>
                                <span class="font-semibold text-gray-900 dark:text-white">{{ formatCurrency(combinedSubtotal) }}</span>
                            </div>
                            <div class="flex justify-between items-center text-sm">
                                <span class="text-gray-500 dark:text-gray-400">Tax ({{ form.tax_rate }}%)</span>
                                <span class="text-gray-700 dark:text-gray-300">{{ formatCurrency(taxAmount) }}</span>
                            </div>
                            <div class="flex justify-between items-center pt-2 border-t border-gray-200 dark:border-gray-600">
                                <span class="text-base font-bold text-gray-900 dark:text-white">Total</span>
                                <span class="text-xl font-bold text-primary-600 dark:text-primary-400">{{ formatCurrency(grandTotal) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <!-- ============================
             Asset Modal
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
                            {{ assetForm.asset_id && editingAssetIndex !== null ? 'Edit Asset' : 'Add Asset' }}
                        </h3>
                        <button @click="showAssetModal = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <div class="flex-1 overflow-y-auto p-6 space-y-5">

                        <!-- Search (shown when no asset selected) -->
                        <div v-if="!assetForm.itemable_id">
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

                        <!-- Selected asset + details form -->
                        <div v-if="assetForm.itemable_id" class="space-y-4">
                            <div class="flex items-center justify-between p-3 bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-800 rounded-lg">
                                <div>
                                    <div class="font-medium text-primary-900 dark:text-primary-200 text-sm">{{ assetForm.name }}</div>
                                    <div class="text-xs text-primary-600 dark:text-primary-400 mt-0.5">
                                        {{ [assetForm.year, assetForm.make].filter(Boolean).join(' · ') || 'No details' }}
                                    </div>
                                </div>
                                <button
                                    v-if="editingAssetIndex === null"
                                    type="button"
                                    @click="assetForm.itemable_id = null; assetForm.asset_id = null; assetForm.name = ''"
                                    class="text-xs text-primary-500 hover:text-primary-700 dark:hover:text-primary-300"
                                >
                                    Change
                                </button>
                            </div>

                            <div class="grid grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Unit Price</label>
                                    <div class="relative">
                                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">$</span>
                                        <input type="number" v-model="assetForm.unit_price" min="0" step="0.01"
                                            class="w-full pl-7 pr-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent" />
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Discount</label>
                                    <div class="relative">
                                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">$</span>
                                        <input type="number" v-model="assetForm.discount" min="0" step="0.01"
                                            class="w-full pl-7 pr-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent" />
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Quantity <span class="text-red-500">*</span></label>
                                    <input type="number" v-model="assetForm.quantity" min="1" step="1"
                                        class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent" />
                                </div>
                            </div>

                            <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                                <span class="text-sm text-gray-600 dark:text-gray-400">Line Total</span>
                                <span class="text-base font-bold text-gray-900 dark:text-white">
                                    {{ formatCurrency((Number(assetForm.unit_price || 0) * Number(assetForm.quantity || 0)) - Number(assetForm.discount || 0)) }}
                                </span>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Notes</label>
                                <textarea v-model="assetForm.notes" rows="2"
                                    class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent resize-none"
                                    placeholder="Optional notes for this asset..." />
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
                        <button type="button" @click="showAssetModal = false" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">Cancel</button>
                        <button type="button" @click="saveAssetItem" :disabled="!assetForm.itemable_id"
                            class="px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg transition-colors">
                            {{ editingAssetIndex !== null ? 'Update Asset' : 'Add Asset' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ============================
             Inventory Item Modal
             ============================ -->
        <Teleport to="body">
            <div
                v-if="showItemModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
                @click.self="showItemModal = false"
            >
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col overflow-hidden">

                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            {{ lineItemForm.itemable_id && editingLineIndex !== null ? 'Edit Item' : 'Add Inventory Item' }}
                        </h3>
                        <button @click="showItemModal = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <div class="flex-1 overflow-y-auto p-6 space-y-5">

                        <!-- Search -->
                        <div v-if="!lineItemForm.itemable_id">
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

                            <div v-if="itemIsLoading" class="flex justify-center py-8">
                                <svg class="w-6 h-6 animate-spin text-primary-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>
                            </div>

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

                            <div v-else class="text-center py-8 text-gray-400 dark:text-gray-500 text-sm">
                                {{ itemSearchQuery.trim() ? 'No items match your search' : 'No inventory items available' }}
                            </div>

                            <div v-if="itemTotalPages > 1" class="flex items-center justify-between mt-3 pt-3 border-t border-gray-100 dark:border-gray-700">
                                <button type="button" @click="itemCurrentPage--; fetchItems()" :disabled="itemCurrentPage <= 1" class="text-xs text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 disabled:opacity-40">← Prev</button>
                                <span class="text-xs text-gray-400">Page {{ itemCurrentPage }} / {{ itemTotalPages }}</span>
                                <button type="button" @click="itemCurrentPage++; fetchItems()" :disabled="itemCurrentPage >= itemTotalPages" class="text-xs text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 disabled:opacity-40">Next →</button>
                            </div>
                        </div>

                        <!-- Selected item + details form -->
                        <div v-if="lineItemForm.itemable_id" class="space-y-4">
                            <div class="flex items-center justify-between p-3 bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-800 rounded-lg">
                                <div>
                                    <div class="font-medium text-primary-900 dark:text-primary-200 text-sm">{{ lineItemForm.name }}</div>
                                    <div class="text-xs text-primary-600 dark:text-primary-400 mt-0.5">{{ lineItemForm.sku || 'No SKU' }}</div>
                                </div>
                                <button
                                    v-if="editingLineIndex === null"
                                    type="button"
                                    @click="lineItemForm.itemable_id = null; lineItemForm.inventory_item_id = null; lineItemForm.name = ''"
                                    class="text-xs text-primary-500 hover:text-primary-700 dark:hover:text-primary-300"
                                >
                                    Change
                                </button>
                            </div>

                            <div class="grid grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Unit Price</label>
                                    <div class="relative">
                                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">$</span>
                                        <input type="number" v-model="lineItemForm.unit_price" min="0" step="0.01"
                                            class="w-full pl-7 pr-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent" />
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Discount</label>
                                    <div class="relative">
                                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">$</span>
                                        <input type="number" v-model="lineItemForm.discount" min="0" step="0.01"
                                            class="w-full pl-7 pr-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent" />
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Quantity <span class="text-red-500">*</span></label>
                                    <input type="number" v-model="lineItemForm.quantity" min="1" step="1"
                                        class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent" />
                                </div>
                            </div>

                            <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                                <span class="text-sm text-gray-600 dark:text-gray-400">Line Total</span>
                                <span class="text-base font-bold text-gray-900 dark:text-white">
                                    {{ formatCurrency((Number(lineItemForm.unit_price || 0) * Number(lineItemForm.quantity || 0)) - Number(lineItemForm.discount || 0)) }}
                                </span>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Notes</label>
                                <textarea v-model="lineItemForm.notes" rows="2"
                                    class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent resize-none"
                                    placeholder="Optional notes for this item..." />
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
                        <button type="button" @click="showItemModal = false" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">Cancel</button>
                        <button type="button" @click="saveLineItem" :disabled="!lineItemForm.itemable_id"
                            class="px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg transition-colors">
                            {{ editingLineIndex !== null ? 'Update Item' : 'Add Item' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ============================
             Add-on Modal
             ============================ -->
        <Teleport to="body">
            <div
                v-if="showAddonModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
                @click.self="showAddonModal = false"
            >
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col overflow-hidden">

                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Add Add-on</h3>
                        <button @click="showAddonModal = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <div class="flex-1 overflow-y-auto p-6 space-y-5">

                        <!-- Catalog search -->
                        <div>
                            <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">From Catalog</h4>
                            <div class="relative mb-3">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                    </svg>
                                </div>
                                <input
                                    v-model="addonSearchQuery"
                                    @input="debouncedFetchAddons"
                                    type="text"
                                    placeholder="Search add-ons..."
                                    class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                />
                            </div>

                            <div v-if="addonIsLoading" class="flex justify-center py-6">
                                <svg class="w-6 h-6 animate-spin text-primary-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>
                            </div>

                            <div v-else-if="addonRecords.length > 0" class="space-y-1.5 max-h-44 overflow-y-auto">
                                <button
                                    v-for="addon in addonRecords"
                                    :key="addon.id"
                                    type="button"
                                    @click="selectAddon(addon)"
                                    class="w-full text-left p-3 border border-gray-200 dark:border-gray-700 rounded-lg hover:border-primary-400 dark:hover:border-primary-500 hover:bg-primary-50 dark:hover:bg-primary-900/20 transition-all group"
                                >
                                    <div class="flex items-center justify-between gap-3">
                                        <div>
                                            <div class="font-medium text-gray-900 dark:text-white text-sm group-hover:text-primary-700 dark:group-hover:text-primary-300">{{ addon.name }}</div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 flex gap-3">
                                                <span>{{ formatCurrency(addon.default_price) }}</span>
                                                <span v-if="addon.description">{{ addon.description }}</span>
                                            </div>
                                        </div>
                                        <svg class="w-4 h-4 text-gray-400 group-hover:text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                        </svg>
                                    </div>
                                </button>
                            </div>

                            <div v-else class="text-center py-6 text-gray-400 dark:text-gray-500 text-sm">
                                {{ addonSearchQuery.trim() ? 'No add-ons match your search' : 'No catalog add-ons available' }}
                            </div>
                        </div>

                        <!-- Custom add-on -->
                        <div class="border-t border-gray-200 dark:border-gray-700 pt-5">
                            <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">Or Create Custom Add-on</h4>
                            <div class="grid grid-cols-2 gap-4">
                                <div class="col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Name <span class="text-red-500">*</span></label>
                                    <input
                                        v-model="customAddonForm.name"
                                        type="text"
                                        placeholder="Add-on name..."
                                        class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Price</label>
                                    <div class="relative">
                                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">$</span>
                                        <input type="number" v-model.number="customAddonForm.price" step="0.01" min="0"
                                            class="w-full pl-7 pr-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent" />
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Quantity</label>
                                    <input type="number" v-model.number="customAddonForm.quantity" min="1"
                                        class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent" />
                                </div>
                                <div class="col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Notes</label>
                                    <input v-model="customAddonForm.notes" type="text" placeholder="Optional notes..."
                                        class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent" />
                                </div>
                            </div>
                            <button
                                type="button"
                                @click="addCustomAddon"
                                :disabled="!customAddonForm.name.trim()"
                                class="mt-4 inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg transition-colors"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                Add Custom Add-on
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
                        <button type="button" @click="showAddonModal = false" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ============================
             Billing Address Confirm Modal
             ============================ -->
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
                    class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4"
                    @click.self="dismissAddressConfirm"
                >
                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden">

                        <!-- Header -->
                        <div class="flex items-start gap-3 px-6 pt-6 pb-4">
                            <div class="flex-shrink-0 w-9 h-9 rounded-full bg-primary-100 dark:bg-primary-900/40 flex items-center justify-center">
                                <svg class="w-5 h-5 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-md font-semibold text-gray-900 dark:text-white">Use customer's address?</h3>
                                <p class="text-md text-gray-500 dark:text-gray-400 mt-0.5">Set this as the billing address for the estimate</p>
                            </div>
                        </div>

                        <!-- Address preview -->
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

                        <!-- Actions -->
                        <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/30">
                            <button
                                type="button"
                                @click="dismissAddressConfirm"
                                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors"
                            >
                                No, keep blank
                            </button>
                            <button
                                type="button"
                                @click="confirmUseBillingAddress"
                                class="px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors"
                            >
                                Yes, use this address
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>