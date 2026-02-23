<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import RecordSelect from '@/Components/Tenant/RecordSelect.vue';
import AddressAutocomplete from '@/Components/AddressAutocomplete.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
    fieldsSchema:  { type: Object, required: true },
    enumOptions:   { type: Object, default: () => ({}) },
    technicians:   { type: Array,  default: () => [] },
});

const breadcrumbItems = [
    { label: 'Home',       href: route('dashboard') },
    { label: 'Deliveries', href: route('deliveries.index') },
    { label: 'New Delivery' },
];

// ── Flow state ────────────────────────────────────────────────────────────
// null = prompt, true = via work order, false = via customer
const hasWorkOrder  = ref(null);
const stepComplete  = ref(false);
const isLoading     = ref(false);

// Human-readable labels to show in the confirmation banner
const selectedWorkOrderLabel = ref('');
const selectedCustomerLabel  = ref('');
const selectedAssetLabel     = ref('');

// ── Form ──────────────────────────────────────────────────────────────────
const tomorrowNoon = (() => {
    const d = new Date();
    d.setDate(d.getDate() + 1);
    d.setHours(12, 0, 0, 0);
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T12:00`;
})();

const form = useForm({
    customer_id:          null,
    asset_unit_id:        null,
    work_order_id:        null,
    technician_id:        null,
    scheduled_at:         tomorrowNoon,
    estimated_arrival_at: null,
    status:               'scheduled',
    internal_notes:       '',
    customer_notes:       '',
    address_line_1:       '',
    address_line_2:       '',
    city:                 '',
    state:                '',
    postal_code:          '',
    country:              '',
    latitude:             null,
    longitude:            null,
});

// watch(() => form.scheduled_at, (val) => {
//     if (val && !form.estimated_arrival_at) {
//         form.estimated_arrival_at = val;
//     }
// });

// ── Helpers ───────────────────────────────────────────────────────────────
const getEnumOptions = (key) => {
    const field = props.fieldsSchema?.[key];
    if (field?.enum) return props.enumOptions[field.enum] || [];
    return [];
};

const addressAutoFilled = ref(false);

const fillAddress = (addr) => {
    if (!addr) return;
    form.address_line_1 = addr.address_line_1 || '';
    form.address_line_2 = addr.address_line_2 || '';
    form.city           = addr.city           || '';
    form.state          = addr.state          || '';
    form.postal_code    = addr.postal_code    || '';
    form.country        = addr.country        || '';
    form.latitude       = addr.latitude       ?? null;
    form.longitude      = addr.longitude      ?? null;
    addressAutoFilled.value = !!(addr.address_line_1 || addr.city);
};

const onAddressUpdate = (data) => {
    form.address_line_1 = data.street   || '';
    form.address_line_2 = data.unit     || '';
    form.city           = data.city     || '';
    form.state          = data.stateCode || data.state || '';
    form.postal_code    = data.postalCode || '';
    form.country        = data.country  || '';
    form.latitude       = data.latitude ?? null;
    form.longitude      = data.longitude ?? null;
    addressAutoFilled.value = false;
};

// ── Work order path ───────────────────────────────────────────────────────
const workOrderField = computed(() => ({
    ...props.fieldsSchema?.work_order_id,
    create: false,
    addNew: false,
}));

const onWorkOrderSelected = async (id) => {
    if (!id) return;
    isLoading.value = true;
    try {
        const { data } = await axios.get(route('deliveries.work-order-details', id));
        form.work_order_id = data.work_order_id;
        form.customer_id   = data.customer_id;
        form.asset_unit_id = data.asset_unit_id;
        selectedWorkOrderLabel.value = data.work_order_number ?? `WO #${id}`;
        selectedCustomerLabel.value  = data.customer_name     ?? '';
        selectedAssetLabel.value     = data.asset_name        ?? '';
        fillAddress(data.address);
        stepComplete.value = true;
    } catch (err) {
        console.error('Failed to fetch work order details:', err);
    } finally {
        isLoading.value = false;
    }
};

