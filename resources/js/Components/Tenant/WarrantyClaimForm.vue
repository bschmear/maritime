<script setup>
import RecordSelect from '@/Components/Tenant/RecordSelect.vue';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    record: { type: Object, default: null },
    mode: { type: String, default: 'create' },
    formSchema: { type: Object, default: null },
    fieldsSchema: { type: Object, required: true },
    enumOptions: { type: Object, default: () => ({}) },
    prefill: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['cancel']);

const statusEnumKey = 'App\\Enums\\WarrantyClaim\\Status';
const statusOptions = computed(() => props.enumOptions[statusEnumKey] || []);

const merged = { ...props.prefill, ...(props.record ?? {}) };
const rawStatus = merged.status?.value ?? merged.status ?? 'draft';

const form = useForm({
    vendor_id: merged.vendor_id ?? null,
    work_order_id: merged.work_order_id ?? null,
    subsidiary_id: merged.subsidiary_id ?? null,
    location_id: merged.location_id ?? null,
    status: rawStatus,
    notes: merged.notes ?? '',
    rejection_reason: merged.rejection_reason ?? '',
    items: [],
    reuse_inventory_image_ids: [],
    claim_images: [],
});

const resolveWorkOrderId = (raw) => {
    if (raw == null || raw === '') return null;
    if (typeof raw === 'object' && raw != null && 'id' in raw) {
        const n = Number(raw.id);
        return Number.isFinite(n) && n > 0 ? n : null;
    }
    const n = Number(raw);
    return Number.isFinite(n) && n > 0 ? n : null;
};

const selectedWorkOrderId = computed(() => resolveWorkOrderId(form.work_order_id));
const subsidiaryLocationLockedFromWorkOrder = computed(() => selectedWorkOrderId.value != null);

const workOrderImages = ref([]);
const serviceTicketImages = ref([]);
const imagesLoading = ref(false);
const workOrderWarrantyItems = ref([]);
const selectedWarrantyServiceItemIds = ref([]);
const selectedReuseIds = ref([]);
const additionalImageFiles = ref([]);
const extraFileInput = ref(null);

