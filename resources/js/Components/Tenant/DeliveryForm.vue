<script setup>
import RecordSelect from '@/Components/Tenant/RecordSelect.vue';
import AddressAutocomplete from '@/Components/AddressAutocomplete.vue';
import ContactAddressAutocomplete from '@/Components/ContactAddressAutocomplete.vue';
import AssetLineModal from '@/Components/Tenant/AssetLineModal.vue';
import { useForm, router } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
    record: { type: Object, default: null },
    mode: { type: String, default: 'create' },
    customerAddresses: { type: Array, default: () => [] },
    enumOptions: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['saved', 'cancelled']);

const isEdit = computed(() => props.mode === 'edit' && props.record);

/** Create page opened with ?transaction_id= or ?work_order_id= (prefill from RecordController). */
const deliveryCreateFromTransaction = computed(
    () => props.mode === 'create' && !!(props.record?.transaction_id),
);
const deliveryCreateFromWorkOrder = computed(
    () => props.mode === 'create' && !!(props.record?.work_order_id) && !props.record?.transaction_id,
);

const prefillTransactionId = computed(() => props.record?.transaction_id ?? props.record?.transaction?.id ?? null);
const prefillTransactionDisplayName = computed(
    () => props.record?.transaction?.display_name
        || (prefillTransactionId.value != null ? `#${prefillTransactionId.value}` : ''),
);

const prefillWorkOrderId = computed(() => props.record?.work_order_id ?? props.record?.work_order?.id ?? null);
const prefillWorkOrderDisplayName = computed(
    () => props.record?.work_order?.display_name
        || (prefillWorkOrderId.value != null ? `#${prefillWorkOrderId.value}` : ''),
);

/** Prefilled create from transaction / work order — same contract as invoice `txLocked`. */
const sourcePrefillLocked = computed(
    () => props.mode === 'create' && !!(props.record?.transaction_id || props.record?.work_order_id),
);