// ── Customer path ─────────────────────────────────────────────────────────
const onCustomerSelected = async (id) => {
    form.customer_id   = id;
    form.asset_unit_id = null;
    selectedAssetLabel.value = '';
    stepComplete.value = false;
    if (!id) return;
    isLoading.value = true;
    try {
        const { data } = await axios.get(route('deliveries.customer-details', id));
        selectedCustomerLabel.value = data.name ?? '';
        fillAddress(data.address);
    } catch (err) {
        console.error(err);
    } finally {
        isLoading.value = false;
    }
};

const onAssetSelected = (id) => {
    form.asset_unit_id = id;
    stepComplete.value = !!id;
};

// ── Reset ─────────────────────────────────────────────────────────────────
const resetFlow = () => {
    hasWorkOrder.value           = null;
    stepComplete.value           = false;
    selectedWorkOrderLabel.value = '';
    selectedCustomerLabel.value  = '';
    selectedAssetLabel.value     = '';
    addressAutoFilled.value      = false;
    form.reset();
};

// ── Submit ────────────────────────────────────────────────────────────────
const submit = () => {
    form.post(route('deliveries.store'), { preserveScroll: true });
};

// ── Computed display flags ────────────────────────────────────────────────
const showStep0    = computed(() => hasWorkOrder.value === null);
const showStepWO   = computed(() => hasWorkOrder.value === true  && !stepComplete.value);
const showStepCust = computed(() => hasWorkOrder.value === false && !stepComplete.value);
const showMainForm = computed(() => stepComplete.value);
</script>

