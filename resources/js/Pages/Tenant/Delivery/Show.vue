<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import Modal from '@/Components/Modal.vue';
import DeliveryPreview from '@/Components/Tenant/DeliveryPreview.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref } from 'vue';

const props = defineProps({
    record: { type: Object, required: true },
    enumOptions: { type: Object, default: () => ({}) },
    account: { type: Object, default: null },
    checklistItems: { type: Array, default: () => [] },
    checklistTemplates: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    customerAddresses: { type: Array, default: () => [] },
});

const recordIdentifier = computed(() => props.record?.id ?? props.record?.uuid);

const showDeleteModal = ref(false);
const isDeleting = ref(false);
const showPreview = ref(false);
const showMarkDeliveredModal = ref(false);

// Checklist state (carried over from original)
const showChecklistModal = ref(false);
const checklistCreationMode = ref('template');
const selectedTemplate = ref(null);
const newChecklistItems = ref([]);
const isLoadingChecklist = ref(false);

const showAddItemModal = ref(false);
const editingChecklistItem = ref(null);
const newItemLabel = ref('');
const newItemCategory = ref('');
const newItemRequired = ref(false);

/* ─── Status ─── */
const isSigned = computed(() => !!props.record?.signed_at);

const deliveryStatusOptions = computed(() => props.enumOptions?.delivery_status || [
    { id: 'scheduled', name: 'Scheduled' },
    { id: 'confirmed', name: 'Confirmed' },
    { id: 'en_route', name: 'En Route' },
    { id: 'delivered', name: 'Delivered' },
    { id: 'cancelled', name: 'Cancelled' },
    { id: 'rescheduled', name: 'Rescheduled' },
]);

const statusOptionValue = (o) => o.value ?? o.id;
const statusOptionLabel = (o) => o.name ?? o.label ?? String(statusOptionValue(o));

const statusOptionsForSelect = computed(() => {
    const opts = Array.isArray(deliveryStatusOptions.value) ? [...deliveryStatusOptions.value] : [];
    const vals = new Set(opts.map(o => statusOptionValue(o)));
    const cur = props.record?.status;
    if (cur && !vals.has(cur)) {
        opts.unshift({ id: cur, name: cur });
    }
    return opts;
});

const recordStatusLabel = computed(() => {
    const cur = props.record?.status;
    if (cur == null || cur === '') return '—';
    const opts = Array.isArray(deliveryStatusOptions.value) ? deliveryStatusOptions.value : [];
    const found = opts.find((o) => statusOptionValue(o) === cur);
    if (found) return statusOptionLabel(found);
    return String(cur);
});

const statusUpdating = ref(false);

const updateDeliveryStatus = async (event) => {
    const el = event?.target;
    const newStatus = el?.value;
    if (!newStatus || newStatus === props.record?.status || isSigned.value) return;
    statusUpdating.value = true;
    try {
        await axios.put(route('deliveries.update', props.record.id), { status: newStatus });
        router.reload({ only: ['record'] });
    } catch (e) {
        console.error(e);
        alert(e?.response?.data?.message ?? 'Failed to update status.');
        if (el) el.value = props.record.status;
    } finally {
        statusUpdating.value = false;
    }
};

/* ─── Formatting helpers ─── */
const formatDateTime = (v) => {
    if (!v) return '—';
    try {
        const d = new Date(v);
        if (isNaN(d.getTime())) return '—';
        return d.toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
    } catch { return '—'; }
};
const formatDate = (v) => {
    if (!v) return '—';
    try {
        const d = new Date(v);
        if (isNaN(d.getTime())) return '—';
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    } catch { return '—'; }
};

/* ─── Items ─── */
const items = computed(() => Array.isArray(props.record?.items) ? props.record.items : []);

const itemName = (item) => {
    const unit = item.asset_unit ?? item.assetUnit ?? null;
    const variant = item.asset_variant ?? item.assetVariant ?? null;
    const assetDisplay = unit?.asset?.display_name;
    if (assetDisplay) return assetDisplay;
    if (variant?.display_name) return variant.display_name;
    return item.name ?? 'Asset';
};
const itemVariantLabel = (item) => {
    const v = item.asset_variant ?? item.assetVariant ?? null;
    return v?.display_name ?? v?.name ?? null;
};
const itemUnitLabel = (item) => {
    const unit = item.asset_unit ?? item.assetUnit ?? null;
    if (!unit) return item.serial_number_snapshot ?? null;
    const raw = unit.display_name ?? null;
    if (raw) {
        const parts = String(raw).split(' - ');
        return parts.length > 1 ? parts.slice(1).join(' - ') : parts[0];
    }
    return unit.serial_number ?? unit.hin ?? unit.sku ?? item.serial_number_snapshot ?? null;
};

const allItemsDelivered = computed(() => items.value.length > 0 && items.value.every(i => !!i.delivered_at));

