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
const newItemLabel = ref('');
const newItemCategory = ref('');
const newItemRequired = ref(false);

/* ─── Status ─── */
const STATUS_STYLES = {
    scheduled: { label: 'Scheduled', cls: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200' },
    confirmed: { label: 'Confirmed', cls: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-200' },
    en_route: { label: 'En Route', cls: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200' },
    delivered: { label: 'Delivered', cls: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200' },
    cancelled: { label: 'Cancelled', cls: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200' },
    rescheduled: { label: 'Rescheduled', cls: 'bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-200' },
};
const statusInfo = computed(() => STATUS_STYLES[props.record?.status] ?? { label: props.record?.status ?? 'Unknown', cls: 'bg-gray-100 text-gray-700' });

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
    return out;
});

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

const isSigned = computed(() => !!props.record?.signed_at);

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
    newItemLabel.value = '';
    newItemCategory.value = props.categories.length > 0 ? props.categories[0].name : '';
    newItemRequired.value = false;
    showAddItemModal.value = true;
};
const closeAddItemModal = () => { showAddItemModal.value = false; };
const saveNewChecklistItem = async () => {
    if (!newItemLabel.value.trim()) return;
    try {
        await axios.post(route('deliveries.checklist.add-item', { delivery: props.record.id }), {
            label: newItemLabel.value.trim(),
            category: newItemCategory.value,
            is_required: newItemRequired.value,
        });
        showAddItemModal.value = false;
        router.reload();
    } catch (e) { console.error(e); alert('Failed to add item.'); }
};
const removeChecklistItemFromDelivery = async (item) => {
    if (!confirm(`Remove "${item.label}" from checklist?`)) return;
    try {
        await axios.delete(route('deliveries.checklist.remove-item', { delivery: props.record.id, item: item.id }));
        router.reload();
    } catch (e) { console.error(e); alert('Failed to remove item.'); }
};
const setChecklistItemCompleted = async (item, completed) => {
    try {
        await axios.put(route('deliveries.checklist.update-item', { delivery: props.record.id, item: item.id }), { completed });
        router.reload();
    } catch (e) { console.error(e); }
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
</script>

<template>
    <Head :title="`Delivery - ${record?.display_name}`" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="flex items-center justify-between mt-4 gap-4 flex-wrap">
                    <div class="flex items-center gap-3">
                        <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                            {{ record?.display_name }}
                        </h2>
                        <span :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium', statusInfo.cls]">
                            {{ statusInfo.label }}
                        </span>
                    </div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <button
                            @click="openPreview"
                            class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700"
                        >
                            <span class="material-icons text-base">visibility</span>
                            Preview
                        </button>
                        <a
                            :href="route('deliveries.print', record.id)"
                            target="_blank"
                            class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700"
                        >
                            <span class="material-icons text-base">print</span>
                            Print
                        </a>
                        <Link
                            :href="route('deliveries.edit', record.id)"
                            class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-white bg-primary-600 border border-transparent rounded-md hover:bg-primary-700"
                        >
                            <span class="material-icons text-base">edit</span>
                            Edit
                        </Link>
                        <button
                            @click="handleDelete"
                            class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-white bg-red-600 border border-transparent rounded-md hover:bg-red-700"
                        >
                            <span class="material-icons text-base">delete</span>
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
                <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">Customer</div>
                            <div class="text-base font-semibold text-gray-900 dark:text-white">
                                {{ record.customer?.display_name ?? record.customer?.contact?.display_name ?? '—' }}
                            </div>
                            <div v-if="record.customer?.contact?.email" class="text-sm text-gray-500 mt-0.5">{{ record.customer.contact.email }}</div>
                            <div v-if="record.customer?.contact?.phone" class="text-sm text-gray-500">{{ record.customer.contact.phone }}</div>
                        </div>
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">Deliver To</div>
                            <div class="text-sm font-medium text-gray-900 dark:text-white">
                                {{ deliverToSummary?.type ?? 'Custom Address' }}
                                <span v-if="deliverToSummary?.name" class="text-gray-500 font-normal">· {{ deliverToSummary.name }}</span>
                            </div>
                            <div v-if="record.address_line_1" class="text-sm text-gray-600 dark:text-gray-400 mt-1 space-y-0.5">
                                <div>{{ record.address_line_1 }}</div>
                                <div v-if="record.address_line_2">{{ record.address_line_2 }}</div>
                                <div>
                                    <span v-if="record.city">{{ record.city }}</span><span v-if="record.state">, {{ record.state }}</span>
                                    <span v-if="record.postal_code"> {{ record.postal_code }}</span>
                                </div>
                            </div>
                            <div v-else class="text-sm text-gray-400 italic mt-1">No address on file</div>
                        </div>
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">Schedule</div>
                            <dl class="text-sm space-y-1">
                                <div class="flex gap-2">
                                    <dt class="text-gray-500 w-24 shrink-0">Scheduled</dt>
                                    <dd class="text-gray-900 dark:text-white">{{ formatDateTime(record.scheduled_at) }}</dd>
                                </div>
                                <div class="flex gap-2">
                                    <dt class="text-gray-500 w-24 shrink-0">ETA</dt>
                                    <dd class="text-gray-900 dark:text-white">{{ formatDateTime(record.estimated_arrival_at) }}</dd>
                                </div>
                                <div class="flex gap-2">
                                    <dt class="text-gray-500 w-24 shrink-0">Delivered</dt>
                                    <dd class="text-gray-900 dark:text-white">{{ formatDateTime(record.delivered_at) }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </div>

                <!-- Assets -->
                <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Assets to Deliver</h3>
                        <div v-if="items.length" class="text-sm text-gray-500">
                            {{ items.filter(i => i.delivered_at).length }} / {{ items.length }} delivered
                        </div>
                    </div>

                    <div v-if="items.length === 0" class="px-6 py-10 text-center text-sm text-gray-500">
                        No assets tied to this delivery yet. Edit the delivery to link a source or add assets.
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900/40">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Asset</th>
                                    <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Variant</th>
                                    <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Unit / Serial</th>
                                    <th class="px-4 py-2 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Qty</th>
                                    <th class="px-4 py-2 text-center text-xs font-semibold uppercase tracking-wide text-gray-500">Delivered</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                <tr v-for="item in items" :key="item.id">
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">
                                        <div class="font-medium">{{ itemName(item) }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ itemVariantLabel(item) ?? '—' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ itemUnitLabel(item) ?? '—' }}</td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-700 dark:text-gray-200">{{ Number(item.quantity ?? 1) }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <label class="inline-flex items-center gap-2 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                :checked="!!item.delivered_at"
                                                :disabled="isSigned"
                                                @change="toggleItemDelivered(item)"
                                                class="h-5 w-5 rounded border-gray-300 text-green-600 focus:ring-green-500"
                                            />
                                            <span v-if="item.delivered_at" class="text-xs text-green-700 dark:text-green-300">
                                                {{ formatDate(item.delivered_at) }}
                                            </span>
                                        </label>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Checklist (existing) -->
                <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Delivery Checklist</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                Items to complete before and during delivery
                                <span v-if="isSigned" class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300">
                                    <span class="material-icons text-sm mr-1">lock</span>
                                    Signed
                                </span>
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <button
                                v-if="!isSigned"
                                @click="addChecklistItemToDelivery"
                                class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20 hover:bg-blue-100 dark:hover:bg-blue-900/40 rounded-lg"
                            >
                                <span class="material-icons text-base">add</span>
                                Add Item
                            </button>
                            <button
                                v-if="!isSigned && checklistItems.length === 0"
                                @click="openChecklistModal"
                                class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-green-600 bg-green-50 hover:bg-green-100 rounded-lg"
                            >
                                <span class="material-icons text-base">description</span>
                                Use Template
                            </button>
                        </div>
                    </div>

                    <div v-if="checklistItems.length > 0" class="p-6 space-y-6">
                        <div v-for="category in itemsByCategory" :key="category.id">
                            <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-3 flex items-center">
                                <span class="w-2 h-2 rounded-full mr-2 bg-blue-500"></span>
                                {{ category.name }}
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div v-for="item in category.items" :key="item.id" class="border border-gray-200 dark:border-gray-600 rounded-lg p-3">
                                    <div class="flex items-start justify-between mb-2 gap-2">
                                        <p class="text-sm font-medium text-gray-900 dark:text-white flex-1">
                                            {{ item.label }}
                                            <span v-if="item.is_required" class="text-red-500">*</span>
                                        </p>
                                        <button
                                            v-if="!isSigned"
                                            @click="removeChecklistItemFromDelivery(item)"
                                            class="flex-shrink-0 h-6 w-6 rounded flex items-center justify-center text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20"
                                        >
                                            <span class="material-icons text-sm">delete</span>
                                        </button>
                                    </div>
                                    <div class="flex gap-2">
                                        <button
                                            type="button"
                                            @click="setChecklistItemCompleted(item, true)"
                                            :disabled="isSigned"
                                            :class="[
                                                'flex-1 px-2 py-1 text-xs font-medium rounded',
                                                item.completed ? 'bg-green-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400',
                                            ]"
                                        >✓ True</button>
                                        <button
                                            type="button"
                                            @click="setChecklistItemCompleted(item, false)"
                                            :disabled="isSigned"
                                            :class="[
                                                'flex-1 px-2 py-1 text-xs font-medium rounded',
                                                !item.completed ? 'bg-red-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400',
                                            ]"
                                        >✗ False</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="px-6 py-8 text-center text-sm text-gray-500">
                        No checklist items.
                    </div>
                </div>

                <!-- Notes -->
                <div v-if="record.internal_notes || record.customer_notes" class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6 space-y-4">
                    <div v-if="record.internal_notes">
                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">Internal Notes</div>
                        <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ record.internal_notes }}</p>
                    </div>
                    <div v-if="record.customer_notes">
                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">Customer Notes</div>
                        <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ record.customer_notes }}</p>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="lg:col-span-4 space-y-6">
                <!-- Actions -->
                <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-4">Actions</h3>
                    <div class="space-y-3">
                        <button
                            @click="markAsDelivered"
                            :disabled="record.delivered_at"
                            class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-green-600 hover:bg-green-700 disabled:bg-gray-400 disabled:cursor-not-allowed rounded-lg"
                        >
                            <span class="material-icons text-base">check_circle</span>
                            {{ record.delivered_at ? 'Delivered' : 'Complete Delivery' }}
                        </button>
                        <button
                            @click="viewSignatureRequest"
                            class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg"
                        >
                            <span class="material-icons text-base">visibility</span>
                            View Signature Request
                        </button>
                    </div>
                </div>

                <!-- Info -->
                <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-4">Delivery Info</h3>
                    <dl class="text-sm space-y-2">
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Status</dt>
                            <dd :class="['inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium', statusInfo.cls]">{{ statusInfo.label }}</dd>
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
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-4">Related Records</h3>
                    <ul class="space-y-2">
                        <li v-for="rel in relatedRecords" :key="rel.label + rel.name" class="flex items-center justify-between gap-3">
                            <div>
                                <div class="text-xs uppercase tracking-wide text-gray-500">{{ rel.label }}</div>
                                <div class="text-sm text-gray-900 dark:text-white">{{ rel.name }}</div>
                            </div>
                            <a :href="rel.href" class="text-primary-600 hover:text-primary-700 text-sm inline-flex items-center gap-1">
                                <span class="material-icons text-sm">open_in_new</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <Modal :show="showDeleteModal" @close="cancelDelete" max-width="md">
            <div class="p-6 text-center">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Delete Delivery</h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Are you sure you want to delete {{ record?.display_name }}? This cannot be undone.
                </p>
                <div class="mt-6 flex justify-center gap-3">
                    <button
                        @click="confirmDelete"
                        :disabled="isDeleting"
                        class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-md disabled:opacity-50"
                    >
                        {{ isDeleting ? 'Deleting...' : 'Delete' }}
                    </button>
                    <button
                        @click="cancelDelete"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 rounded-md"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </Modal>

        <!-- Mark delivered modal -->
        <Modal :show="showMarkDeliveredModal" @close="showMarkDeliveredModal = false" max-width="md">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Complete Delivery</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Choose how you want to complete this delivery:</p>
                <div class="space-y-3">
                    <button
                        @click="sendSignatureRequest"
                        class="w-full flex items-center gap-3 p-4 border border-blue-200 bg-blue-50 hover:bg-blue-100 rounded-lg text-left"
                    >
                        <span class="material-icons text-blue-600">send</span>
                        <div>
                            <div class="font-medium text-gray-900">Send Signature Request</div>
                            <div class="text-sm text-gray-600">Request customer signature via email</div>
                        </div>
                    </button>
                    <button
                        @click="markDeliveredWithoutSignature"
                        class="w-full flex items-center gap-3 p-4 border border-green-200 bg-green-50 hover:bg-green-100 rounded-lg text-left"
                    >
                        <span class="material-icons text-green-600">check_circle</span>
                        <div>
                            <div class="font-medium text-gray-900">Mark as Delivered</div>
                            <div class="text-sm text-gray-600">Complete without customer signature</div>
                        </div>
                    </button>
                </div>
                <div class="mt-4 pt-4 border-t border-gray-200">
                    <button
                        @click="showMarkDeliveredModal = false"
                        class="w-full px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 rounded-lg"
                    >Cancel</button>
                </div>
            </div>
        </Modal>

        <!-- Checklist template modal -->
        <Modal :show="showChecklistModal" @close="closeChecklistModal" max-width="2xl">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Add Delivery Checklist</h3>
                    <button @click="closeChecklistModal" class="text-gray-400 hover:text-gray-600">
                        <span class="material-icons">close</span>
                    </button>
                </div>

                <div class="grid grid-cols-2 gap-3 mb-4">
                    <button
                        type="button"
                        @click="selectChecklistMode('template')"
                        :class="[
                            'p-4 rounded-lg border-2 text-left',
                            checklistCreationMode === 'template' ? 'border-blue-500 bg-blue-50' : 'border-gray-200'
                        ]"
                    >
                        <p class="font-semibold text-gray-900">From Template</p>
                        <p class="text-sm text-gray-500">Use an existing template</p>
                    </button>
                    <button
                        type="button"
                        @click="selectChecklistMode('scratch')"
                        :class="[
                            'p-4 rounded-lg border-2 text-left',
                            checklistCreationMode === 'scratch' ? 'border-green-500 bg-green-50' : 'border-gray-200'
                        ]"
                    >
                        <p class="font-semibold text-gray-900">Create from Scratch</p>
                        <p class="text-sm text-gray-500">Build a custom checklist</p>
                    </button>
                </div>

                <div v-if="checklistCreationMode === 'template'" class="space-y-2">
                    <select v-model="selectedTemplate" class="block w-full rounded-md border-gray-300">
                        <option :value="null">Choose a template…</option>
                        <option v-for="t in checklistTemplates" :key="t.id" :value="t">{{ t.name }}</option>
                    </select>
                </div>

                <div v-if="checklistCreationMode === 'scratch'" class="space-y-2">
                    <div v-for="(item, idx) in newChecklistItems" :key="idx" class="flex gap-2">
                        <input v-model="item.label" placeholder="Item label…" class="flex-1 rounded-md border-gray-300" />
                        <select v-model="item.category" class="rounded-md border-gray-300 w-40">
                            <option value="Pre Delivery">Pre Delivery</option>
                            <option value="Upon Delivery">Upon Delivery</option>
                        </select>
                        <button @click="removeChecklistItem(idx)" class="text-red-600 px-2">
                            <span class="material-icons text-base">delete</span>
                        </button>
                    </div>
                    <button @click="addChecklistItem" class="text-sm text-blue-600">+ Add item</button>
                </div>

                <div class="mt-4 flex justify-end gap-2">
                    <button @click="closeChecklistModal" class="px-4 py-2 text-sm text-gray-700 border rounded">Cancel</button>
                    <button @click="saveChecklist" :disabled="isLoadingChecklist" class="px-4 py-2 text-sm text-white bg-blue-600 rounded">Save</button>
                </div>
            </div>
        </Modal>

        <!-- Single-item modal -->
        <Modal :show="showAddItemModal" @close="closeAddItemModal" max-width="md">
            <form @submit.prevent="saveNewChecklistItem" class="p-6 space-y-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Add Checklist Item</h3>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Label</label>
                    <input v-model="newItemLabel" required class="w-full rounded-md border-gray-300" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                    <select v-model="newItemCategory" class="w-full rounded-md border-gray-300">
                        <option v-for="c in categories" :key="c.id" :value="c.name">{{ c.name }}</option>
                    </select>
                </div>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" v-model="newItemRequired" /> Required
                </label>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="closeAddItemModal" class="px-4 py-2 text-sm text-gray-700 border rounded">Cancel</button>
                    <button type="submit" :disabled="!newItemLabel.trim()" class="px-4 py-2 text-sm text-white bg-blue-600 rounded">Add</button>
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