<template>
    <Head title="New Delivery" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
            </div>
        </template>

        <div class="space-y-6 ml-auto mr-auto w-full">

            <!-- ── Page header ─────────────────────────────────────────── -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="h-10 w-10 rounded-lg bg-blue-100 dark:bg-blue-900/20 flex items-center justify-center">
                        <span class="material-icons text-blue-600 dark:text-blue-400">add_location_alt</span>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-900 dark:text-white">New Delivery</h1>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Schedule a vessel delivery</p>
                    </div>
                </div>
                <Link :href="route('deliveries.index')"
                    class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                    <span class="material-icons text-base">arrow_back</span>
                    Cancel
                </Link>
            </div>

            <!-- ── Step indicator ─────────────────────────────────────── -->
            <div class="flex items-center gap-2 text-xs text-gray-400 dark:text-gray-500">
                <div :class="['flex items-center gap-1.5 font-medium', showStep0 || hasWorkOrder !== null ? 'text-blue-600 dark:text-blue-400' : '']">
                    <div :class="['h-5 w-5 rounded-full flex items-center justify-center text-xs font-bold',
                        hasWorkOrder !== null ? 'bg-blue-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-500']">
                        <span v-if="hasWorkOrder !== null" class="material-icons text-xs">check</span>
                        <span v-else>1</span>
                    </div>
                    Source
                </div>
                <div class="h-px flex-1 bg-gray-200 dark:bg-gray-700" />
                <div :class="['flex items-center gap-1.5 font-medium', stepComplete ? 'text-blue-600 dark:text-blue-400' : '']">
                    <div :class="['h-5 w-5 rounded-full flex items-center justify-center text-xs font-bold',
                        stepComplete ? 'bg-blue-600 text-white' : showStepWO || showStepCust ? 'bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 ring-2 ring-blue-400' : 'bg-gray-200 dark:bg-gray-700 text-gray-500']">
                        <span v-if="stepComplete" class="material-icons text-xs">check</span>
                        <span v-else>2</span>
                    </div>
                    Select
                </div>
                <div class="h-px flex-1 bg-gray-200 dark:bg-gray-700" />
                <div :class="['flex items-center gap-1.5 font-medium', showMainForm ? 'text-blue-600 dark:text-blue-400' : '']">
                    <div :class="['h-5 w-5 rounded-full flex items-center justify-center text-xs font-bold',
                        showMainForm ? 'bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 ring-2 ring-blue-400' : 'bg-gray-200 dark:bg-gray-700 text-gray-500']">
                        3
                    </div>
                    Details
                </div>
            </div>

            <!-- ── Step 0: Prompt ──────────────────────────────────────── -->
            <Transition name="slide-up" appear>
            <div v-if="showStep0"
                class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">

                <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">How would you like to start?</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                        Link this delivery to an existing work order, or choose a customer directly.
                    </p>
                </div>

                <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-4">

                    <!-- Yes – work order -->
                    <button type="button" @click="hasWorkOrder = true"
                        class="group relative flex flex-col items-start gap-4 p-6 rounded-xl border-2 border-gray-200 dark:border-gray-700 hover:border-blue-500 dark:hover:border-blue-400 hover:shadow-md transition-all text-left">
                        <div class="h-12 w-12 rounded-xl bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center group-hover:bg-blue-200 dark:group-hover:bg-blue-900/50 transition-colors">
                            <span class="material-icons text-2xl text-blue-600 dark:text-blue-400">assignment</span>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">Link to Work Order</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 leading-relaxed">
                                Select an existing work order. Customer, asset, and address will be pre-filled automatically.
                            </p>
                        </div>
                        <span class="absolute top-4 right-4 material-icons text-gray-300 dark:text-gray-600 group-hover:text-blue-400 transition-colors text-lg">chevron_right</span>
                    </button>

                    <!-- No – customer -->
                    <button type="button" @click="hasWorkOrder = false"
                        class="group relative flex flex-col items-start gap-4 p-6 rounded-xl border-2 border-gray-200 dark:border-gray-700 hover:border-gray-400 dark:hover:border-gray-500 hover:shadow-md transition-all text-left">
                        <div class="h-12 w-12 rounded-xl bg-gray-100 dark:bg-gray-700 flex items-center justify-center group-hover:bg-gray-200 dark:group-hover:bg-gray-600 transition-colors">
                            <span class="material-icons text-2xl text-gray-600 dark:text-gray-400">person</span>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">No Work Order</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 leading-relaxed">
                                Choose a customer and vessel directly without linking to a work order.
                            </p>
                        </div>
                        <span class="absolute top-4 right-4 material-icons text-gray-300 dark:text-gray-600 group-hover:text-gray-500 transition-colors text-lg">chevron_right</span>
                    </button>

                </div>
            </div>
            </Transition>

            <!-- ── Step 1a: Select work order ─────────────────────────── -->
            <Transition name="slide-up" appear>
            <div v-if="showStepWO"
                class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">

                <div class="flex items-center gap-3 px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <button type="button" @click="resetFlow"
                        class="h-8 w-8 rounded-lg flex items-center justify-center text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                        <span class="material-icons text-lg">arrow_back</span>
                    </button>
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Select Work Order</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Customer and asset will be filled automatically</p>
                    </div>
                </div>

                <div class="p-6">
                    <RecordSelect
                        id="work_order_id"
                        :field="workOrderField"
                        :model-value="form.work_order_id"
                        @update:model-value="onWorkOrderSelected"
                        :enum-options="getEnumOptions('work_order_id')"
                        field-key="work_order_id"
                    />
                    <div v-if="isLoading" class="mt-4 flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                        <span class="material-icons text-base animate-spin">sync</span>
                        Loading work order details…
                    </div>
                </div>
            </div>
            </Transition>

            <!-- ── Step 1b: Select customer + asset ───────────────────── -->
            <Transition name="slide-up" appear>
            <div v-if="showStepCust"
                class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">

                <div class="flex items-center gap-3 px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <button type="button" @click="resetFlow"
                        class="h-8 w-8 rounded-lg flex items-center justify-center text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                        <span class="material-icons text-lg">arrow_back</span>
                    </button>
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Customer & Vessel</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Select a customer, then choose the vessel to be delivered</p>
                    </div>
                </div>

                <div class="p-6 space-y-5">

                    <!-- Customer -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                            Customer <span class="text-red-500">*</span>
                        </label>
                        <RecordSelect
                            id="customer_id"
                            :field="fieldsSchema?.customer_id"
                            :model-value="form.customer_id"
                            @update:model-value="onCustomerSelected"
                            :enum-options="getEnumOptions('customer_id')"
                            field-key="customer_id"
                        />
                    </div>

                    <!-- Asset — revealed after customer chosen -->
                    <Transition name="fade">
                    <div v-if="form.customer_id">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                            Vessel / Asset <span class="text-red-500">*</span>
                        </label>
                        <RecordSelect
                            id="asset_unit_id"
                            :field="fieldsSchema?.asset_unit_id"
                            :model-value="form.asset_unit_id"
                            @update:model-value="onAssetSelected"
                            :enum-options="getEnumOptions('asset_unit_id')"
                            field-key="asset_unit_id"
                            filter-by="customer_id"
                            :filter-value="form.customer_id"
                        />
                        <div v-if="isLoading" class="mt-2 flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                            <span class="material-icons text-sm animate-spin">sync</span>
                            Loading…
                        </div>
                    </div>
                    </Transition>

                </div>
            </div>
            </Transition>

            <!-- ── Main form ───────────────────────────────────────────── -->
            <Transition name="slide-up">
            <form v-if="showMainForm" @submit.prevent="submit" class="space-y-6">

                <!-- Confirmation banner -->
                <div class="rounded-xl border border-blue-200 dark:border-blue-900/50 bg-blue-50 dark:bg-blue-900/10 px-5 py-4">
                    <div class="flex items-start gap-3">
                        <span class="material-icons text-blue-500 dark:text-blue-400 mt-0.5 flex-shrink-0 text-lg">check_circle</span>
                        <div class="flex-1 min-w-0 flex flex-col sm:flex-row gap-4">
                            <div v-if="selectedWorkOrderLabel" class="min-w-0">
                                <p class="text-xs font-semibold text-blue-500 dark:text-blue-400 uppercase tracking-wide">Work Order</p>
                                <p class="text-sm font-semibold text-blue-900 dark:text-blue-200 truncate">{{ selectedWorkOrderLabel }}</p>
                            </div>
                            <div v-if="selectedCustomerLabel" class="min-w-0">
                                <p class="text-xs font-semibold text-blue-500 dark:text-blue-400 uppercase tracking-wide">Customer</p>
                                <p class="text-sm font-semibold text-blue-900 dark:text-blue-200 truncate">{{ selectedCustomerLabel }}</p>
                            </div>
                            <div v-if="selectedAssetLabel" class="min-w-0">
                                <p class="text-xs font-semibold text-blue-500 dark:text-blue-400 uppercase tracking-wide">Vessel</p>
                                <p class="text-sm font-semibold text-blue-900 dark:text-blue-200 truncate">{{ selectedAssetLabel }}</p>
                            </div>
                        </div>
                        <button type="button" @click="resetFlow"
                            class="flex-shrink-0 text-xs text-blue-600 dark:text-blue-400 hover:underline font-medium whitespace-nowrap">
                            Change
                        </button>
                    </div>
                </div>

                <!-- ── Scheduling ──────────────────────────────────────── -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
                    <div class="flex items-center gap-2 px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/30">
                        <span class="material-icons text-gray-500 dark:text-gray-400">calendar_today</span>
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Scheduling</h2>
                    </div>

                    <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-5">

                        <!-- Technician -->
                        <div class="sm:col-span-2 sm:max-w-sm">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Assigned Technician</label>
                            <RecordSelect
                                v-if="fieldsSchema?.technician_id"
                                id="technician_id"
                                :field="fieldsSchema.technician_id"
                                v-model="form.technician_id"
                                :enum-options="getEnumOptions('technician_id')"
                                field-key="technician_id"
                            />
                            <select v-else v-model="form.technician_id"
                                class="block w-full rounded-lg border border-gray-300 dark:border-gray-600 py-2 px-3 text-sm dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                                <option :value="null">Unassigned</option>
                                <option v-for="t in technicians" :key="t.id" :value="t.id">{{ t.name }}</option>
                            </select>
                        </div>

                        <!-- Scheduled at -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                Scheduled Date & Time <span class="text-red-500">*</span>
                            </label>
                            <input v-model="form.scheduled_at" type="datetime-local"
                                :class="['block w-full rounded-lg border py-2 px-3 text-sm dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors',
                                    form.errors.scheduled_at ? 'border-red-400' : 'border-gray-300 dark:border-gray-600']"
                            />
                            <p v-if="form.errors.scheduled_at" class="mt-1 text-xs text-red-500">{{ form.errors.scheduled_at }}</p>
                        </div>

                        <!-- Est. Arrival -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                Estimated Arrival
                                <span class="text-xs text-gray-400 font-normal">(optional)</span>
                            </label>
                            <input v-model="form.estimated_arrival_at" type="datetime-local"
                                class="block w-full rounded-lg border border-gray-300 dark:border-gray-600 py-2 px-3 text-sm dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                            />
                        </div>

                        <!-- Status -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Status</label>
                            <select v-model="form.status"
                                class="block w-full rounded-lg border border-gray-300 dark:border-gray-600 py-2 px-3 text-sm dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                                <template v-if="getEnumOptions('status').length">
                                    <option v-for="opt in getEnumOptions('status')" :key="opt.id" :value="opt.value ?? opt.id">
                                        {{ opt.name }}
                                    </option>
                                </template>
                                <template v-else>
                                    <option value="scheduled">Scheduled</option>
                                    <option value="en_route">En Route</option>
                                    <option value="rescheduled">Rescheduled</option>
                                </template>
                            </select>
                        </div>

                    </div>
                </div>

                <!-- ── Delivery Address ────────────────────────────────── -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/30">
                        <div class="flex items-center gap-2">
                            <span class="material-icons text-gray-500 dark:text-gray-400">location_on</span>
                            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Delivery Address</h2>
                        </div>
                        <span v-if="addressAutoFilled"
                            class="inline-flex items-center gap-1 text-xs text-green-600 dark:text-green-400 font-medium">
                            <span class="material-icons text-sm">auto_fix_high</span>
                            Auto-filled from {{ hasWorkOrder ? 'work order' : 'customer' }}
                        </span>
                    </div>

                    <div class="p-6">
                        <AddressAutocomplete
                            :street="form.address_line_1"
                            :unit="form.address_line_2"
                            :city="form.city"
                            :state="form.state"
                            :postal-code="form.postal_code"
                            :country="form.country"
                            :latitude="form.latitude"
                            :longitude="form.longitude"
                            @update="onAddressUpdate"
                        />
                    </div>
                </div>

                <!-- ── Notes ──────────────────────────────────────────── -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
                    <div class="flex items-center gap-2 px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/30">
                        <span class="material-icons text-gray-500 dark:text-gray-400">notes</span>
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Notes</h2>
                    </div>
                    <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Internal Notes</label>
                            <textarea v-model="form.internal_notes" rows="3" placeholder="Visible to staff only…"
                                class="block w-full rounded-lg border border-gray-300 dark:border-gray-600 py-2 px-3 text-sm dark:bg-gray-700 dark:text-white resize-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Customer Notes</label>
                            <textarea v-model="form.customer_notes" rows="3" placeholder="Visible to customer…"
                                class="block w-full rounded-lg border border-gray-300 dark:border-gray-600 py-2 px-3 text-sm dark:bg-gray-700 dark:text-white resize-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                            />
                        </div>
                    </div>
                </div>

                <!-- General error -->
                <div v-if="form.errors.general"
                    class="flex items-center gap-2 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-700 dark:text-red-400">
                    <span class="material-icons text-base flex-shrink-0">error</span>
                    {{ form.errors.general }}
                </div>

                <!-- Submit -->
                <div class="flex justify-end gap-3 pb-8">
                    <Link :href="route('deliveries.index')"
                        class="px-5 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        Cancel
                    </Link>
                    <button type="submit" :disabled="form.processing"
                        class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-60 disabled:cursor-not-allowed transition-colors shadow-sm">
                        <span v-if="form.processing" class="material-icons text-base animate-spin">autorenew</span>
                        <span v-else class="material-icons text-base">save</span>
                        Create Delivery
                    </button>
                </div>

            </form>
            </Transition>

        </div>
    </TenantLayout>
</template>

<style scoped>
.slide-up-enter-active { transition: all 0.2s ease-out; }
.slide-up-leave-active { transition: all 0.15s ease-in; position: absolute; width: 100%; }
.slide-up-enter-from   { opacity: 0; transform: translateY(10px); }
.slide-up-leave-to     { opacity: 0; transform: translateY(-6px); }

.fade-enter-active { transition: opacity 0.2s ease, transform 0.2s ease; }
.fade-leave-active { transition: opacity 0.15s ease; }
.fade-enter-from   { opacity: 0; transform: translateY(4px); }
.fade-leave-to     { opacity: 0; }
</style>