/* ─── Related records sidebar ─── */
const relatedRecords = computed(() => {
    const r = props.record;
    const out = [];
    if (r?.customer?.id) {
        out.push({ label: 'Customer', name: r.customer.display_name ?? (r.customer.contact?.display_name) ?? `#${r.customer.id}`, href: route('customers.show', r.customer.id) });
    }
    if (r?.work_order?.id || r?.workOrder?.id) {
        const wo = r.work_order ?? r.workOrder;
        out.push({ label: 'Work Order', name: wo.display_name ?? `WO-${wo.work_order_number ?? wo.id}`, href: route('workorders.show', wo.id) });
    }
    if (r?.transaction?.id) {
        out.push({ label: 'Transaction', name: r.transaction.display_name ?? `#${r.transaction.id}`, href: route('transactions.show', r.transaction.id) });
    }
    const dloc = r?.delivery_location ?? r?.deliveryLocation;
    if (dloc?.id) {
        out.push({ label: 'Delivery Location', name: dloc.display_name ?? dloc.name, href: route('delivery-locations.show', dloc.id) });
    }
    if (r?.subsidiary?.id) {
        out.push({
            label: 'Subsidiary',
            name: r.subsidiary.display_name ?? `Subsidiary #${r.subsidiary.id}`,
            href: route('subsidiaries.show', r.subsidiary.id),
        });
    }
    if (r?.location?.id) {
        out.push({
            label: 'Depart from',
            name: r.location.display_name ?? `Location #${r.location.id}`,
            href: route('locations.show', r.location.id),
        });
    }
    return out;
});

const canMarkEnRoute = computed(
    () => !isSigned.value
        && ['scheduled', 'confirmed', 'rescheduled'].includes(props.record?.status),
);
const enRouteLoading = ref(false);
const goEnRoute = () => {
    if (!canMarkEnRoute.value) return;
    enRouteLoading.value = true;
    router.post(
        route('deliveries.en-route', props.record.id),
        {},
        {
            preserveScroll: true,
            onFinish: () => { enRouteLoading.value = false; },
        },
    );
};

/* ─── Actions ─── */
const markAsDelivered = () => { showMarkDeliveredModal.value = true; };

const markDeliveredWithoutSignature = async () => {
    try {
        await axios.post(route('deliveries.mark-delivered', props.record.id));
        showMarkDeliveredModal.value = false;
        router.reload();
    } catch (error) {
        console.error(error);
        alert('Failed to mark delivery as completed. Please try again.');
    }
};

const sendSignatureRequest = async () => {
    try {
        await axios.post(route('deliveries.send-signature-request', props.record.id));
        showMarkDeliveredModal.value = false;
        alert('Signature request sent to customer successfully!');
    } catch (error) {
        console.error(error);
        alert('Failed to send signature request. Please try again.');
    }
};

const viewSignatureRequest = () => {
    window.open(route('deliveries.review', props.record.uuid), '_blank');
};

const openPreview = () => { showPreview.value = true; };
const closePreview = () => { showPreview.value = false; };

const handleDelete = () => { showDeleteModal.value = true; };
const cancelDelete = () => { showDeleteModal.value = false; };
const confirmDelete = () => {
    isDeleting.value = true;
    router.delete(route('deliveries.destroy', recordIdentifier.value), {
        onSuccess: () => router.visit(route('deliveries.index')),
        onError: () => { isDeleting.value = false; },
        onFinish: () => { isDeleting.value = false; showDeleteModal.value = false; },
    });
};

const toggleItemDelivered = async (item) => {
    const delivered = !item.delivered_at;
    try {
        await axios.post(route('deliveries.items.mark-delivered', { delivery: props.record.id, item: item.id }), {
            delivered,
        });
        router.reload({ only: ['record'] });
    } catch (error) {
        console.error(error);
        alert('Failed to update item.');
    }
};

/* ─── Checklist helpers (kept from original) ─── */
const itemsByCategory = computed(() => {
    const grouped = {};
    (props.checklistItems || []).forEach(item => {
        const catId = item.category_id ?? item.category?.id ?? 'uncategorized';
        const catName = item.category?.name ?? 'Other';
        if (!grouped[catId]) grouped[catId] = { id: catId, name: catName, items: [] };
        grouped[catId].items.push(item);
    });
    return Object.values(grouped).sort((a, b) => a.name.localeCompare(b.name));
});

const openChecklistModal = () => {
    showChecklistModal.value = true;
    checklistCreationMode.value = 'template';
    selectedTemplate.value = null;
    newChecklistItems.value = [];
};
const closeChecklistModal = () => { showChecklistModal.value = false; };
const selectChecklistMode = (mode) => {
    checklistCreationMode.value = mode;
    if (mode === 'scratch') {
        newChecklistItems.value = [{ label: '', category: 'Pre Delivery', is_required: false, sort_order: 0 }];
    }
};
const addChecklistItem = () => newChecklistItems.value.push({ label: '', category: 'Pre Delivery', is_required: false, sort_order: newChecklistItems.value.length });
const removeChecklistItem = (idx) => newChecklistItems.value.splice(idx, 1);

const saveChecklist = async () => {
    isLoadingChecklist.value = true;
    try {
        if (checklistCreationMode.value === 'template' && selectedTemplate.value) {
            await axios.post(route('deliveries.checklist.store', { delivery: props.record.id }), { template_id: selectedTemplate.value.id });
        } else if (checklistCreationMode.value === 'scratch') {
            const valid = newChecklistItems.value.filter(i => i.label.trim());
            if (valid.length) {
                await axios.post(route('deliveries.checklist.store', { delivery: props.record.id }), { items: valid });
            }
        }
        router.reload();
        closeChecklistModal();
    } catch (e) { console.error(e); }
    finally { isLoadingChecklist.value = false; }
};

