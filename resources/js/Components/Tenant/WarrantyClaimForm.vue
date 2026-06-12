<script setup>
import RecordSelect from '@/Components/Tenant/RecordSelect.vue';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, reactive, ref, watch } from 'vue';
import { useFormValidationToast } from '@/composables/useFormValidationToast';
import { useSubsidiaryLocationAutofill } from '@/composables/useSubsidiaryLocationAutofill';

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

function mapLineItemsFromRecord(record) {
    if (!record) return [];
    const rows = record.lineItems || record.line_items || [];
    return rows.map((r) => ({
        id: r.id,
        work_order_service_item_id: r.work_order_service_item_id ?? null,
        serviceItemDisplayName:
            r.work_order_service_item?.display_name ?? r.workOrderServiceItem?.display_name ?? null,
        description: r.description ?? '',
        cost_type: r.cost_type?.value ?? r.cost_type ?? 'quantity',
        quantity: Math.max(1, parseInt(String(r.quantity), 10) || 1),
        cost: Number(r.cost) || 0,
        notes: r.notes ?? '',
    }));
}

const serviceLineDisplayName = (line) =>
    line?.serviceItemDisplayName
    || line?.work_order_service_item?.display_name
    || line?.workOrderServiceItem?.display_name
    || (line?.work_order_service_item_id ? `Work order line #${line.work_order_service_item_id}` : 'Line item');

const { validationSubmitOptions } = useFormValidationToast(() => props.fieldsSchema);

const form = useForm({
    vendor_id: merged.vendor_id ?? null,
    work_order_id: merged.work_order_id ?? null,
    subsidiary_id: merged.subsidiary_id ?? null,
    location_id: merged.location_id ?? null,
    status: rawStatus,
    notes: merged.notes ?? '',
    rejection_reason: merged.rejection_reason ?? '',
    items: props.mode === 'edit' ? mapLineItemsFromRecord(props.record) : [],
    reuse_inventory_image_ids: [],
    claim_images: [],
});

const isDraft = computed(() => {
    const s = form.status;
    const v = typeof s === 'object' && s != null && 'value' in s ? s.value : s;
    return String(v || 'draft').toLowerCase() === 'draft';
});

const lineItemsNotesOnly = computed(() => props.mode === 'edit' && !isDraft.value);

const showWorkOrderWarrantyPicker = computed(
    () =>
        selectedWorkOrderId.value != null &&
        (props.mode === 'create' || (props.mode === 'edit' && isDraft.value && form.items.length === 0)),
);

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
/** Per work-order service line id: { cost_type, quantity, cost, notes } */
const lineItemSettings = reactive({});
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
        workOrderWarrantyItems.value = showWorkOrderWarrantyPicker.value ? data.warranty_service_items || [] : [];
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

const clearLineItemSettings = () => {
    for (const k of Object.keys(lineItemSettings)) {
        delete lineItemSettings[k];
    }
};

const defaultLineSettingsFromRow = (row) => {
    const bt = Number(row.billing_type ?? 1);
    if (bt === 2) {
        const total =
            row.total_cost != null && Number.isFinite(Number(row.total_cost))
                ? Number(row.total_cost)
                : (Number(row.unit_cost) || 0) * Math.max(1, Number(row.quantity) || 1);
        return { cost_type: 'fixed', quantity: 1, cost: Math.round(total * 100) / 100, notes: '', description: '' };
    }
    const qty = Math.max(1, Math.round(Number(row.quantity) || 1));
    const unit =
        row.unit_cost != null && Number.isFinite(Number(row.unit_cost))
            ? Number(row.unit_cost)
            : row.total_cost != null && qty
              ? Number(row.total_cost) / qty
              : 0;
    return { cost_type: 'quantity', quantity: qty, cost: Math.round(unit * 100) / 100, notes: '', description: '' };
};