const tomorrowNoon = (() => {
    const d = new Date();
    d.setDate(d.getDate() + 1);
    d.setHours(12, 0, 0, 0);
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T12:00`;
})();

const toLocalDatetime = (value) => {
    if (!value) return '';
    try {
        const d = new Date(value);
        if (isNaN(d.getTime())) return '';
        const pad = (n) => String(n).padStart(2, '0');
        return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
    } catch { return ''; }
};

const mapRecordItem = (it) => ({
    id: it.id ?? null,
    position: it.position ?? 0,
    asset_id: it.asset_id ?? it.asset_unit?.asset_id ?? it.assetUnit?.asset_id ?? null,
    asset_variant_id: it.asset_variant_id ?? it.assetVariant?.id ?? null,
    asset_unit_id: it.asset_unit_id ?? it.assetUnit?.id ?? null,
    name: it.name ?? it.asset_unit?.asset?.display_name ?? it.assetUnit?.asset?.display_name ?? '',
    description: it.description ?? '',
    quantity: 1,
    unit_price: Number(it.unit_price ?? 0),
    delivered_at: it.delivered_at ?? null,
    asset_unit: it.asset_unit ?? it.assetUnit ?? null,
    asset_variant: it.asset_variant ?? it.assetVariant ?? null,
});

/** Snapshot for initial form state (Inertia first paint). */
const initialRecord = props.record ?? {};

const form = useForm({
    customer_id: initialRecord.customer_id ?? null,
    work_order_id: initialRecord.work_order_id ?? null,
    transaction_id: initialRecord.transaction_id ?? null,
    subsidiary_id: initialRecord.subsidiary_id ?? null,
    location_id: initialRecord.location_id ?? null,
    technician_id: initialRecord.technician_id ?? null,
    scheduled_at: toLocalDatetime(initialRecord.scheduled_at) || tomorrowNoon,
    estimated_arrival_at: toLocalDatetime(initialRecord.estimated_arrival_at) || null,
    status: initialRecord.status ?? 'scheduled',
    delivery_to_type: initialRecord.delivery_to_type ?? 'contact_address',
    contact_address_id: initialRecord.contact_address_id ?? null,
    delivery_location_id: initialRecord.delivery_location_id ?? null,
    internal_notes: initialRecord.internal_notes ?? '',
    customer_notes: initialRecord.customer_notes ?? '',
    address_line_1: initialRecord.address_line_1 ?? '',
    address_line_2: initialRecord.address_line_2 ?? '',
    city: initialRecord.city ?? '',
    state: initialRecord.state ?? '',
    postal_code: initialRecord.postal_code ?? '',
    country: initialRecord.country ?? '',
    latitude: initialRecord.latitude ?? null,
    longitude: initialRecord.longitude ?? null,
    items: (initialRecord.items ?? []).map(mapRecordItem),
});

const sourceMode = ref(form.work_order_id ? 'work_order' : form.transaction_id ? 'transaction' : 'none');

const selectedCustomerLabel = ref(initialRecord.customer?.display_name ?? initialRecord.customer?.contact?.display_name ?? '');
const selectedWorkOrderLabel = ref(initialRecord.work_order?.display_name ?? '');
const selectedTransactionLabel = ref(initialRecord.transaction?.display_name ?? '');
const selectedTechnicianLabel = ref(initialRecord.technician?.name ?? '');

/** Parent row for RecordSelect embedded relation stubs (prefill / edit). */
const record = computed(() => props.record ?? {});

const statusOptions = computed(() => props.enumOptions?.delivery_status || [
    { id: 'scheduled', name: 'Scheduled' },
    { id: 'confirmed', name: 'Confirmed' },
    { id: 'en_route', name: 'En Route' },
    { id: 'delivered', name: 'Delivered' },
    { id: 'cancelled', name: 'Cancelled' },
    { id: 'rescheduled', name: 'Rescheduled' },
]);

const statusOptionValue = (o) => o.value ?? o.id;
const statusOptionLabel = (o) => o.name ?? o.label ?? String(statusOptionValue(o));

const sourceLabel = computed(() => {
    if (sourceMode.value === 'work_order' && selectedWorkOrderLabel.value) return selectedWorkOrderLabel.value;
    if (sourceMode.value === 'transaction' && selectedTransactionLabel.value) return selectedTransactionLabel.value;
    return 'Standalone';
});

/* ─── Source handling ─── */
const loadingSource = ref(false);
const loadSourceItems = async (type, id) => {
    if (!type || !id) return;
    loadingSource.value = true;
    try {
        const { data } = await axios.get(route('deliveries.source-items'), { params: { type, id } });
        if (data.customer_id && !form.customer_id) form.customer_id = data.customer_id;
        const newItems = (data.items || []).map((i, idx) => ({
            id: null,
            position: idx,
            asset_id: i.asset_unit?.asset_id ?? null,
            asset_variant_id: i.asset_variant_id ?? null,
            asset_unit_id: i.asset_unit_id ?? null,
            name: i.name,
            description: i.description ?? '',
            quantity: 1,
            unit_price: Number(i.unit_price ?? 0),
            delivered_at: null,
            asset_unit: i.asset_unit ?? null,
            asset_variant: i.asset_variant ?? null,
        }));
        if (newItems.length) {
            if (!form.items.length || confirm('Replace current items with those from the selected source?')) {
                form.items = newItems;
            }
        }
        if (type === 'transaction') selectedTransactionLabel.value = data.source?.display_name ?? '';
        if (type === 'work_order') selectedWorkOrderLabel.value = data.source?.display_name ?? '';
    } catch (err) {
        console.error('Failed to load source items', err);
    } finally {
        loadingSource.value = false;
    }
};

const onWorkOrderSelected = async (id) => {
    if (sourcePrefillLocked.value) return;
    form.work_order_id = id;
    form.transaction_id = null;
    if (id) await loadSourceItems('work_order', id);
};
const onTransactionSelected = async (id) => {
    if (sourcePrefillLocked.value) return;
    form.transaction_id = id;
    form.work_order_id = null;
    if (id) await loadSourceItems('transaction', id);
};

const onSourceModeChange = (mode) => {
    if (sourcePrefillLocked.value) return;
    sourceMode.value = mode;
    if (mode === 'none') {
        form.work_order_id = null;
        form.transaction_id = null;
        selectedWorkOrderLabel.value = '';
        selectedTransactionLabel.value = '';
    } else if (mode === 'work_order') {
        form.transaction_id = null;
        selectedTransactionLabel.value = '';
    } else if (mode === 'transaction') {
        form.work_order_id = null;
        selectedWorkOrderLabel.value = '';
    }
};

/* ─── Customer handling ─── */
const onCustomerSelected = async (id, recordObj) => {
    if (sourcePrefillLocked.value && id !== form.customer_id) return;
    form.customer_id = id;
    form.contact_address_id = null;
    deliveryPickerContactId.value = null;
    selectedCustomerLabel.value = recordObj?.display_name ?? '';
    if (!id) return;
    try {
        const { data } = await axios.get(route('deliveries.customer-details', id));
        if (data.name) selectedCustomerLabel.value = data.name;
        deliveryPickerContactId.value = data.contact_id ?? null;
        if (form.delivery_to_type === 'contact_address' && data.address && !form.address_line_1?.trim()) {
            fillAddress(data.address);
        }
    } catch (err) { console.error(err); }
};

/** Prefill / seed contact id for address picker when customer is already set. */
onMounted(async () => {
    if (!form.customer_id) return;
    try {
        const { data } = await axios.get(route('deliveries.customer-details', form.customer_id));
        deliveryPickerContactId.value = data.contact_id ?? null;
        if (data.name && !selectedCustomerLabel.value) selectedCustomerLabel.value = data.name;
        if (
            props.mode === 'create'
            && form.delivery_to_type === 'contact_address'
            && !form.address_line_1?.trim()
            && data.address
        ) {
            fillAddress(data.address);
        }
    } catch (err) {
        console.error(err);
    }
});

/* ─── Deliver-to handling ─── */
const fillAddress = (addr) => {
    if (!addr) return;
    form.address_line_1 = addr.address_line_1 ?? addr.street ?? '';
    form.address_line_2 = addr.address_line_2 ?? addr.unit ?? '';
    form.city = addr.city ?? '';
    form.state = addr.state ?? addr.stateCode ?? '';
    form.postal_code = addr.postal_code ?? addr.postalCode ?? '';
    form.country = addr.country ?? '';
    form.latitude = addr.latitude ?? null;
    form.longitude = addr.longitude ?? null;
};

const onDeliverToTypeChange = (next) => {
    form.delivery_to_type = next;
    if (next === 'contact_address') {
        form.delivery_location_id = null;
    } else if (next === 'delivery_location') {
        form.contact_address_id = null;
    } else {
        form.contact_address_id = null;
        form.delivery_location_id = null;
    }
};

/** Same picker list as props.customerAddresses; refreshed when opening modal or after adding an address. */
const deliveryPickerAddresses = ref([]);
const deliveryPickerContactId = ref(null);
const showDeliveryAddressPicker = ref(false);
const isFetchingDeliveryAddresses = ref(false);
const postingDeliveryAddress = ref(false);

const syncDeliveryPickerAddressesFromProps = () => {
    deliveryPickerAddresses.value = Array.isArray(props.customerAddresses) ? [...props.customerAddresses] : [];
};

watch(
    () => props.customerAddresses,
    () => syncDeliveryPickerAddressesFromProps(),
    { deep: true, immediate: true },
);

const dismissDeliveryAddressPicker = () => {
    showDeliveryAddressPicker.value = false;
    isFetchingDeliveryAddresses.value = false;
};

const openDeliveryContactAddressPicker = async () => {
    if (!form.customer_id) return;
    showDeliveryAddressPicker.value = true;
    isFetchingDeliveryAddresses.value = true;
    deliveryPickerAddresses.value = [];
    try {
        const { data } = await axios.get(route('deliveries.customer-details', form.customer_id));
        deliveryPickerContactId.value = data.contact_id ?? null;
        deliveryPickerAddresses.value = Array.isArray(data.addresses) ? data.addresses : [];
    } catch (err) {
        console.error(err);
        syncDeliveryPickerAddressesFromProps();
    } finally {
        isFetchingDeliveryAddresses.value = false;
    }
};

const selectDeliveryContactAddress = (addr) => {
    if (!addr) return;
    form.contact_address_id = addr.id ?? null;
    fillAddress(addr);
    dismissDeliveryAddressPicker();
};

const onDeliveryContactAddressSaved = (payload) => {
    if (!deliveryPickerContactId.value) return;
    postingDeliveryAddress.value = true;
    router.post(route('contacts.addresses.store', deliveryPickerContactId.value), payload, {
        preserveScroll: true,
        onFinish: () => {
            postingDeliveryAddress.value = false;
        },
        onSuccess: async () => {
            fillAddress({
                address_line_1: payload.address_line_1,
                address_line_2: payload.address_line_2 ?? null,
                city: payload.city,
                state: payload.state,
                postal_code: payload.postal_code,
                country: payload.country,
                latitude: payload.latitude ?? null,
                longitude: payload.longitude ?? null,
            });
            try {
                const { data } = await axios.get(route('deliveries.customer-details', form.customer_id));
                deliveryPickerAddresses.value = Array.isArray(data.addresses) ? data.addresses : [];
                const match = deliveryPickerAddresses.value.find(
                    (a) => (a.address_line_1 || '') === (payload.address_line_1 || '')
                        && (a.postal_code || '') === (payload.postal_code || ''),
                );
                form.contact_address_id = match?.id ?? null;
            } catch (e) {
                console.error(e);
            }
            dismissDeliveryAddressPicker();
        },
    });
};

const onDeliveryLocationSelected = async (id, recordObj) => {
    form.delivery_location_id = id;
    if (recordObj) {
        fillAddress(recordObj);
        return;
    }
    if (!id) return;
    try {
        const { data } = await axios.get(route('delivery-locations.options'), { params: { id } });
        const loc = (data.records ?? [])[0] ?? null;
        if (loc) fillAddress(loc);
    } catch (err) { console.error(err); }
};

const onAddressUpdate = (data) => {
    form.address_line_1 = data.street || '';
    form.address_line_2 = data.unit || '';
    form.city = data.city || '';
    form.state = data.stateCode || data.state || '';
    form.postal_code = data.postalCode || '';
    form.country = data.country || '';
    form.latitude = data.latitude ?? null;
    form.longitude = data.longitude ?? null;
};

/* ─── Items management ─── */
const showAssetModal = ref(false);
const editingIndex = ref(null);
const emptyAssetModel = () => ({
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
});
const assetFormModel = ref(emptyAssetModel());

const openAddItem = () => {
    if (sourcePrefillLocked.value) return;
    editingIndex.value = null;
    assetFormModel.value = emptyAssetModel();
    showAssetModal.value = true;
};

const openEditItem = (idx) => {
    if (sourcePrefillLocked.value) return;
    const item = form.items[idx];
    editingIndex.value = idx;
    assetFormModel.value = {
        ...emptyAssetModel(),
        itemable_id: item.asset_id,
        asset_id: item.asset_id,
        name: item.name ?? '',
        quantity: 1,
        unit_price: Number(item.unit_price ?? 0),
        notes: item.description ?? '',
        has_variants: !!item.asset_variant_id,
        asset_variant_id: item.asset_variant_id ?? null,
        variant_display_name: item.asset_variant?.display_name ?? '',
        asset_unit_id: item.asset_unit_id ?? null,
        unit_display_name: item.asset_unit?.display_name ?? '',
        asset_description: item.description ?? '',
    };
    showAssetModal.value = true;
};

const saveAssetItem = () => {
    const src = assetFormModel.value;
    const newItem = {
        id: editingIndex.value !== null ? form.items[editingIndex.value].id : null,
        position: editingIndex.value !== null ? form.items[editingIndex.value].position : form.items.length,
        asset_id: src.asset_id,
        asset_variant_id: src.asset_variant_id,
        asset_unit_id: src.asset_unit_id,
        name: src.name,
        description: src.notes || '',
        quantity: 1,
        unit_price: Number(src.unit_price || 0),
        delivered_at: editingIndex.value !== null ? form.items[editingIndex.value].delivered_at : null,
        asset_unit: src.asset_unit_id ? { id: src.asset_unit_id, display_name: src.unit_display_name } : null,
        asset_variant: src.asset_variant_id ? { id: src.asset_variant_id, display_name: src.variant_display_name } : null,
    };
    if (editingIndex.value !== null) {
        form.items[editingIndex.value] = newItem;
    } else {
        form.items.push(newItem);
    }
    showAssetModal.value = false;
};

const removeItem = (idx) => {
    if (sourcePrefillLocked.value) return;
    form.items.splice(idx, 1);
};

const itemLabel = (item) => {
    if (item.asset_unit?.display_name) return item.asset_unit.display_name;
    if (item.asset_variant?.display_name) return item.asset_variant.display_name;
    return item.name || 'Asset';
};

/* ─── Submit ─── */
const submit = () => {
    const url = isEdit.value
        ? route('deliveries.update', props.record.id)
        : route('deliveries.store');
    const method = isEdit.value ? 'put' : 'post';
    form.submit(method, url, {
        preserveScroll: true,
        onSuccess: () => emit('saved'),
    });
};

/* ─── Field configs for RecordSelect ─── */
const customerField = computed(() => ({ type: 'record', typeDomain: 'Customer', label: 'Customer', required: true }));
const workOrderField = computed(() => ({ type: 'record', typeDomain: 'WorkOrder', label: 'Work Order' }));
const transactionField = computed(() => ({ type: 'record', typeDomain: 'Transaction', label: 'Transaction' }));
const deliveryLocationField = computed(() => ({ type: 'record', typeDomain: 'DeliveryLocation', label: 'Delivery Location' }));
const technicianField = computed(() => ({ type: 'record', typeDomain: 'User', label: 'Technician' }));

const deliverToTypeLabel = computed(() => {
    const map = { contact_address: 'Customer address', delivery_location: 'Common location', custom: 'Custom' };
    return map[form.delivery_to_type] ?? '—';
});

const addressSummary = computed(() => {
    const parts = [form.address_line_1, form.city, form.state, form.postal_code].filter(Boolean);
    return parts.join(', ') || '—';
});
</script>

<template>
    <div class="w-full flex flex-col space-y-6">
        <!-- Banner: creating from transaction (same pattern as InvoiceForm) -->
        <div
            v-if="deliveryCreateFromTransaction"
            class="rounded-lg border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-900/30 px-4 py-3 flex items-start gap-3"
        >
            <span class="material-icons text-blue-600 dark:text-blue-400 shrink-0 mt-0.5">local_shipping</span>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-blue-900 dark:text-blue-200">
                    Creating delivery from Transaction →
                    <a
                        v-if="prefillTransactionId"
                        :href="route('transactions.show', prefillTransactionId)"
                        class="underline decoration-dotted hover:text-blue-700 dark:hover:text-blue-100"
                    >{{ prefillTransactionDisplayName }}</a>
                    <span v-else>{{ prefillTransactionDisplayName }}</span>
                </p>
                <p class="text-xs text-blue-800/80 dark:text-blue-300/80 mt-0.5">
                    Fields populated from the transaction are locked. To change them, update the transaction or start from a different one.
                </p>
            </div>
        </div>

        <!-- Banner: creating from work order -->
        <div
            v-if="deliveryCreateFromWorkOrder"
            class="rounded-lg border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-900/30 px-4 py-3 flex items-start gap-3"
        >
            <span class="material-icons text-blue-600 dark:text-blue-400 shrink-0 mt-0.5">local_shipping</span>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-blue-900 dark:text-blue-200">
                    Creating delivery from Work Order →
                    <a
                        v-if="prefillWorkOrderId"
                        :href="route('workorders.show', prefillWorkOrderId)"
                        class="underline decoration-dotted hover:text-blue-700 dark:hover:text-blue-100"
                    >{{ prefillWorkOrderDisplayName }}</a>
                    <span v-else>{{ prefillWorkOrderDisplayName }}</span>
                </p>
                <p class="text-xs text-blue-800/80 dark:text-blue-300/80 mt-0.5">
                    Fields populated from the work order are locked. To change them, update the work order or start from a different one.
                </p>
            </div>
        </div>

        <form @submit.prevent="submit">
            <div class="grid gap-6 lg:grid-cols-12">

                <!-- ============================
                     Main Column
                     ============================ -->
                <div class="lg:col-span-8 space-y-6">

                    <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden">

                        <!-- Gradient header -->
                        <div class="bg-gradient-to-r from-primary-600 to-primary-700 dark:from-primary-700 dark:to-primary-800 px-6 py-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h1 class="text-2xl font-bold text-white">
                                        {{ isEdit ? 'EDIT DELIVERY' : 'NEW DELIVERY' }}
                                    </h1>
                                    <p class="text-primary-100 text-sm mt-1">
                                        {{ isEdit ? 'Update delivery details' : 'Schedule and configure asset delivery' }}
                                    </p>
                                </div>
                                <div v-if="props.record?.sequence" class="text-right">
                                    <div class="text-primary-200 text-xs font-medium">Delivery #</div>
                                    <div class="text-white text-lg font-mono">{{ props.record.sequence }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="p-6 space-y-6">

                            <!-- Customer & Source + Schedule -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                                <!-- Left: Customer & Source -->
                                <div class="space-y-4">
                                    <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700">
                                        Customer &amp; source
                                    </h3>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                            Customer <span class="text-red-500">*</span>
                                        </label>
                                        <RecordSelect
                                            id="customer_id"
                                            :field="customerField"
                                            :record="record"
                                            :model-value="form.customer_id"
                                            :disabled="sourcePrefillLocked"
                                            @update:model-value="(id) => onCustomerSelected(id)"
                                            @record-selected="(r) => onCustomerSelected(r.id, r)"
                                            field-key="customer_id"
                                        />
                                        <p v-if="form.errors.customer_id" class="mt-1 text-xs text-red-500">{{ form.errors.customer_id }}</p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Source</label>
                                        <div class="grid grid-cols-3 gap-2 mb-3">
                                            <button
                                                type="button"
                                                :disabled="sourcePrefillLocked"
                                                @click="onSourceModeChange('none')"
                                                :class="[
                                                    'px-3 py-2 rounded-lg border text-sm text-center transition-colors',
                                                    sourceMode === 'none'
                                                        ? 'border-primary-500 bg-primary-50 text-primary-700 dark:bg-primary-900/20 dark:text-primary-200'
                                                        : 'border-gray-200 dark:border-gray-600 hover:border-gray-300 dark:hover:border-gray-500 text-gray-600 dark:text-gray-300',
                                                    sourcePrefillLocked ? 'opacity-50 cursor-not-allowed' : '',
                                                ]"
                                            >Standalone</button>
                                            <button
                                                type="button"
                                                :disabled="sourcePrefillLocked"
                                                @click="onSourceModeChange('work_order')"
                                                :class="[
                                                    'px-3 py-2 rounded-lg border text-sm text-center transition-colors',
                                                    sourceMode === 'work_order'
                                                        ? 'border-primary-500 bg-primary-50 text-primary-700 dark:bg-primary-900/20 dark:text-primary-200'
                                                        : 'border-gray-200 dark:border-gray-600 hover:border-gray-300 dark:hover:border-gray-500 text-gray-600 dark:text-gray-300',
                                                    sourcePrefillLocked ? 'opacity-50 cursor-not-allowed' : '',
                                                ]"
                                            >Work Order</button>
                                            <button
                                                type="button"
                                                :disabled="sourcePrefillLocked"
                                                @click="onSourceModeChange('transaction')"
                                                :class="[
                                                    'px-3 py-2 rounded-lg border text-sm text-center transition-colors',
                                                    sourceMode === 'transaction'
                                                        ? 'border-primary-500 bg-primary-50 text-primary-700 dark:bg-primary-900/20 dark:text-primary-200'
                                                        : 'border-gray-200 dark:border-gray-600 hover:border-gray-300 dark:hover:border-gray-500 text-gray-600 dark:text-gray-300',
                                                    sourcePrefillLocked ? 'opacity-50 cursor-not-allowed' : '',
                                                ]"
                                            >Transaction</button>
                                        </div>

                                        <div v-if="sourceMode === 'work_order'">
                                            <RecordSelect
                                                id="work_order_id"
                                                :field="workOrderField"
                                                :record="record"
                                                :model-value="form.work_order_id"
                                                :disabled="sourcePrefillLocked"
                                                @update:model-value="onWorkOrderSelected"
                                                @record-selected="(r) => onWorkOrderSelected(r.id)"
                                                field-key="work_order_id"
                                            />
                                        </div>
                                        <div v-if="sourceMode === 'transaction'">
                                            <RecordSelect
                                                id="transaction_id"
                                                :field="transactionField"
                                                :record="record"
                                                :model-value="form.transaction_id"
                                                :disabled="sourcePrefillLocked"
                                                @update:model-value="onTransactionSelected"
                                                @record-selected="(r) => onTransactionSelected(r.id)"
                                                field-key="transaction_id"
                                            />
                                        </div>
                                        <div v-if="loadingSource" class="mt-2 text-xs text-gray-500 flex items-center gap-2">
                                            <span class="material-icons text-sm animate-spin">sync</span> Loading source items…
                                        </div>
                                    </div>
                                </div>

                                <!-- Right: Schedule -->
                                <div class="space-y-4">
                                    <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700">
                                        Schedule
                                    </h3>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Status</label>
                                        <select v-model="form.status" class="input-style w-full">
                                            <option
                                                v-for="opt in statusOptions"
                                                :key="String(statusOptionValue(opt))"
                                                :value="statusOptionValue(opt)"
                                            >
                                                {{ statusOptionLabel(opt) }}
                                            </option>
                                        </select>
                                        <p v-if="form.errors.status" class="mt-1 text-xs text-red-500">{{ form.errors.status }}</p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                            Scheduled <span class="text-red-500">*</span>
                                        </label>
                                        <input type="datetime-local" v-model="form.scheduled_at" class="input-style" />
                                        <p v-if="form.errors.scheduled_at" class="mt-1 text-xs text-red-500">{{ form.errors.scheduled_at }}</p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Estimated Arrival</label>
                                        <input type="datetime-local" v-model="form.estimated_arrival_at" class="input-style" />
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Technician</label>
                                        <RecordSelect
                                            id="technician_id"
                                            :field="technicianField"
                                            :record="record"
                                            v-model="form.technician_id"
                                            @record-selected="(r) => selectedTechnicianLabel = r?.name ?? ''"
                                            field-key="technician_id"
                                        />
                                    </div>
                                </div>
                            </div>

                            <!-- Assets to Deliver -->
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-5">
                                <div class="flex items-center justify-between mb-4">
                                    <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                                        Assets to deliver
                                    </h3>
                                    <button
                                        type="button"
                                        :disabled="sourcePrefillLocked"
                                        @click="openAddItem"
                                        :class="[
                                            'inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium rounded-md',
                                            sourcePrefillLocked
                                                ? 'text-gray-400 bg-gray-100 cursor-not-allowed dark:bg-gray-700 dark:text-gray-500'
                                                : 'text-primary-700 bg-primary-50 hover:bg-primary-100 dark:bg-primary-900/20 dark:text-primary-200',
                                        ]"
                                    >
                                        <span class="material-icons text-sm">add</span>
                                        Add Asset
                                    </button>
                                </div>

                                <div v-if="!form.items.length" class="py-10 text-center text-sm text-gray-500 dark:text-gray-400 border border-dashed border-gray-200 dark:border-gray-700 rounded-lg">
                                    No assets yet. Add one manually or link a source above to auto-populate.
                                </div>
                                <div v-else class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                        <thead class="bg-gray-50 dark:bg-gray-900/40">
                                            <tr>
                                                <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Asset</th>
                                                <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Variant</th>
                                                <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Unit</th>
                                                <th class="px-2 py-2 w-10"></th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700 bg-white dark:bg-gray-800">
                                            <tr v-for="(item, idx) in form.items" :key="idx">
                                                <td class="px-4 py-2 text-gray-900 dark:text-white">
                                                    <button
                                                        v-if="!sourcePrefillLocked"
                                                        type="button"
                                                        class="text-primary-600 hover:underline font-medium"
                                                        @click="openEditItem(idx)"
                                                    >
                                                        {{ itemLabel(item) }}
                                                    </button>
                                                    <span v-else class="font-medium text-gray-900 dark:text-white">{{ itemLabel(item) }}</span>
                                                </td>
                                                <td class="px-4 py-2 text-gray-600 dark:text-gray-300">{{ item.asset_variant?.display_name ?? '—' }}</td>
                                                <td class="px-4 py-2 text-gray-600 dark:text-gray-300">{{ item.asset_unit?.display_name ?? '—' }}</td>
                                                <td class="px-2 py-2 text-right">
                                                    <button
                                                        type="button"
                                                        :disabled="sourcePrefillLocked"
                                                        @click="removeItem(idx)"
                                                        :class="sourcePrefillLocked ? 'text-gray-300 cursor-not-allowed dark:text-gray-600' : 'text-gray-400 hover:text-red-500'"
                                                    >
                                                        <span class="material-icons text-base">delete</span>
                                                    </button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Delivery Address (layout aligned with invoice billing address) -->
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-5">
                                <div class="grid grid-cols-3 gap-2 mb-4">
                                    <button
                                        type="button"
                                        @click="onDeliverToTypeChange('contact_address')"
                                        :class="[
                                            'px-3 py-2 rounded-lg border text-sm text-center transition-colors',
                                            form.delivery_to_type === 'contact_address'
                                                ? 'border-primary-500 bg-primary-50 text-primary-700 dark:bg-primary-900/20 dark:text-primary-200'
                                                : 'border-gray-200 dark:border-gray-600 hover:border-gray-300 text-gray-600 dark:text-gray-300'
                                        ]"
                                    >Customer Address</button>
                                    <button
                                        type="button"
                                        @click="onDeliverToTypeChange('delivery_location')"
                                        :class="[
                                            'px-3 py-2 rounded-lg border text-sm text-center transition-colors',
                                            form.delivery_to_type === 'delivery_location'
                                                ? 'border-primary-500 bg-primary-50 text-primary-700 dark:bg-primary-900/20 dark:text-primary-200'
                                                : 'border-gray-200 dark:border-gray-600 hover:border-gray-300 text-gray-600 dark:text-gray-300'
                                        ]"
                                    >Common Location</button>
                                    <button
                                        type="button"
                                        @click="onDeliverToTypeChange('custom')"
                                        :class="[
                                            'px-3 py-2 rounded-lg border text-sm text-center transition-colors',
                                            form.delivery_to_type === 'custom'
                                                ? 'border-primary-500 bg-primary-50 text-primary-700 dark:bg-primary-900/20 dark:text-primary-200'
                                                : 'border-gray-200 dark:border-gray-600 hover:border-gray-300 text-gray-600 dark:text-gray-300'
                                        ]"
                                    >Custom</button>
                                </div>

                                <div class="flex items-center justify-between mb-3 gap-3 flex-wrap">
                                    <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                                        Delivery Address
                                    </h3>
                                    <div class="flex items-center gap-3">
                                        <button
                                            v-if="form.delivery_to_type === 'contact_address' && form.customer_id"
                                            type="button"
                                            class="inline-flex items-center gap-1.5 text-xs font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300"
                                            @click="openDeliveryContactAddressPicker"
                                        >
                                            <span class="material-icons text-[16px]">person_pin_circle</span>
                                            Choose from contact
                                        </button>
                                        <span
                                            v-if="form.delivery_to_type === 'contact_address' && isFetchingDeliveryAddresses"
                                            class="text-xs text-primary-600 dark:text-primary-400 animate-pulse"
                                        >
                                            Loading addresses…
                                        </span>
                                    </div>
                                </div>

                                <div v-if="form.delivery_to_type === 'delivery_location'" class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Common Delivery Location</label>
                                    <RecordSelect
                                        id="delivery_location_id"
                                        :field="deliveryLocationField"
                                        :record="record"
                                        :model-value="form.delivery_location_id"
                                        @update:model-value="onDeliveryLocationSelected"
                                        @record-selected="(r) => onDeliveryLocationSelected(r.id, r)"
                                        field-key="delivery_location_id"
                                    />
                                </div>

                                <AddressAutocomplete
                                    :street="form.address_line_1"
                                    :unit="form.address_line_2"
                                    :city="form.city"
                                    :state="form.state"
                                    :stateCode="form.state"
                                    :postalCode="form.postal_code"
                                    :country="form.country"
                                    :latitude="form.latitude"
                                    :longitude="form.longitude"
                                    @update="onAddressUpdate"
                                />
                            </div>

                            <!-- Notes -->
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-5">
                                <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">
                                    Notes
                                </h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-3">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Internal Notes</label>
                                        <textarea rows="3" v-model="form.internal_notes" placeholder="Visible to staff only…" class="input-style" />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Customer Notes</label>
                                        <textarea rows="3" v-model="form.customer_notes" placeholder="Visible to customer…" class="input-style" />
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- ============================
                     Sidebar
                     ============================ -->
                <div class="lg:col-span-4 space-y-6">

                    <!-- Actions -->
                    <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden">
                        <div class="flex justify-between items-center px-5 py-4 bg-gray-700 dark:bg-gray-700 border-b border-gray-600">
                            <span class="text-sm font-semibold text-white">Actions</span>
                        </div>
                        <div class="p-5 space-y-3">
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg transition-colors"
                            >
                                <span class="material-icons text-[18px]" :class="{ 'animate-spin': form.processing }">
                                    {{ form.processing ? 'sync' : 'check' }}
                                </span>
                                {{ form.processing ? 'Saving…' : (isEdit ? 'Save Changes' : 'Create Delivery') }}
                            </button>
                            <button
                                type="button"
                                @click="emit('cancelled')"
                                class="w-full inline-flex items-center justify-center px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 rounded-lg transition-colors"
                            >
                                Cancel
                            </button>
                        </div>
                    </div>

                    <!-- Delivery Summary -->
                    <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-600 bg-gray-700 dark:bg-gray-700">
                            <span class="text-sm font-semibold text-white">Delivery summary</span>
                        </div>
                        <div class="p-5 space-y-2 text-sm">
                            <div class="space-y-1.5">
                                <span class="text-gray-500 dark:text-gray-400 text-xs block">Status</span>
                                <select v-model="form.status" class="input-style w-full text-sm">
                                    <option
                                        v-for="opt in statusOptions"
                                        :key="String(statusOptionValue(opt))"
                                        :value="statusOptionValue(opt)"
                                    >
                                        {{ statusOptionLabel(opt) }}
                                    </option>
                                </select>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-500 dark:text-gray-400">Technician</span>
                                <span class="font-medium text-gray-900 dark:text-white">
                                    {{ selectedTechnicianLabel || 'Unassigned' }}
                                </span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-500 dark:text-gray-400">Scheduled</span>
                                <span class="font-medium text-gray-900 dark:text-white">
                                    {{ form.scheduled_at ? new Date(form.scheduled_at).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' }) : '—' }}
                                </span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-500 dark:text-gray-400">Source</span>
                                <span class="font-medium text-gray-900 dark:text-white truncate max-w-[160px] text-right">{{ sourceLabel }}</span>
                            </div>
                            <div class="flex justify-between items-center pt-3 border-t border-gray-200 dark:border-gray-700">
                                <span class="text-gray-500 dark:text-gray-400">Items</span>
                                <span class="text-lg font-bold text-primary-600 dark:text-primary-400">
                                    {{ form.items.length }} asset{{ form.items.length !== 1 ? 's' : '' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Details -->
                    <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-600 bg-gray-700 dark:bg-gray-700">
                            <span class="text-sm font-semibold text-white">Details</span>
                        </div>
                        <ul class="p-5 space-y-3 text-sm divide-y divide-gray-100 dark:divide-gray-700">
                            <li class="flex items-center gap-2 pt-0">
                                <span class="material-icons text-[18px] text-gray-400 shrink-0">person</span>
                                <span class="text-gray-500 dark:text-gray-400 flex-1">Customer</span>
                                <span class="font-medium text-gray-900 dark:text-white truncate max-w-[140px]">
                                    {{ selectedCustomerLabel || '—' }}
                                </span>
                            </li>
                            <li class="flex items-center gap-2 pt-3">
                                <span class="material-icons text-[18px] text-gray-400 shrink-0">location_on</span>
                                <span class="text-gray-500 dark:text-gray-400 flex-1">Deliver to</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ deliverToTypeLabel }}</span>
                            </li>
                            <li class="flex items-start gap-2 pt-3">
                                <span class="material-icons text-[18px] text-gray-400 shrink-0 mt-0.5">home</span>
                                <span class="text-gray-500 dark:text-gray-400 flex-1">Address</span>
                                <span class="font-medium text-gray-900 dark:text-white text-right max-w-[150px] leading-snug">
                                    {{ addressSummary }}
                                </span>
                            </li>
                        </ul>
                    </div>

                </div>
            </div>
        </form>

        <AssetLineModal
            v-model="assetFormModel"
            v-model:open="showAssetModal"
            :editing="editingIndex !== null"
            hide-quantity
            @save="saveAssetItem"
        />

        <!-- Customer saved addresses (same flow as invoice billing address picker) -->
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
                    v-if="showDeliveryAddressPicker"
                    class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4"
                    @click.self="dismissDeliveryAddressPicker"
                >
                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
                        <div class="flex items-start justify-between gap-3 px-6 pt-6 pb-4">
                            <div class="flex items-start gap-3">
                                <div class="flex-shrink-0 w-9 h-9 rounded-full bg-primary-100 dark:bg-primary-900/40 flex items-center justify-center">
                                    <svg class="w-5 h-5 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Delivery address</h3>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                                        <template v-if="isFetchingDeliveryAddresses">Loading addresses…</template>
                                        <template v-else-if="deliveryPickerAddresses.length > 0">Select one of this contact’s saved addresses</template>
                                        <template v-else-if="deliveryPickerContactId">This contact has no saved addresses yet. Add one to save it on the contact and use it here.</template>
                                        <template v-else>This customer has no linked contact for saved addresses. Enter the delivery address manually below.</template>
                                    </p>
                                </div>
                            </div>
                            <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 mt-0.5" @click="dismissDeliveryAddressPicker">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>

                        <div v-if="isFetchingDeliveryAddresses" class="flex justify-center py-12">
                            <svg class="w-8 h-8 animate-spin text-primary-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                        </div>

                        <div v-else-if="deliveryPickerAddresses.length > 0" class="px-6 pb-2 space-y-2 max-h-80 overflow-y-auto">
                            <button
                                v-for="addr in deliveryPickerAddresses"
                                :key="addr.id"
                                type="button"
                                class="w-full text-left px-4 py-3 rounded-xl border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/50 hover:border-primary-400 hover:bg-primary-50 dark:hover:bg-primary-900/20 transition-colors group"
                                @click="selectDeliveryContactAddress(addr)"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <div class="text-sm space-y-0.5">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="font-medium text-gray-900 dark:text-white">{{ addr.address_line_1 }}</p>
                                            <span
                                                v-if="addr.is_primary"
                                                class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300"
                                            >Primary</span>
                                            <span
                                                v-if="addr.label"
                                                class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-600 dark:text-gray-300"
                                            >{{ addr.label }}</span>
                                        </div>
                                        <p v-if="addr.address_line_2" class="text-gray-500 dark:text-gray-400">{{ addr.address_line_2 }}</p>
                                        <p class="text-gray-600 dark:text-gray-300">
                                            <span v-if="addr.city">{{ addr.city }}<span v-if="addr.state || addr.postal_code">, </span></span>
                                            <span v-if="addr.state">{{ addr.state }} </span>
                                            <span v-if="addr.postal_code">{{ addr.postal_code }}</span>
                                        </p>
                                        <p v-if="addr.country" class="text-gray-500 dark:text-gray-400">{{ addr.country }}</p>
                                    </div>
                                    <svg class="w-4 h-4 flex-shrink-0 text-gray-300 group-hover:text-primary-500 mt-0.5 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </div>
                            </button>
                        </div>

                        <div v-else-if="deliveryPickerContactId" class="px-6 pb-2 space-y-4">
                            <ContactAddressAutocomplete
                                :disabled="postingDeliveryAddress"
                                button-label="Add address to contact"
                                @saved="onDeliveryContactAddressSaved"
                            />
                        </div>

                        <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/30 mt-2">
                            <button
                                type="button"
                                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors"
                                @click="dismissDeliveryAddressPicker"
                            >
                                Skip, fill manually
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>