const addChecklistItemToDelivery = () => {
    editingChecklistItem.value = null;
    newItemLabel.value = '';
    newItemCategory.value = props.categories.length > 0 ? props.categories[0].name : '';
    newItemRequired.value = false;
    showAddItemModal.value = true;
};
const editChecklistItemOnDelivery = (item) => {
    editingChecklistItem.value = item;
    newItemLabel.value = item.label ?? '';
    newItemCategory.value = item.category?.name
        ?? (props.categories.length > 0 ? props.categories[0].name : '');
    newItemRequired.value = !!item.is_required;
    showAddItemModal.value = true;
};
const closeAddItemModal = () => {
    showAddItemModal.value = false;
    editingChecklistItem.value = null;
};
const saveNewChecklistItem = async () => {
    if (!newItemLabel.value.trim()) return;
    try {
        if (editingChecklistItem.value) {
            await axios.put(route('deliveries.checklist.update-item', {
                delivery: props.record.id,
                item: editingChecklistItem.value.id,
            }), {
                label: newItemLabel.value.trim(),
                category: newItemCategory.value,
                is_required: newItemRequired.value,
            });
        } else {
            await axios.post(route('deliveries.checklist.add-item', { delivery: props.record.id }), {
                label: newItemLabel.value.trim(),
                category: newItemCategory.value,
                is_required: newItemRequired.value,
            });
        }
        closeAddItemModal();
        router.reload();
    } catch (e) {
        console.error(e);
        alert(editingChecklistItem.value ? 'Failed to update item.' : 'Failed to add item.');
    }
};
const removeChecklistItemFromDelivery = async (item) => {
    if (!confirm(`Remove "${item.label}" from checklist?`)) return;
    try {
        await axios.delete(route('deliveries.checklist.remove-item', { delivery: props.record.id, item: item.id }));
        router.reload();
    } catch (e) { console.error(e); alert('Failed to remove item.'); }
};
const toggleChecklistItemCompleted = async (item) => {
    const next = !item.completed;
    item.completed = next;
    try {
        await axios.put(route('deliveries.checklist.update-item', { delivery: props.record.id, item: item.id }), { completed: next });
        router.reload({ only: ['checklistItems'] });
    } catch (e) {
        console.error(e);
        item.completed = !next;
    }
};

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Deliveries', href: route('deliveries.index') },
    { label: props.record?.display_name ?? 'Delivery' },
]);

const deliverToSummary = computed(() => {
    const r = props.record;
    if (!r) return null;
    if (r.delivery_to_type === 'delivery_location' && (r.delivery_location ?? r.deliveryLocation)) {
        const d = r.delivery_location ?? r.deliveryLocation;
        return { type: 'Common Location', name: d.display_name ?? d.name };
    }
    if (r.delivery_to_type === 'contact_address') {
        return { type: 'Customer Address', name: null };
    }
    return { type: 'Custom Address', name: null };
});

const locationRecord = computed(() => props.record?.location ?? null);

const hasLocationAddress = computed(() => {
    const loc = locationRecord.value;
    if (!loc) return false;
    return !!(
        (loc.address_line_1 && String(loc.address_line_1).trim())
        || (loc.city && String(loc.city).trim())
    );
});

const formatAddressForMaps = (o) => {
    if (!o) return null;
    const parts = [o.address_line_1, o.address_line_2, o.city, o.state, o.postal_code, o.country]
        .map((p) => (p == null ? '' : String(p).trim()))
        .filter(Boolean);
    if (parts.length) {
        return parts.join(', ');
    }
    if (o.display_name && String(o.display_name).trim()) {
        return String(o.display_name).trim();
    }
    if (o.name && String(o.name).trim()) {
        return String(o.name).trim();
    }
    return null;
};

/** Prefer lat,lng; otherwise a full address or place name for Google to resolve. */
const mapsPointForLocation = (loc) => {
    if (!loc) return null;
    const lat = loc.latitude;
    const lng = loc.longitude;
    if (lat != null && lat !== '' && lng != null && lng !== '') {
        return `${String(lat).trim()},${String(lng).trim()}`;
    }
    return formatAddressForMaps(loc);
};

const mapsPointForDeliveryDestination = (r) => {
    if (!r) return null;
    const lat = r.latitude;
    const lng = r.longitude;
    if (lat != null && lat !== '' && lng != null && lng !== '') {
        return `${String(lat).trim()},${String(lng).trim()}`;
    }
    const fromSnapshot = formatAddressForMaps(r);
    if (fromSnapshot) {
        return fromSnapshot;
    }
    const dloc = r.delivery_location ?? r.deliveryLocation;
    if (dloc) {
        return formatAddressForMaps(dloc) ?? null;
    }
    return null;
};

const googleMapsDirectionsUrl = computed(() => {
    const origin = mapsPointForLocation(locationRecord.value);
    const dest = mapsPointForDeliveryDestination(props.record);
    if (!origin || !dest) return null;
    return `https://www.google.com/maps/dir/?${new URLSearchParams({
        api: '1',
        origin,
        destination: dest,
        travelmode: 'driving',
    }).toString()}`;
});
</script>