const fetchWorkOrderContext = async (workOrderId) => {
    const id = resolveWorkOrderId(workOrderId);
    if (!id) {
        workOrderImages.value = [];
        serviceTicketImages.value = [];
        workOrderWarrantyItems.value = [];
        return;
    }
    imagesLoading.value = true;
    try {
        const { data } = await axios.get(route('warrantyclaims.by-work-order.service-ticket-images', { workorder: id }), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        workOrderImages.value = props.mode === 'create' ? data.work_order_images || [] : [];
        serviceTicketImages.value = props.mode === 'create' ? data.service_ticket_images || [] : [];
        workOrderWarrantyItems.value = props.mode === 'create' ? data.warranty_service_items || [] : [];
        form.subsidiary_id = data.subsidiary_id ?? null;
        form.location_id = data.location_id ?? null;
    } catch {
        workOrderImages.value = [];
        serviceTicketImages.value = [];
        workOrderWarrantyItems.value = [];
    } finally {
        imagesLoading.value = false;
    }
};

const formatMoney = (value) => {
    if (value == null || Number.isNaN(Number(value))) {
        return '—';
    }
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(Number(value));
};

const warrantyLineQtyDisplay = (row) => {
    const q = Number(row?.quantity);
    if (!Number.isFinite(q)) return '—';
    return Number.isInteger(q) ? String(q) : q.toFixed(2);
};

const toggleWarrantyServiceItem = (lineId) => {
    const n = Number(lineId);
    const set = new Set(selectedWarrantyServiceItemIds.value.map(Number));
    set.has(n) ? set.delete(n) : set.add(n);
    selectedWarrantyServiceItemIds.value = [...set];
};

const isWarrantyServiceItemSelected = (lineId) => selectedWarrantyServiceItemIds.value.map(Number).includes(Number(lineId));

const selectAllWarrantyLines = () => {
    selectedWarrantyServiceItemIds.value = workOrderWarrantyItems.value.map((r) => Number(r.id));
};

const clearWarrantyLineSelection = () => {
    selectedWarrantyServiceItemIds.value = [];
};

const createSubmitBlockedByWarrantyLines = computed(
    () =>
        props.mode === 'create' &&
        selectedWorkOrderId.value != null &&
        !imagesLoading.value &&
        workOrderWarrantyItems.value.length > 0 &&
        selectedWarrantyServiceItemIds.value.length === 0,
);

watch(selectedWorkOrderId, (id) => {
    selectedReuseIds.value = [];
    selectedWarrantyServiceItemIds.value = [];
    workOrderWarrantyItems.value = [];
    if (!id) {
        workOrderImages.value = [];
        serviceTicketImages.value = [];
        form.subsidiary_id = null;
        form.location_id = null;
        return;
    }
    fetchWorkOrderContext(id);
}, { immediate: true });

const toggleReuseImage = (id) => {
    const n = Number(id);
    const set = new Set(selectedReuseIds.value.map(Number));
    set.has(n) ? set.delete(n) : set.add(n);
    selectedReuseIds.value = [...set];
};

const isReuseSelected = (id) => selectedReuseIds.value.map(Number).includes(Number(id));
const imageThumbUrl = (img) => img?.url || '';

const onExtraFilesChange = (event) => {
    const files = Array.from(event.target.files || []).filter((f) => f.type.startsWith('image/'));
    additionalImageFiles.value = [...additionalImageFiles.value, ...files];
    event.target.value = '';
};

const removeExtraFile = (index) => {
    additionalImageFiles.value = additionalImageFiles.value.filter((_, i) => i !== index);
};

const submit = () => {
    if (props.mode === 'create') {
        form.reuse_inventory_image_ids = [...selectedReuseIds.value];
        form.claim_images = additionalImageFiles.value.length ? [...additionalImageFiles.value] : [];
        if (selectedWorkOrderId.value != null && workOrderWarrantyItems.value.length > 0) {
            form.items = selectedWarrantyServiceItemIds.value.map((wid) => ({ work_order_service_item_id: Number(wid) }));
        } else {
            form.items = [];
        }
        form.post(route('warrantyclaims.store'), { forceFormData: true });
    } else if (props.record?.id != null) {
        form
            .transform((data) => {
                const { reuse_inventory_image_ids: _r, claim_images: _c, items: _i, ...rest } = data;
                return rest;
            })
            .put(route('warrantyclaims.update', props.record.id));
    }
};

const fieldOr = (key, fallback) => props.fieldsSchema[key] ?? fallback;
</script>

<template>
    <div class="w-full flex flex-col space-y-4 md:space-y-6">
        <form @submit.prevent="submit">
            <div class="grid gap-4 lg:gap-6 lg:grid-cols-12">

                <!-- Main Content -->
                <div class="lg:col-span-9 space-y-6">

                    <!-- Header Card -->
                    <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden">
                        <div class="bg-gradient-to-r from-blue-600 to-blue-700 dark:from-blue-700 dark:to-blue-800 px-6 py-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h1 class="text-2xl font-bold text-white">
                                        {{ mode === 'edit' ? 'EDIT' : 'WARRANTY CLAIM' }}
                                    </h1>
                                    <p class="text-blue-100 text-base mt-1">
                                        {{ mode === 'edit' ? 'Update Warranty Claim' : 'New Claim Form' }}
                                    </p>
                                </div>
                                <div class="text-right" v-if="record?.claim_number">
                                    <div class="text-white text-base font-medium">Claim #</div>
                                    <div class="text-white text-xl font-mono">{{ record.claim_number }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="p-6 space-y-6">

                            <!-- Auto-number note -->
                            <div v-if="mode === 'create'" class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg px-4 py-3 flex items-center gap-3">
                                <span class="material-icons text-blue-500 dark:text-blue-400 text-base shrink-0">info</span>
                                <p class="text-base text-blue-700 dark:text-blue-300">
                                    A claim reference (e.g. WCL-1001) is assigned automatically when you save.
                                </p>
                            </div>

                            <!-- Claim Details -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-4">
                                    <h3 class="text-base font-semibold text-gray-900 dark:text-white uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700">
                                        Claim Details
                                    </h3>

                                    <!-- Vendor -->
                                    <div>
                                        <label class="block text-base font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Manufacturer (vendor)
                                        </label>
                                        <RecordSelect
                                            id="warranty-claim-vendor"
                                            v-model="form.vendor_id"
                                            :field="fieldOr('vendor_id', { type: 'record', typeDomain: 'Vendor', label: 'Manufacturer (vendor)' })"
                                            :enum-options="enumOptions.vendor_id ?? []"
                                        />
                                    </div>

                                    <!-- Work Order -->
                                    <div>
                                        <label class="block text-base font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Work order
                                        </label>
                                        <RecordSelect
                                            id="warranty-claim-work-order"
                                            v-model="form.work_order_id"
                                            :field="fieldOr('work_order_id', { type: 'record', typeDomain: 'WorkOrder', label: 'Work order' })"
                                            :enum-options="enumOptions.work_order_id ?? []"
                                        />
                                    </div>
                                </div>

                                <!-- Subsidiary & Location -->
                                <div class="space-y-4">
                                    <h3 class="text-base font-semibold text-gray-900 dark:text-white uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700">
                                        Assignment
                                    </h3>

                                    <div
                                        class="space-y-4 rounded-lg border p-4 transition-colors"
                                        :class="subsidiaryLocationLockedFromWorkOrder
                                            ? 'border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800/60'
                                            : 'border-transparent p-0'"
                                    >
                                        <p v-if="subsidiaryLocationLockedFromWorkOrder" class="text-sm text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
                                            <span class="material-icons text-base text-gray-400">lock</span>
                                            Subsidiary and location are sourced from the selected work order.
                                        </p>

                                        <div>
                                            <label class="block text-base font-medium text-gray-700 dark:text-gray-300 mb-2">Subsidiary</label>
                                            <RecordSelect
                                                id="warranty-claim-subsidiary"
                                                v-model="form.subsidiary_id"
                                                :disabled="subsidiaryLocationLockedFromWorkOrder"
                                                :field="fieldOr('subsidiary_id', { type: 'record', typeDomain: 'Subsidiary', label: 'Subsidiary' })"
                                                :enum-options="enumOptions.subsidiary_id ?? []"
                                            />
                                        </div>

                                        <div>
                                            <label class="block text-base font-medium text-gray-700 dark:text-gray-300 mb-2">Location</label>
                                            <RecordSelect
                                                id="warranty-claim-location"
                                                v-model="form.location_id"
                                                :disabled="subsidiaryLocationLockedFromWorkOrder"
                                                :field="fieldOr('location_id', { type: 'record', typeDomain: 'Location', label: 'Location' })"
                                                :enum-options="enumOptions.location_id ?? []"
                                                filter-by="subsidiary_id"
                                                :filter-value="form.subsidiary_id"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Manufacturer warranty lines (create, work order selected) -->
                            <div
                                v-if="mode === 'create' && selectedWorkOrderId != null"
                                class="border-t border-gray-200 dark:border-gray-700 pt-6 space-y-4"
                            >
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <h3 class="text-base font-semibold text-gray-900 dark:text-white uppercase tracking-wide">
                                            Warranty line items
                                        </h3>
                                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                            Manufacturer warranty lines from this work order. Select which lines to include on the claim; amounts are taken from the work order when you save.
                                        </p>
                                    </div>
                                    <div
                                        v-if="!imagesLoading && workOrderWarrantyItems.length > 0"
                                        class="flex flex-wrap gap-2 shrink-0"
                                    >
                                        <button
                                            type="button"
                                            class="text-sm font-medium text-primary-600 hover:text-primary-800 dark:text-primary-400 dark:hover:text-primary-300"
                                            @click="selectAllWarrantyLines"
                                        >
                                            Select all
                                        </button>
                                        <span class="text-gray-300 dark:text-gray-600" aria-hidden="true">|</span>
                                        <button
                                            type="button"
                                            class="text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200"
                                            @click="clearWarrantyLineSelection"
                                        >
                                            Clear
                                        </button>
                                    </div>
                                </div>

                                <div v-if="imagesLoading" class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                                    <span class="material-icons text-base animate-spin">sync</span>
                                    Loading work order lines…
                                </div>
                                <div
                                    v-else-if="workOrderWarrantyItems.length === 0"
                                    class="rounded-lg border border-dashed border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/30 px-4 py-3 text-sm text-gray-600 dark:text-gray-400"
                                >
                                    No active manufacturer warranty lines on this work order. You can still save the claim without line items.
                                </div>
                                <div v-else class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                                            <tr>
                                                <th scope="col" class="w-12 px-3 py-2 text-left">
                                                    <span class="sr-only">Include</span>
                                                </th>
                                                <th scope="col" class="px-3 py-2 text-left font-medium text-gray-700 dark:text-gray-300">Line</th>
                                                <th scope="col" class="px-3 py-2 text-right font-medium text-gray-700 dark:text-gray-300">Qty</th>
                                                <th scope="col" class="px-3 py-2 text-right font-medium text-gray-700 dark:text-gray-300">Unit price</th>
                                                <th scope="col" class="px-3 py-2 text-left font-medium text-gray-700 dark:text-gray-300">Coverage</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
                                            <tr
                                                v-for="row in workOrderWarrantyItems"
                                                :key="'wosi-' + row.id"
                                                class="hover:bg-gray-50 dark:hover:bg-gray-700/50"
                                            >
                                                <td class="px-3 py-2 align-top">
                                                    <input
                                                        type="checkbox"
                                                        class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700"
                                                        :checked="isWarrantyServiceItemSelected(row.id)"
                                                        @change="toggleWarrantyServiceItem(row.id)"
                                                    />
                                                </td>
                                                <td class="px-3 py-2 align-top text-gray-900 dark:text-gray-100">
                                                    <div class="font-medium">{{ row.display_name }}</div>
                                                    <div v-if="row.description" class="mt-0.5 text-xs text-gray-500 dark:text-gray-400 line-clamp-2">
                                                        {{ row.description }}
                                                    </div>
                                                </td>
                                                <td class="px-3 py-2 align-top text-right tabular-nums text-gray-700 dark:text-gray-300">
                                                    {{ warrantyLineQtyDisplay(row) }}
                                                </td>
                                                <td class="px-3 py-2 align-top text-right tabular-nums text-gray-700 dark:text-gray-300">
                                                    {{ formatMoney(row.unit_price) }}
                                                </td>
                                                <td class="px-3 py-2 align-top text-gray-600 dark:text-gray-400">
                                                    {{ row.warranty_type_label || row.warranty_type || '—' }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <p
                                    v-if="createSubmitBlockedByWarrantyLines"
                                    class="text-sm font-medium text-amber-700 dark:text-amber-400"
                                >
                                    Select at least one warranty line to create this claim.
                                </p>
                            </div>

                            <!-- Notes & Rejection -->
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-6 space-y-4">
                                <h3 class="text-base font-semibold text-gray-900 dark:text-white uppercase tracking-wide mb-4">
                                    Notes
                                </h3>

                                <div>
                                    <label class="block text-base font-medium text-gray-700 dark:text-gray-300 mb-2" for="notes">Notes</label>
                                    <textarea
                                        id="notes"
                                        v-model="form.notes"
                                        rows="4"
                                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-base text-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-500 focus:ring-primary-500 resize-none"
                                        placeholder="Add notes about this claim…"
                                    />
                                </div>

                                <div>
                                    <label class="block text-base font-medium text-gray-700 dark:text-gray-300 mb-2" for="rejection_reason">Rejection reason</label>
                                    <textarea
                                        id="rejection_reason"
                                        v-model="form.rejection_reason"
                                        rows="3"
                                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-base text-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-500 focus:ring-primary-500 resize-none"
                                        placeholder="If rejected, describe the reason…"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Claim Images Card (create only) -->
                    <div
                        v-if="mode === 'create'"
                        class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden"
                    >
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Claim Images</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Reuse photos from the work order or its linked service ticket, and/or upload new images for this claim only.
                            </p>
                        </div>

                        <div class="p-6 space-y-6">

                            <!-- No work order selected -->
                            <div v-if="selectedWorkOrderId == null" class="text-center py-10 bg-gray-50 dark:bg-gray-900/20 rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-700">
                                <span class="material-icons text-4xl text-gray-400 dark:text-gray-600 mb-2 block">image_search</span>
                                <p class="text-base text-gray-500 dark:text-gray-400">Select a work order to pick photos from it or its linked service ticket.</p>
                            </div>

                            <!-- Work order selected -->
                            <template v-else>
                                <!-- From work order -->
                                <div>
                                    <p class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-3">From work order</p>
                                    <div v-if="imagesLoading" class="flex items-center gap-2 text-base text-gray-500 dark:text-gray-400">
                                        <span class="material-icons animate-spin text-base">sync</span>
                                        Loading images…
                                    </div>
                                    <div v-else-if="workOrderImages.length === 0" class="text-base text-gray-500 dark:text-gray-400 italic">
                                        No images on this work order yet.
                                    </div>
                                    <div v-else class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                                        <button
                                            v-for="img in workOrderImages"
                                            :key="'wo-' + img.id"
                                            type="button"
                                            class="group relative aspect-square overflow-hidden rounded-xl border-2 text-left transition focus:outline-none focus:ring-2 focus:ring-primary-500"
                                            :class="isReuseSelected(img.id)
                                                ? 'border-primary-600 ring-2 ring-primary-200 dark:border-primary-500 dark:ring-primary-900'
                                                : 'border-gray-200 dark:border-gray-600 hover:border-gray-300 dark:hover:border-gray-500'"
                                            @click="toggleReuseImage(img.id)"
                                        >
                                            <img :src="imageThumbUrl(img)" :alt="img.display_name || 'Work order image'" class="h-full w-full object-cover" loading="lazy" />
                                            <span
                                                class="absolute right-2 top-2 flex h-6 w-6 items-center justify-center rounded-full border-2 border-white text-sm font-bold shadow transition-colors"
                                                :class="isReuseSelected(img.id) ? 'bg-primary-600 text-white' : 'bg-white/90 text-gray-600'"
                                            >
                                                {{ isReuseSelected(img.id) ? '✓' : '' }}
                                            </span>
                                            <span class="absolute inset-x-0 bottom-0 truncate bg-black/50 px-2 py-1 text-sm text-white opacity-0 transition group-hover:opacity-100">
                                                {{ img.display_name }}
                                            </span>
                                        </button>
                                    </div>
                                </div>

                                <!-- From service ticket -->
                                <div class="border-t border-gray-200 dark:border-gray-700 pt-5">
                                    <p class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-3">From service ticket</p>
                                    <div v-if="imagesLoading" class="flex items-center gap-2 text-base text-gray-500 dark:text-gray-400">
                                        <span class="material-icons animate-spin text-base">sync</span>
                                        Loading images…
                                    </div>
                                    <div v-else-if="serviceTicketImages.length === 0" class="text-base text-gray-500 dark:text-gray-400 italic">
                                        No linked service ticket, or that ticket has no images yet.
                                    </div>
                                    <div v-else class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                                        <button
                                            v-for="img in serviceTicketImages"
                                            :key="'st-' + img.id"
                                            type="button"
                                            class="group relative aspect-square overflow-hidden rounded-xl border-2 text-left transition focus:outline-none focus:ring-2 focus:ring-primary-500"
                                            :class="isReuseSelected(img.id)
                                                ? 'border-primary-600 ring-2 ring-primary-200 dark:border-primary-500 dark:ring-primary-900'
                                                : 'border-gray-200 dark:border-gray-600 hover:border-gray-300 dark:hover:border-gray-500'"
                                            @click="toggleReuseImage(img.id)"
                                        >
                                            <img :src="imageThumbUrl(img)" :alt="img.display_name || 'Service ticket image'" class="h-full w-full object-cover" loading="lazy" />
                                            <span
                                                class="absolute right-2 top-2 flex h-6 w-6 items-center justify-center rounded-full border-2 border-white text-sm font-bold shadow transition-colors"
                                                :class="isReuseSelected(img.id) ? 'bg-primary-600 text-white' : 'bg-white/90 text-gray-600'"
                                            >
                                                {{ isReuseSelected(img.id) ? '✓' : '' }}
                                            </span>
                                            <span class="absolute inset-x-0 bottom-0 truncate bg-black/50 px-2 py-1 text-sm text-white opacity-0 transition group-hover:opacity-100">
                                                {{ img.display_name }}
                                            </span>
                                        </button>
                                    </div>
                                </div>
                            </template>

                            <!-- Additional uploads -->
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-5">
                                <p class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-3">Additional uploads</p>
                                <input
                                    ref="extraFileInput"
                                    type="file"
                                    multiple
                                    accept="image/*"
                                    class="hidden"
                                    @change="onExtraFilesChange"
                                />
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2.5 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors"
                                    @click="extraFileInput?.click()"
                                >
                                    <span class="material-icons text-base">add_photo_alternate</span>
                                    Choose images…
                                </button>
                                <span v-if="additionalImageFiles.length" class="ml-3 text-base text-gray-600 dark:text-gray-400">
                                    {{ additionalImageFiles.length }} file(s) ready
                                </span>
                                <ul v-if="additionalImageFiles.length" class="mt-3 space-y-2">
                                    <li
                                        v-for="(file, idx) in additionalImageFiles"
                                        :key="idx"
                                        class="flex items-center justify-between gap-2 rounded-lg bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 px-3 py-2"
                                    >
                                        <div class="flex items-center gap-2 min-w-0">
                                            <span class="material-icons text-base text-gray-400 shrink-0">image</span>
                                            <span class="text-base text-gray-700 dark:text-gray-300 truncate">{{ file.name }}</span>
                                        </div>
                                        <button
                                            type="button"
                                            class="shrink-0 inline-flex items-center gap-1 text-sm text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-200 font-medium"
                                            @click="removeExtraFile(idx)"
                                        >
                                            <span class="material-icons text-base">close</span>
                                            Remove
                                        </button>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Validation Errors -->
                    <div v-if="Object.keys(form.errors || {}).length" class="rounded-lg border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-950/40 px-4 py-3 text-base text-red-800 dark:text-red-200">
                        <p v-for="(errs, key) in form.errors" :key="key">{{ key }}: {{ Array.isArray(errs) ? errs.join(', ') : errs }}</p>
                    </div>

                </div>

                <!-- Actions Sidebar -->
                <div class="lg:col-span-3 w-full">
                    <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden sticky top-[140px]">
                        <div class="flex items-center px-5 py-4 font-semibold text-gray-900 dark:text-white bg-gray-100 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                            Actions
                        </div>

                        <div class="p-4 sm:p-5 space-y-6">

                            <!-- Status -->
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2" for="status">
                                    Status
                                </label>
                                <select
                                    id="status"
                                    v-model="form.status"
                                    class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-base text-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                >
                                    <option
                                        v-for="opt in statusOptions"
                                        :key="String(opt.value ?? opt.id)"
                                        :value="opt.value ?? opt.id"
                                    >
                                        {{ opt.name }}
                                    </option>
                                </select>
                            </div>

                            <!-- Save Actions -->
                            <div class="space-y-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                                <button
                                    type="submit"
                                    :disabled="form.processing || createSubmitBlockedByWarrantyLines"
                                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-base font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                >
                                    <span v-if="form.processing" class="material-icons text-base animate-spin">refresh</span>
                                    <span v-else class="material-icons text-base">check_circle</span>
                                    {{ mode === 'create' ? (form.processing ? 'Creating…' : 'Create claim') : (form.processing ? 'Saving…' : 'Save changes') }}
                                </button>

                                <button
                                    type="button"
                                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-base font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-colors"
                                    @click="emit('cancel')"
                                >
                                    <span class="material-icons text-base">close</span>
                                    Cancel
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </form>
    </div>
</template>