const ensureLineSettings = (lineId) => {
    const key = String(lineId);
    if (lineItemSettings[key]) return;
    const row = workOrderWarrantyItems.value.find((r) => Number(r.id) === Number(lineId));
    if (row) {
        lineItemSettings[key] = defaultLineSettingsFromRow(row);
    }
};

const onLineCostTypeChange = (wid) => {
    const key = String(wid);
    if (lineItemSettings[key]?.cost_type === 'fixed') {
        lineItemSettings[key].quantity = 1;
    }
};

const toggleWarrantyServiceItem = (lineId) => {
    const n = Number(lineId);
    const set = new Set(selectedWarrantyServiceItemIds.value.map(Number));
    const key = String(n);
    if (set.has(n)) {
        set.delete(n);
        delete lineItemSettings[key];
    } else {
        set.add(n);
        ensureLineSettings(n);
    }
    selectedWarrantyServiceItemIds.value = [...set];
};

const isWarrantyServiceItemSelected = (lineId) => selectedWarrantyServiceItemIds.value.map(Number).includes(Number(lineId));

const selectAllWarrantyLines = () => {
    selectedWarrantyServiceItemIds.value = workOrderWarrantyItems.value.map((r) => Number(r.id));
    workOrderWarrantyItems.value.forEach((r) => ensureLineSettings(r.id));
};

const clearWarrantyLineSelection = () => {
    selectedWarrantyServiceItemIds.value = [];
    clearLineItemSettings();
};

const createSubmitBlockedByWarrantyLines = computed(
    () =>
        showWorkOrderWarrantyPicker.value &&
        !imagesLoading.value &&
        workOrderWarrantyItems.value.length > 0 &&
        selectedWarrantyServiceItemIds.value.length === 0,
);

useSubsidiaryLocationAutofill(form, () => props.fieldsSchema, {
    guard: () => !subsidiaryLocationLockedFromWorkOrder.value,
});