<template>
    <Head :title="`Delivery - ${record?.display_name}`" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="flex items-center justify-between mt-4 gap-4 flex-wrap">
                    <div class="flex items-center gap-3 flex-wrap">
                        <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                            {{ record?.display_name }}
                        </h2>
                        <div class="flex items-center gap-2">
                            <label for="delivery-status-select" class="sr-only">Delivery status</label>
                            <select
                                id="delivery-status-select"
                                :value="record.status"
                                :disabled="isSigned || statusUpdating"
                                class="input-style text-md py-2 min-w-[12rem] max-w-full disabled:opacity-60 disabled:cursor-not-allowed"
                                @change="updateDeliveryStatus"
                            >
                                <option
                                    v-for="opt in statusOptionsForSelect"
                                    :key="String(statusOptionValue(opt))"
                                    :value="statusOptionValue(opt)"
                                >
                                    {{ statusOptionLabel(opt) }}
                                </option>
                            </select>
                            <span
                                v-if="statusUpdating"
                                class="material-icons text-xl text-primary-600 animate-spin"
                                aria-hidden="true"
                            >sync</span>
                            <span
                                v-if="isSigned"
                                class="text-sm text-gray-500 dark:text-gray-400 whitespace-nowrap"
                            >Locked (signed)</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <button
                            @click="openPreview"
                            class="inline-flex items-center gap-2 px-3 py-2 text-md font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700"
                        >
                            <span class="material-icons text-lg">visibility</span>
                            Preview
                        </button>
                        <a
                            :href="route('deliveries.print', record.id)"
                            target="_blank"
                            class="inline-flex items-center gap-2 px-3 py-2 text-md font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700"
                        >
                            <span class="material-icons text-lg">print</span>
                            Print
                        </a>
                        <Link
                            :href="route('deliveries.edit', record.id)"
                            class="inline-flex items-center gap-2 px-3 py-2 text-md font-medium text-white bg-primary-600 border border-transparent rounded-md hover:bg-primary-700"
                        >
                            <span class="material-icons text-lg">edit</span>
                            Edit
                        </Link>
                        <button
                            @click="handleDelete"
                            class="inline-flex items-center gap-2 px-3 py-2 text-md font-medium text-white bg-red-600 border border-transparent rounded-md hover:bg-red-700"
                        >
                            <span class="material-icons text-lg">delete</span>
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <!-- Main content -->
            <div class="lg:col-span-8 space-y-6">
                <!-- Summary card -->
                <div class="divide-y divide-gray-200 dark:divide-gray-700 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">

<!-- Header: Customer -->
<div class="bg-white dark:bg-gray-800 px-6 py-5 flex items-center justify-between gap-4 flex-wrap">
  <div class="flex items-center gap-3">
    <div class="w-10 h-10 rounded-full bg-blue-50 dark:bg-blue-950 flex items-center justify-center text-md font-medium text-blue-600 dark:text-blue-400 shrink-0">
      {{ record.customer?.display_name?.slice(0,2).toUpperCase() ?? '??' }}
    </div>
    <div>
      <p class="m-0 text-md font-semibold text-gray-900 dark:text-white">
        {{ record.customer?.display_name ?? record.customer?.contact?.display_name ?? '—' }}
      </p>
      <p class="text-sm text-gray-500 dark:text-gray-400">
        <span v-if="record.customer?.contact?.email">{{ record.customer.contact.email }}</span>
        <span v-if="record.customer?.contact?.email && record.customer?.contact?.phone"> · </span>
        <span v-if="record.customer?.contact?.phone">{{ record.customer.contact.phone }}</span>
      </p>
    </div>
  </div>
  <span class="text-sm font-medium px-3 py-1 rounded-full bg-green-50 dark:bg-green-950 text-green-700 dark:text-green-400 tracking-wide">
    {{ recordStatusLabel }}
  </span>
</div>

<!-- Middle: 3-column info -->
<div class="bg-white dark:bg-gray-800 grid grid-cols-1 sm:grid-cols-3 divide-y sm:divide-y-0 sm:divide-x divide-gray-200 dark:divide-gray-700">

  <div class="px-6 py-5">
    <p class="text-sm font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-2">Deliver to</p>
    <p class="text-md font-semibold text-gray-900 dark:text-white mb-1">
      {{ deliverToSummary?.type ?? 'Custom Address' }}
      <span v-if="deliverToSummary?.name" class="font-normal text-gray-500"> · {{ deliverToSummary.name }}</span>
    </p>
    <div v-if="record.address_line_1" class="text-sm text-gray-500 dark:text-gray-400 leading-relaxed">
      <div>{{ record.address_line_1 }}</div>
      <div v-if="record.address_line_2">{{ record.address_line_2 }}</div>
      <div>
        <span v-if="record.city">{{ record.city }}</span><span v-if="record.state">, {{ record.state }}</span><span v-if="record.postal_code"> {{ record.postal_code }}</span>
      </div>
    </div>
    <div v-else class="text-sm text-gray-400 italic">No address on file</div>
  </div>

  <div class="px-6 py-5">
    <p class="text-sm font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-2">Delivered from</p>
    <div v-if="locationRecord">
      <p class="text-md font-semibold mb-1">
        <Link v-if="locationRecord.id" :href="route('locations.show', locationRecord.id)" class="text-primary-600 hover:text-primary-500 dark:text-primary-400">
          {{ locationRecord.display_name ?? `Location #${locationRecord.id}` }}
        </Link>
        <span v-else class="text-gray-900 dark:text-white">{{ locationRecord.display_name ?? '—' }}</span>
      </p>
      <div v-if="hasLocationAddress" class="text-sm text-gray-500 dark:text-gray-400 leading-relaxed">
        <div v-if="locationRecord.address_line_1">{{ locationRecord.address_line_1 }}</div>
        <div v-if="locationRecord.address_line_2">{{ locationRecord.address_line_2 }}</div>
        <div>
          <span v-if="locationRecord.city">{{ locationRecord.city }}</span><span v-if="locationRecord.state">, {{ locationRecord.state }}</span><span v-if="locationRecord.postal_code"> {{ locationRecord.postal_code }}</span>
        </div>
        <div v-if="locationRecord.country" class="text-gray-400">{{ locationRecord.country }}</div>
      </div>
      <div v-else class="text-sm text-gray-400 italic">No address on file</div>
    </div>
    <div v-else class="text-md text-gray-400">—</div>
  </div>

  <div class="px-6 py-5">
    <p class="text-sm font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-2">Subsidiary</p>
    <div v-if="record.subsidiary?.id">
      <Link :href="route('subsidiaries.show', record.subsidiary.id)" class="text-md font-semibold text-primary-600 hover:text-primary-500 dark:text-primary-400">
        {{ record.subsidiary.display_name ?? `Subsidiary #${record.subsidiary.id}` }}
      </Link>
      <p class="text-sm text-gray-400 mt-0.5">Sub #{{ record.subsidiary.id }}</p>
    </div>
    <div v-else class="text-md text-gray-400">—</div>
  </div>

</div>

<!-- Bottom: Schedule tiles -->
<div class="bg-white dark:bg-gray-800 px-6 py-5">
  <p class="text-sm font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-3">Schedule &amp; routing</p>
  <div class="grid grid-cols-2 sm:grid-cols-3 divide-x divide-y divide-gray-200 dark:divide-gray-700 rounded-md border border-gray-200 dark:border-gray-700 overflow-hidden">

    <div class="px-4 py-3 bg-white dark:bg-gray-800">
      <p class="text-sm text-gray-400 dark:text-gray-500 mb-1">Need to leave by</p>
      <p class="text-md font-semibold text-gray-900 dark:text-white">{{ formatDateTime(record.time_to_leave_by) }}</p>
    </div>

    <div class="px-4 py-3 bg-white dark:bg-gray-800">
      <p class="text-sm text-gray-400 dark:text-gray-500 mb-1">Scheduled arrive by</p>
      <p class="text-md font-semibold text-gray-900 dark:text-white">{{ formatDateTime(record.scheduled_at) }}</p>
    </div>

    <div v-if="record.estimated_travel_duration_seconds" class="px-4 py-3 bg-white dark:bg-gray-800">
      <p class="text-sm text-gray-400 dark:text-gray-500 mb-1">Drive time</p>
      <p class="text-md font-semibold text-gray-900 dark:text-white">~{{ Math.max(1, Math.round(record.estimated_travel_duration_seconds / 60)) }} min</p>
    </div>

    <div v-if="record.en_route_at" class="px-4 py-3 bg-white dark:bg-gray-800">
      <p class="text-sm text-gray-400 dark:text-gray-500 mb-1">Departed en route</p>
      <p class="text-md font-semibold text-gray-900 dark:text-white">{{ formatDateTime(record.en_route_at) }}</p>
    </div>

    <div class="px-4 py-3 bg-white dark:bg-gray-800">
      <p class="text-sm text-gray-400 dark:text-gray-500 mb-1">Est. arrival</p>
      <p class="text-md font-semibold text-gray-900 dark:text-white">{{ formatDateTime(record.estimated_arrival_at) }}</p>
    </div>

    <div class="px-4 py-3 bg-white dark:bg-gray-800">
      <p class="text-sm text-gray-400 dark:text-gray-500 mb-1">Delivered</p>
      <p class="text-md font-semibold text-green-600 dark:text-green-400">{{ formatDateTime(record.delivered_at) }}</p>
    </div>
    <div></div>
  </div>

  <div v-if="googleMapsDirectionsUrl" class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
    <a
      :href="googleMapsDirectionsUrl"
      target="_blank"
      rel="noopener noreferrer"
      class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-[#4285F4] hover:bg-[#3367d6] rounded-lg shadow-sm transition-colors"
    >
      <span class="material-icons text-xl" aria-hidden="true">map</span>
      Open delivery route in Google Maps
    </a>
    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
      From your <strong>depart</strong> location to the <strong>delivery</strong> address (driving).
    </p>
  </div>
</div>

</div>

                <!-- Assets -->
                <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white">Assets to Deliver</h3>
                        <div v-if="items.length" class="text-md text-gray-500">
                            {{ items.filter(i => i.delivered_at).length }} / {{ items.length }} delivered
                        </div>
                    </div>

                    <div v-if="items.length === 0" class="px-6 py-10 text-center text-md text-gray-500">
                        No assets tied to this delivery yet. Edit the delivery to link a source or add assets.
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900/40">
                                <tr>
                                    <th class="px-4 py-2 text-left text-sm font-semibold uppercase tracking-wide text-gray-500">Asset</th>
                                    <th class="px-4 py-2 text-left text-sm font-semibold uppercase tracking-wide text-gray-500">Variant</th>
                                    <th class="px-4 py-2 text-left text-sm font-semibold uppercase tracking-wide text-gray-500">Unit / Serial</th>
                                    <th class="px-4 py-2 text-center text-sm font-semibold uppercase tracking-wide text-gray-500">Delivered</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                <tr v-for="item in items" :key="item.id">
                                    <td class="px-4 py-3 text-md text-gray-900 dark:text-white">
                                        <div class="font-medium">{{ itemName(item) }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-md text-gray-600 dark:text-gray-300">{{ itemVariantLabel(item) ?? '—' }}</td>
                                    <td class="px-4 py-3 text-md text-gray-600 dark:text-gray-300">{{ itemUnitLabel(item) ?? '—' }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <label class="inline-flex items-center gap-2 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                :checked="!!item.delivered_at"
                                                :disabled="isSigned"
                                                @change="toggleItemDelivered(item)"
                                                class="h-5 w-5 rounded border-gray-300 text-green-600 focus:ring-green-500"
                                            />
                                            <span v-if="item.delivered_at" class="text-sm text-green-700 dark:text-green-300">
                                                {{ formatDate(item.delivered_at) }}
                                            </span>
                                        </label>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <!-- Notes -->
                <div v-if="record.internal_notes || record.customer_notes" class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6 space-y-4">
                    <div v-if="record.internal_notes">
                        <div class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">Internal Notes</div>
                        <p class="text-md text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ record.internal_notes }}</p>
                    </div>
                    <div v-if="record.customer_notes">
                        <div class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">Customer Notes</div>
                        <p class="text-md text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ record.customer_notes }}</p>
                    </div>
                </div>

                <!-- Checklist (existing) -->
                <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                        <div>
                            <h3 class="text-xl font-semibold text-gray-900 dark:text-white">Delivery Checklist</h3>
                            <p class="text-md text-gray-500 dark:text-gray-400">
                                Items to complete before and during delivery
                                <span v-if="isSigned" class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-sm font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300">
                                    <span class="material-icons text-md mr-1">lock</span>
                                    Signed
                                </span>
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <button
                                v-if="!isSigned"
                                @click="addChecklistItemToDelivery"
                                class="inline-flex items-center gap-2 px-3 py-1.5 text-md font-medium text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20 hover:bg-blue-100 dark:hover:bg-blue-900/40 rounded-lg"
                            >
                                <span class="material-icons text-lg">add</span>
                                Add Item
                            </button>
                            <button
                                v-if="!isSigned && checklistItems.length === 0"
                                @click="openChecklistModal"
                                class="inline-flex items-center gap-2 px-3 py-1.5 text-md font-medium text-green-600 bg-green-50 hover:bg-green-100 rounded-lg"
                            >
                                <span class="material-icons text-lg">description</span>
                                Use Template
                            </button>
                        </div>
                    </div>

                    <div v-if="checklistItems.length > 0" class="divide-y divide-gray-100 dark:divide-gray-700">
                        <div v-for="category in itemsByCategory" :key="category.id" class="px-6 py-4">
                            <h4 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-3 flex items-center gap-2">
                                <span class="w-1.5 h-1.5 rounded-full bg-primary-500"></span>
                                {{ category.name }}
                                <span class="ml-1 text-gray-400 dark:text-gray-500 normal-case font-medium">
                                    ({{ category.items.filter(i => i.completed).length }}/{{ category.items.length }})
                                </span>
                            </h4>
                            <ul class="space-y-1.5">
                                <li
                                    v-for="item in category.items"
                                    :key="item.id"
                                    class="group flex items-center gap-3 rounded-lg px-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
                                >
                                    <label class="flex items-center gap-3 flex-1 min-w-0 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            :checked="!!item.completed"
                                            :disabled="isSigned"
                                            @change="toggleChecklistItemCompleted(item)"
                                            class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 disabled:opacity-60 disabled:cursor-not-allowed"
                                        />
                                        <span
                                            :class="[
                                                'text-md flex-1 min-w-0 truncate',
                                                item.completed
                                                    ? 'text-gray-400 dark:text-gray-500 line-through'
                                                    : 'text-gray-900 dark:text-white',
                                            ]"
                                        >
                                            {{ item.label }}
                                            <span v-if="item.is_required" class="ml-1 text-red-500">*</span>
                                        </span>
                                    </label>
                                    <div v-if="!isSigned" class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <button
                                            type="button"
                                            @click="editChecklistItemOnDelivery(item)"
                                            class="h-7 w-7 rounded flex items-center justify-center text-gray-400 hover:text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-900/20"
                                            aria-label="Edit item"
                                        >
                                            <span class="material-icons text-lg">edit</span>
                                        </button>
                                        <button
                                            type="button"
                                            @click="removeChecklistItemFromDelivery(item)"
                                            class="h-7 w-7 rounded flex items-center justify-center text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20"
                                            aria-label="Remove item"
                                        >
                                            <span class="material-icons text-lg">delete</span>
                                        </button>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div v-else class="px-6 py-8 text-center text-md text-gray-500">
                        No checklist items.
                    </div>
                </div>


            </div>

            <!-- Sidebar -->
            <div class="lg:col-span-4 space-y-6">
                <!-- Actions -->
                <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Actions</h3>
                    <div class="space-y-3">
                        <button
                            v-if="canMarkEnRoute"
                            type="button"
                            @click="goEnRoute"
                            :disabled="enRouteLoading || isSigned"
                            class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-md font-medium text-white bg-primary-600 hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg"
                        >
                            <span class="material-icons text-lg" :class="{ 'animate-spin': enRouteLoading }">
                                {{ enRouteLoading ? 'sync' : 'local_shipping' }}
                            </span>
                            Mark en route
                        </button>
                        <button
                            @click="markAsDelivered"
                            :disabled="record.delivered_at"
                            class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-md font-medium text-white bg-green-600 hover:bg-green-700 disabled:bg-gray-400 disabled:cursor-not-allowed rounded-lg"
                        >
                            <span class="material-icons text-lg">check_circle</span>
                            {{ record.delivered_at ? 'Delivered' : 'Complete Delivery' }}
                        </button>
                        <button
                            @click="viewSignatureRequest"
                            class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-md font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg"
                        >
                            <span class="material-icons text-lg">visibility</span>
                            View Signature Request
                        </button>
                    </div>
                </div>

                <!-- Info -->
                <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Delivery Info</h3>
                    <dl class="text-md space-y-3">
                        <div>
                            <dt class="text-gray-500 mb-1.5">Status</dt>
                            <dd class="m-0">
                                <label for="delivery-status-select-sidebar" class="sr-only">Delivery status</label>
                                <select
                                    id="delivery-status-select-sidebar"
                                    :value="record.status"
                                    :disabled="isSigned || statusUpdating"
                                    class="input-style w-full text-md py-2 disabled:opacity-60 disabled:cursor-not-allowed"
                                    @change="updateDeliveryStatus"
                                >
                                    <option
                                        v-for="opt in statusOptionsForSelect"
                                        :key="`sb-${String(statusOptionValue(opt))}`"
                                        :value="statusOptionValue(opt)"
                                    >
                                        {{ statusOptionLabel(opt) }}
                                    </option>
                                </select>
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Technician</dt>
                            <dd class="text-gray-900 dark:text-white text-right">{{ record.technician?.display_name ?? record.technician?.name ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Created</dt>
                            <dd class="text-gray-900 dark:text-white">{{ formatDate(record.created_at) }}</dd>
                        </div>
                        <div v-if="record.signed_at" class="flex justify-between gap-4">
                            <dt class="text-gray-500">Signed</dt>
                            <dd class="text-gray-900 dark:text-white">{{ formatDateTime(record.signed_at) }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- Related records -->
                <div v-if="relatedRecords.length" class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Related Records</h3>
                    <ul class="space-y-2">
                        <li v-for="rel in relatedRecords" :key="rel.label + rel.name" class="flex items-center justify-between gap-3">
                            <div>
                                <div class="text-sm uppercase tracking-wide text-gray-500">{{ rel.label }}</div>
                                <div class="text-md text-gray-900 dark:text-white">{{ rel.name }}</div>
                            </div>
                            <a :href="rel.href" class="text-primary-600 hover:text-primary-700 text-md inline-flex items-center gap-1">
                                <span class="material-icons text-md">open_in_new</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <Modal :show="showDeleteModal" @close="cancelDelete" max-width="md">
            <div class="p-6 text-center">
                <h3 class="text-xl font-medium text-gray-900 dark:text-white">Delete Delivery</h3>
                <p class="mt-2 text-md text-gray-500 dark:text-gray-400">
                    Are you sure you want to delete {{ record?.display_name }}? This cannot be undone.
                </p>
                <div class="mt-6 flex justify-center gap-3">
                    <button
                        @click="confirmDelete"
                        :disabled="isDeleting"
                        class="px-4 py-2 text-md font-medium text-white bg-red-600 hover:bg-red-700 rounded-md disabled:opacity-50"
                    >
                        {{ isDeleting ? 'Deleting...' : 'Delete' }}
                    </button>
                    <button
                        @click="cancelDelete"
                        class="px-4 py-2 text-md font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 rounded-md"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </Modal>

        <!-- Mark delivered modal -->
        <Modal :show="showMarkDeliveredModal" @close="showMarkDeliveredModal = false" max-width="md">
            <div class="p-6">
                <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Complete Delivery</h3>
                <p class="text-md text-gray-600 dark:text-gray-400 mb-4">Choose how you want to complete this delivery:</p>
                <div class="space-y-3">
                    <button
                        @click="sendSignatureRequest"
                        class="w-full flex items-center gap-3 p-4 border border-blue-200 bg-blue-50 hover:bg-blue-100 rounded-lg text-left"
                    >
                        <span class="material-icons text-blue-600">send</span>
                        <div>
                            <div class="font-medium text-gray-900">Send Signature Request</div>
                            <div class="text-md text-gray-600">Request customer signature via email</div>
                        </div>
                    </button>
                    <button
                        @click="markDeliveredWithoutSignature"
                        class="w-full flex items-center gap-3 p-4 border border-green-200 bg-green-50 hover:bg-green-100 rounded-lg text-left"
                    >
                        <span class="material-icons text-green-600">check_circle</span>
                        <div>
                            <div class="font-medium text-gray-900">Mark as Delivered</div>
                            <div class="text-md text-gray-600">Complete without customer signature</div>
                        </div>
                    </button>
                </div>
                <div class="mt-4 pt-4 border-t border-gray-200">
                    <button
                        @click="showMarkDeliveredModal = false"
                        class="w-full px-4 py-2 text-md font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 rounded-lg"
                    >Cancel</button>
                </div>
            </div>
        </Modal>

        <!-- Checklist template modal -->
        <Modal :show="showChecklistModal" @close="closeChecklistModal" max-width="2xl">
            <div class="p-6 text-gray-900 dark:text-gray-100">
                <div class="flex items-start justify-between gap-3 mb-5">
                    <div>
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white">Add delivery checklist</h3>
                        <p class="text-md text-gray-500 dark:text-gray-400 mt-1">Start from a template or define items manually.</p>
                    </div>
                    <button
                        type="button"
                        @click="closeChecklistModal"
                        class="shrink-0 rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-200 transition-colors"
                        aria-label="Close"
                    >
                        <span class="material-icons text-xl">close</span>
                    </button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-5">
                    <button
                        type="button"
                        @click="selectChecklistMode('template')"
                        :class="[
                            'p-4 rounded-xl border-2 text-left transition-colors',
                            checklistCreationMode === 'template'
                                ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/25 dark:border-primary-400'
                                : 'border-gray-200 dark:border-gray-600 bg-gray-50/80 dark:bg-gray-900/40 hover:border-gray-300 dark:hover:border-gray-500',
                        ]"
                    >
                        <p class="font-semibold text-gray-900 dark:text-white">From template</p>
                        <p class="text-md text-gray-500 dark:text-gray-400 mt-0.5">Use an existing checklist template</p>
                    </button>
                    <button
                        type="button"
                        @click="selectChecklistMode('scratch')"
                        :class="[
                            'p-4 rounded-xl border-2 text-left transition-colors',
                            checklistCreationMode === 'scratch'
                                ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-900/20 dark:border-emerald-400'
                                : 'border-gray-200 dark:border-gray-600 bg-gray-50/80 dark:bg-gray-900/40 hover:border-gray-300 dark:hover:border-gray-500',
                        ]"
                    >
                        <p class="font-semibold text-gray-900 dark:text-white">From scratch</p>
                        <p class="text-md text-gray-500 dark:text-gray-400 mt-0.5">Build a custom checklist for this delivery</p>
                    </button>
                </div>

                <div v-if="checklistCreationMode === 'template'" class="space-y-2">
                    <label class="block text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Template</label>
                    <select v-model="selectedTemplate" class="input-style">
                        <option :value="null">Choose a template…</option>
                        <option v-for="t in checklistTemplates" :key="t.id" :value="t">{{ t.name }}</option>
                    </select>
                </div>

                <div v-if="checklistCreationMode === 'scratch'" class="space-y-3">
                    <div
                        v-for="(item, idx) in newChecklistItems"
                        :key="idx"
                        class="flex flex-col sm:flex-row sm:items-center gap-2 rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50/50 dark:bg-gray-900/30 p-3"
                    >
                        <input
                            v-model="item.label"
                            type="text"
                            placeholder="Item label…"
                            class="input-style flex-1 min-w-0"
                        />
                        <select v-model="item.category" class="input-style w-full sm:w-44 shrink-0">
                            <option value="Pre Delivery">Pre delivery</option>
                            <option value="Upon Delivery">Upon delivery</option>
                        </select>
                        <button
                            type="button"
                            @click="removeChecklistItem(idx)"
                            class="inline-flex items-center justify-center rounded-lg p-2 text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/40 shrink-0 self-end sm:self-auto"
                            aria-label="Remove item"
                        >
                            <span class="material-icons text-lg">delete</span>
                        </button>
                    </div>
                    <button
                        type="button"
                        @click="addChecklistItem"
                        class="text-md font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300"
                    >
                        + Add item
                    </button>
                </div>

                <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700 flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
                    <button
                        type="button"
                        @click="closeChecklistModal"
                        class="px-4 py-2.5 text-md font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="saveChecklist"
                        :disabled="isLoadingChecklist"
                        class="px-4 py-2.5 text-md font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                        {{ isLoadingChecklist ? 'Saving…' : 'Save' }}
                    </button>
                </div>
            </div>
        </Modal>

        <!-- Single-item modal -->
        <Modal :show="showAddItemModal" @close="closeAddItemModal" max-width="md">
            <form @submit.prevent="saveNewChecklistItem" class="p-6 space-y-4 text-gray-900 dark:text-gray-100">
                <h3 class="text-xl font-semibold text-gray-900 dark:text-white">
                    {{ editingChecklistItem ? 'Edit checklist item' : 'Add checklist item' }}
                </h3>
                <div>
                    <label class="block text-md font-medium text-gray-700 dark:text-gray-300 mb-1.5">Label</label>
                    <input v-model="newItemLabel" required class="input-style" />
                </div>
                <div>
                    <label class="block text-md font-medium text-gray-700 dark:text-gray-300 mb-1.5">Category</label>
                    <select v-model="newItemCategory" class="input-style w-full">
                        <option v-for="c in categories" :key="c.id" :value="c.name">{{ c.name }}</option>
                    </select>
                </div>
                <label class="flex items-center gap-2 text-md text-gray-700 dark:text-gray-300 cursor-pointer">
                    <input
                        v-model="newItemRequired"
                        type="checkbox"
                        class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700"
                    />
                    Required
                </label>
                <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-2 border-t border-gray-200 dark:border-gray-700">
                    <button
                        type="button"
                        @click="closeAddItemModal"
                        class="px-4 py-2.5 text-md font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="!newItemLabel.trim()"
                        class="px-4 py-2.5 text-md font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        {{ editingChecklistItem ? 'Save' : 'Add' }}
                    </button>
                </div>
            </form>
        </Modal>

        <!-- Preview -->
        <Teleport to="body">
            <div v-if="showPreview" class="delivery-preview-overlay fixed inset-0 z-[100] overflow-y-auto">
                <DeliveryPreview
                    :record="record"
                    :account="account"
                    :enum-options="enumOptions"
                    :checklist-items="checklistItems"
                    @close="closePreview"
                />
            </div>
        </Teleport>
    </TenantLayout>
</template>