watch(selectedWorkOrderId, (id) => {
    selectedReuseIds.value = [];
    if (props.mode === 'create') {
        selectedWarrantyServiceItemIds.value = [];
        clearLineItemSettings();
        workOrderWarrantyItems.value = [];
    }
    if (!id) {
        workOrderImages.value = [];
        serviceTicketImages.value = [];
        if (props.mode === 'create') {
            form.subsidiary_id = null;
            form.location_id = null;
        }
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

const buildCreateItemsFromWoSelection = () => {
    if (selectedWorkOrderId.value == null || workOrderWarrantyItems.value.length === 0) {
        return [];
    }
    return selectedWarrantyServiceItemIds.value.map((wid) => {
        const key = String(wid);
        const row = workOrderWarrantyItems.value.find((r) => Number(r.id) === Number(wid));
        const defaults = row ? defaultLineSettingsFromRow(row) : { cost_type: 'quantity', quantity: 1, cost: 0, notes: '', description: '' };
        const s = lineItemSettings[key] || defaults;
        return {
            work_order_service_item_id: Number(wid),
            description: String(s.description ?? '').trim(),
            cost_type: s.cost_type ?? defaults.cost_type,
            quantity: s.quantity ?? defaults.quantity,
            cost: Number(s.cost ?? defaults.cost),
            notes: s.notes ?? '',
        };
    });
};

const buildEditItemsPayload = () => {
    if (lineItemsNotesOnly.value) {
        return form.items.map((row) => ({ id: row.id, notes: row.notes ?? '' }));
    }
    return form.items.map((row) => ({
        ...(row.id != null && row.id !== '' ? { id: row.id } : {}),
        work_order_service_item_id: row.work_order_service_item_id ?? null,
        description: row.description ?? '',
        cost_type: row.cost_type ?? 'quantity',
        quantity: Math.max(1, Number(row.quantity) || 1),
        cost: Number(row.cost) || 0,
        notes: row.notes ?? '',
    }));
};

const editLinePreviewTotal = (line) => {
    const type = line.cost_type === 'fixed' ? 'fixed' : 'quantity';
    const qty = type === 'fixed' ? 1 : Math.max(1, Number(line.quantity) || 1);
    const cost = Number(line.cost) || 0;
    if (type === 'fixed') return Math.round(cost * 100) / 100;
    return Math.round(qty * cost * 100) / 100;
};

const onEditLineCostTypeChange = (idx) => {
    const line = form.items[idx];
    if (line?.cost_type === 'fixed') {
        line.quantity = 1;
    }
};

const submit = () => {
    if (props.mode === 'create') {
        form.reuse_inventory_image_ids = [...selectedReuseIds.value];
        form.claim_images = additionalImageFiles.value.length ? [...additionalImageFiles.value] : [];
        if (selectedWorkOrderId.value != null && workOrderWarrantyItems.value.length > 0) {
            form.items = buildCreateItemsFromWoSelection();
        } else {
            form.items = [];
        }
        form.post(route('warrantyclaims.store'), validationSubmitOptions({ forceFormData: true }));
    } else if (props.record?.id != null) {
        form
            .transform((data) => {
                let itemsPayload;
                if (isDraft.value && form.items.length === 0 && showWorkOrderWarrantyPicker.value) {
                    itemsPayload = buildCreateItemsFromWoSelection();
                } else {
                    itemsPayload = buildEditItemsPayload();
                }
                return { ...data, items: itemsPayload };
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
                                            field-key="vendor_id"
                                            :record="record"
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
                                            field-key="work_order_id"
                                            :record="record"
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
                                                field-key="subsidiary_id"
                                                :record="record"
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
                                                field-key="location_id"
                                                :record="record"
                                                :disabled="subsidiaryLocationLockedFromWorkOrder || !form.subsidiary_id"
                                                :field="fieldOr('location_id', { type: 'record', typeDomain: 'Location', label: 'Location', filterby: 'subsidiary_id' })"
                                                :enum-options="enumOptions.location_id ?? []"
                                                filter-by="subsidiary_id"
                                                :filter-value="form.subsidiary_id"
                                            />
                                            <p
                                                v-if="!subsidiaryLocationLockedFromWorkOrder && !form.subsidiary_id"
                                                class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                                            >
                                                Select a subsidiary to choose a location.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Manufacturer warranty lines (create, or draft edit with no lines yet + work order) -->
                            <div
                                v-if="showWorkOrderWarrantyPicker"
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
                                                <th scope="col" class="px-3 py-2 text-right font-medium text-gray-700 dark:text-gray-300">Unit cost</th>
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
                                                    {{ formatMoney(row.unit_cost) }}
                                                </td>
                                                <td class="px-3 py-2 align-top text-gray-600 dark:text-gray-400">
                                                    {{ row.warranty_type_label || row.warranty_type || '—' }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div
                                    v-if="
                                        !imagesLoading
                                        && workOrderWarrantyItems.length > 0
                                        && selectedWarrantyServiceItemIds.length > 0
                                    "
                                    class="mt-4 space-y-4 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50/80 dark:bg-gray-900/30 p-4"
                                >
                                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Costs and details for selected lines</h4>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        <span class="font-medium text-gray-600 dark:text-gray-300">Quantity × cost:</span>
                                        line total = quantity × cost (per unit).
                                        <span class="font-medium text-gray-600 dark:text-gray-300"> Fixed total:</span>
                                        line total = the cost amount only.
                                    </p>
                                    <div
                                        v-for="wid in selectedWarrantyServiceItemIds"
                                        :key="'linecfg-' + wid"
                                        class="space-y-2 border-b border-gray-200 pb-4 last:border-0 last:pb-0 dark:border-gray-700"
                                    >
                                        <div v-if="lineItemSettings[String(wid)]" class="space-y-3">
                                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                {{ workOrderWarrantyItems.find((r) => Number(r.id) === Number(wid))?.display_name }}
                                            </div>
                                            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                                <div>
                                                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Cost type</label>
                                                    <select
                                                        v-model="lineItemSettings[String(wid)].cost_type"
                                                        class="w-full rounded-lg border border-gray-300 bg-white px-2 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                                                        @change="onLineCostTypeChange(wid)"
                                                    >
                                                        <option value="quantity">Quantity × cost</option>
                                                        <option value="fixed">Fixed total</option>
                                                    </select>
                                                </div>
                                                <div v-if="lineItemSettings[String(wid)].cost_type === 'quantity'">
                                                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Quantity</label>
                                                    <input
                                                        v-model.number="lineItemSettings[String(wid)].quantity"
                                                        type="number"
                                                        min="1"
                                                        step="1"
                                                        class="w-full rounded-lg border border-gray-300 bg-white px-2 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                                                    />
                                                </div>
                                                <div :class="lineItemSettings[String(wid)].cost_type === 'quantity' ? '' : 'sm:col-span-2'">
                                                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">
                                                        {{
                                                            lineItemSettings[String(wid)].cost_type === 'fixed'
                                                                ? 'Total cost'
                                                                : 'Cost per unit'
                                                        }}
                                                    </label>
                                                    <input
                                                        v-model.number="lineItemSettings[String(wid)].cost"
                                                        type="number"
                                                        min="0"
                                                        step="0.01"
                                                        class="w-full rounded-lg border border-gray-300 bg-white px-2 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                                                    />
                                                </div>
                                            </div>
                                            <div>
                                                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Service description (optional)</label>
                                                <textarea
                                                    v-model="lineItemSettings[String(wid)].description"
                                                    rows="2"
                                                    class="w-full rounded-lg border border-gray-300 bg-white px-2 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                                                    placeholder="Additional context for this line on the claim…"
                                                />
                                            </div>
                                            <div>
                                                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Vendor feedback (optional)</label>
                                                <textarea
                                                    v-model="lineItemSettings[String(wid)].notes"
                                                    rows="2"
                                                    class="w-full rounded-lg border border-gray-300 bg-white px-2 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                                                    placeholder="Notes for the manufacturer on this line…"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <p
                                    v-if="createSubmitBlockedByWarrantyLines"
                                    class="text-sm font-medium text-amber-700 dark:text-amber-400"
                                >
                                    Select at least one warranty line to {{ mode === 'create' ? 'create' : 'save' }} this claim.
                                </p>
                            </div>

                            <!-- Line items (edit) -->
                            <div
                                v-if="mode === 'edit'"
                                class="border-t border-gray-200 dark:border-gray-700 pt-6 space-y-4"
                            >
                                <div>
                                    <h3 class="text-base font-semibold text-gray-900 dark:text-white uppercase tracking-wide">
                                        Line items
                                    </h3>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        <template v-if="lineItemsNotesOnly">
                                            Only vendor feedback per line can be changed after the claim leaves draft.
                                        </template>
                                        <template v-else>
                                            Set cost type and amounts while the claim is in draft. Optional service description and vendor feedback are shown under each line.
                                        </template>
                                    </p>
                                </div>
                                <div
                                    v-if="form.items.length === 0"
                                    class="rounded-lg border border-dashed border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/30 px-4 py-3 text-sm text-gray-600 dark:text-gray-400"
                                >
                                    No line items on this claim yet.
                                    <span v-if="isDraft && selectedWorkOrderId != null" class="block mt-1 text-gray-500 dark:text-gray-500">
                                        Select manufacturer warranty lines from the work order above to add them.
                                    </span>
                                </div>
                                <div v-else class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                                            <tr>
                                                <th scope="col" class="px-3 py-2 text-left font-medium text-gray-700 dark:text-gray-300 w-40">Cost type</th>
                                                <th scope="col" class="px-3 py-2 text-right font-medium text-gray-700 dark:text-gray-300 w-24">Qty</th>
                                                <th scope="col" class="px-3 py-2 text-right font-medium text-gray-700 dark:text-gray-300 w-28">Cost</th>
                                                <th scope="col" class="px-3 py-2 text-right font-medium text-gray-700 dark:text-gray-300 w-28">Line total</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
                                            <template v-for="(line, idx) in form.items" :key="'edit-line-' + (line.id ?? idx)">
                                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 align-top">
                                                    <td class="px-3 py-2">
                                                        <select
                                                            v-if="!lineItemsNotesOnly"
                                                            v-model="form.items[idx].cost_type"
                                                            class="w-full rounded-lg border border-gray-300 bg-white px-2 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                                                            @change="onEditLineCostTypeChange(idx)"
                                                        >
                                                            <option value="quantity">Quantity × cost</option>
                                                            <option value="fixed">Fixed total</option>
                                                        </select>
                                                        <span v-else class="text-gray-600 dark:text-gray-400">{{ line.cost_type === 'fixed' ? 'Fixed total' : 'Quantity × cost' }}</span>
                                                    </td>
                                                    <td class="px-3 py-2 text-right tabular-nums">
                                                        <input
                                                            v-if="!lineItemsNotesOnly && form.items[idx].cost_type === 'quantity'"
                                                            v-model.number="form.items[idx].quantity"
                                                            type="number"
                                                            min="1"
                                                            step="1"
                                                            class="w-full max-w-[6rem] ml-auto rounded-lg border border-gray-300 bg-white px-2 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                                                        />
                                                        <span v-else-if="lineItemsNotesOnly" class="text-gray-700 dark:text-gray-300">{{ warrantyLineQtyDisplay(line) }}</span>
                                                        <span v-else class="text-gray-700 dark:text-gray-300 tabular-nums">1</span>
                                                    </td>
                                                    <td class="px-3 py-2 text-right tabular-nums">
                                                        <input
                                                            v-if="!lineItemsNotesOnly"
                                                            v-model.number="form.items[idx].cost"
                                                            type="number"
                                                            min="0"
                                                            step="0.01"
                                                            class="w-full max-w-[7rem] ml-auto rounded-lg border border-gray-300 bg-white px-2 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                                                        />
                                                        <span v-else class="text-gray-700 dark:text-gray-300">{{ formatMoney(line.cost) }}</span>
                                                    </td>
                                                    <td class="px-3 py-2 text-right tabular-nums text-gray-700 dark:text-gray-300">
                                                        {{ formatMoney(editLinePreviewTotal(line)) }}
                                                    </td>
                                                </tr>
                                                <tr class="bg-gray-50/90 dark:bg-gray-900/40">
                                                    <td colspan="4" class="px-3 py-3 border-t border-gray-100 dark:border-gray-700 align-top">
                                                        <div class="space-y-3">
                                                            <div>
                                                                <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">
                                                                    Service line
                                                                </div>
                                                                <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                                    {{ serviceLineDisplayName(line) }}
                                                                </div>
                                                            </div>
                                                            <div v-if="!lineItemsNotesOnly">
                                                                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Service description (optional)</label>
                                                                <textarea
                                                                    v-model="form.items[idx].description"
                                                                    rows="2"
                                                                    class="w-full rounded-lg border border-gray-300 bg-white px-2 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                                                                    placeholder="Additional context on the claim for this line…"
                                                                />
                                                            </div>
                                                            <div v-else-if="(line.description || '').trim()">
                                                                <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">
                                                                    Service description
                                                                </div>
                                                                <div class="text-sm text-gray-800 dark:text-gray-200 whitespace-pre-wrap">{{ line.description }}</div>
                                                            </div>
                                                            <div>
                                                                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Vendor feedback</label>
                                                                <textarea
                                                                    v-model="form.items[idx].notes"
                                                                    rows="2"
                                                                    class="w-full rounded-lg border border-gray-300 bg-white px-2 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                                                                    placeholder="Notes for the manufacturer on this line…"
                                                                />
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
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
                        <div class="flex justify-between items-center p-4 sm:px-5 w-full font-semibold text-gray-900 bg-gray-100 dark:text-white dark:bg-gray-700">
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