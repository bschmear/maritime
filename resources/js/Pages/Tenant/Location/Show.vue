<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import Sublist from '@/Components/Tenant/Sublist.vue';
import Modal from '@/Components/Modal.vue';
import LayoutBuilder from '@/Components/Tenant/LayoutBuilder.vue';
import LocationLayoutUnitPickerModal from '@/Components/Tenant/LocationLayoutUnitPickerModal.vue';
import { formatPhoneNumber } from '@/Utils/formatPhoneNumber';
import axios from 'axios';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, getCurrentInstance, onUnmounted, ref, watch } from 'vue';

const appInstance = getCurrentInstance();
function toast(type, message) {
    appInstance?.appContext.config.globalProperties.$toast?.(type, message);
}

const props = defineProps({
    record: { type: Object, required: true },
    recordType: { type: String, default: 'locations' },
    recordTitle: { type: String, default: 'Location' },
    domainName: { type: String, default: 'Location' },
    formSchema: { type: Object, default: null },
    fieldsSchema: { type: Object, default: () => ({}) },
    enumOptions: { type: Object, default: () => ({}) },
    locationTypeLabel: { type: String, default: null },
    effectiveDeliveryApprover: { type: Object, default: null },
    pendingDeliveryRequestCount: { type: Number, default: 0 },
    canManageDeliveryApprovers: { type: Boolean, default: false },
    layouts: { type: Array, default: () => [] },
    activeLayoutId: { type: Number, default: null },
    layoutSpace: { type: Object, default: () => ({ width_ft: 60, height_ft: 40 }) },
    layoutUnits: { type: Array, default: () => [] },
    unitStatusOptions: { type: Array, default: () => [] },
    defaultUnitStatusFilter: { type: Array, default: () => [1, 4, 6, 5] },
});

const urlTab = new URLSearchParams(window.location.search).get('tab');
const activeTab = ref(urlTab === 'floor_plans' ? 'floor_plans' : 'overview');

const tabs = [
    { key: 'overview', label: 'Overview', icon: 'info' },
    { key: 'floor_plans', label: 'Floor plans', icon: 'grid_view' },
];

const showDeleteModal = ref(false);
const isDeleting = ref(false);

const locationLabel = computed(
    () => props.record.display_name?.trim() || `Location #${props.record.id}`,
);

const indexHref = computed(() => route(`${props.recordType}.index`));
const editHref = computed(() => route(`${props.recordType}.edit`, props.record.id));

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Locations', href: indexHref.value },
    { label: locationLabel.value },
]);

const isActive = computed(() => !props.record.inactive);

const statusBadgeClass = computed(() =>
    isActive.value
        ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300'
        : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
);

const timezoneLabel = computed(() => {
    const tz = props.record.timezone;
    if (!tz) {
        return null;
    }
    return props.record.timezone_label ?? tz;
});

const addressLines = computed(() => {
    const r = props.record;
    const lines = [];
    if (r.address_line_1) {
        lines.push(r.address_line_1);
    }
    if (r.address_line_2) {
        lines.push(r.address_line_2);
    }
    const cityLine = [r.city, r.state, r.postal_code].filter(Boolean).join(', ');
    if (cityLine) {
        lines.push(cityLine);
    }
    if (r.country) {
        lines.push(r.country);
    }
    return lines;
});

const hasAddress = computed(() => addressLines.value.length > 0 || props.record.full_address);

const mapsUrl = computed(() => {
    if (props.record.latitude && props.record.longitude) {
        return `https://www.google.com/maps?q=${props.record.latitude},${props.record.longitude}`;
    }
    const full = props.record.full_address || addressLines.value.join(', ');
    if (full) {
        return `https://www.google.com/maps?q=${encodeURIComponent(full)}`;
    }
    return null;
});

const formattedPhone = computed(() => {
    const p = props.record.phone;
    if (!p) {
        return null;
    }
    return formatPhoneNumber(p) || p;
});

const managerUser = computed(
    () => props.record.manager_user
        ?? props.record.managerUser
        ?? null,
);

const deliveryApproverUser = computed(
    () => props.record.delivery_approver
        ?? props.record.deliveryApprover
        ?? null,
);

const managerName = computed(() => managerUser.value?.display_name ?? null);

const deliveryApproverName = computed(() => deliveryApproverUser.value?.display_name ?? null);

const userShowHref = (userId) => route('users.show', userId);

const visibleSublists = computed(() => props.formSchema?.sublists ?? []);

const requestsHref = computed(() => route('deliveries.requests.index', {
    location_id: props.record.id,
}));

const fmtDate = (val) => {
    if (!val) {
        return '—';
    }
    const d = new Date(val);
    return Number.isNaN(d.getTime())
        ? '—'
        : d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
};

const confirmDelete = () => {
    isDeleting.value = true;
    router.delete(route(`${props.recordType}.destroy`, props.record.id), {
        onSuccess: () => router.visit(route(`${props.recordType}.index`)),
        onFinish: () => {
            isDeleting.value = false;
            showDeleteModal.value = false;
    },
});
};

// ── Floor plans ───────────────────────────────────────────────────
const LAYOUT_AUTO_SAVE_DELAY_MS = 1500;
const LAYOUT_AUTO_SAVE_KEY = 'layoutBuilderAutoSave';

const layoutAutoSave = ref(
    typeof window !== 'undefined' ? localStorage.getItem(LAYOUT_AUTO_SAVE_KEY) !== '0' : true,
);
const selectedLayoutId = ref(props.activeLayoutId);
const layoutSavePending = ref(false);
const layoutSavedFlash = ref(false);
const unitPickerOpen = ref(false);
const newLayoutName = ref('');
const showNewLayoutForm = ref(false);
let layoutPersistTimer = null;
let layoutSavedFlashTimer = null;

watch(layoutAutoSave, (enabled) => {
    if (typeof window !== 'undefined') {
        localStorage.setItem(LAYOUT_AUTO_SAVE_KEY, enabled ? '1' : '0');
    }
});

const layoutItemsForBuilder = computed(() => props.layoutUnits ?? []);

const layoutAttachConfig = computed(() => ({}));

const layoutSyncUrl = computed(() => {
    if (!selectedLayoutId.value) return null;
    return route('locations.layouts.sync', {
        location: props.record.id,
        layout: selectedLayoutId.value,
    });
});

const printLayoutHref = computed(() => {
    if (!selectedLayoutId.value) {
        return '#';
    }

    return route('locations.layouts.print', {
        location: props.record.id,
        layout: selectedLayoutId.value,
    });
});

const layoutUnitStoreUrl = computed(() => {
    if (!selectedLayoutId.value) return null;
    return route('locations.layouts.units.store', {
        location: props.record.id,
        layout: selectedLayoutId.value,
    });
});

const layoutPickerUrl = computed(() => {
    if (!selectedLayoutId.value) return null;
    return route('locations.layouts.picker-units', {
        location: props.record.id,
        layout: selectedLayoutId.value,
    });
});

function switchLayout(layoutId) {
    selectedLayoutId.value = layoutId;
    router.get(
        route('locations.show', props.record.id),
        { layout: layoutId, tab: 'floor_plans' },
        { preserveState: false, preserveScroll: true },
    );
}

async function createLayout() {
    const name = newLayoutName.value.trim();
    if (!name) return;
    try {
        const response = await axios.post(
            route('locations.layouts.store', props.record.id),
            { name },
            { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } },
        );
        showNewLayoutForm.value = false;
        newLayoutName.value = '';
        const id = response.data.layout?.id;
        if (id) {
            switchLayout(id);
        } else {
            router.reload({ only: ['layouts', 'activeLayoutId', 'layoutSpace', 'layoutUnits'] });
        }
        toast('success', 'Layout created');
    } catch (e) {
        toast('error', e.response?.data?.message ?? 'Could not create layout.');
    }
}

async function deleteLayout(layout) {
    if (!confirm(`Delete floor plan "${layout.name}"?`)) return;
    try {
        await axios.delete(
            route('locations.layouts.destroy', { location: props.record.id, layout: layout.id }),
            { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } },
        );
        toast('success', 'Layout deleted');
        router.reload({ only: ['layouts', 'activeLayoutId', 'layoutSpace', 'layoutUnits'] });
    } catch (e) {
        toast('error', e.response?.data?.message ?? 'Could not delete layout.');
    }
}

async function persistLocationLayout(payload, { showToast = false, reloadAfter = false } = {}) {
    if (!layoutSyncUrl.value || !payload) return;
    layoutSavePending.value = true;
    try {
        await axios.put(layoutSyncUrl.value, payload, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (reloadAfter) {
            await router.reload({ only: ['layoutSpace', 'layoutUnits'] });
        }
        if (showToast) {
            toast('success', 'Floor plan saved');
        } else {
            layoutSavedFlash.value = true;
            clearTimeout(layoutSavedFlashTimer);
            layoutSavedFlashTimer = setTimeout(() => {
                layoutSavedFlash.value = false;
            }, 2000);
        }
    } catch (e) {
        const msg =
            e.response?.data?.message ??
            (e.response?.data?.errors ? Object.values(e.response.data.errors).flat().join(' ') : null) ??
            'Could not save floor plan.';
        toast('error', msg);
    } finally {
        layoutSavePending.value = false;
    }
}

function onLayoutChange(payload) {
    if (!layoutAutoSave.value) {
        return;
    }
    clearTimeout(layoutPersistTimer);
    layoutPersistTimer = setTimeout(
        () => persistLocationLayout(payload, { showToast: false, reloadAfter: false }),
        LAYOUT_AUTO_SAVE_DELAY_MS,
    );
}

async function onLayoutSave(payload) {
    clearTimeout(layoutPersistTimer);
    await persistLocationLayout(payload, { showToast: true, reloadAfter: true });
}

async function onLayoutUnitAttached() {
    await router.reload({ only: ['layoutUnits'] });
    unitPickerOpen.value = false;
    toast('success', 'Unit added to floor plan');
}

onUnmounted(() => {
    clearTimeout(layoutPersistTimer);
    clearTimeout(layoutSavedFlashTimer);
});
</script>

<template>
    <Head :title="`${locationLabel} — Location`" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="flex min-w-0 items-center gap-3">
                        <h2 class="truncate text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                            {{ locationLabel }}
                        </h2>
                        <span
                            :class="[
                                'hidden sm:inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold',
                                statusBadgeClass,
                            ]"
                        >
                            <span
                                :class="[
                                    'h-1.5 w-1.5 rounded-full',
                                    isActive ? 'bg-green-500' : 'bg-gray-400',
                                ]"
                            />
                            {{ isActive ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <Link
                            :href="indexHref"
                            class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-gray-600 transition-colors hover:text-gray-900 dark:text-gray-300 dark:hover:text-white"
                        >
                            <span class="material-icons text-[16px]">arrow_back</span>
                            Locations
                        </Link>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-red-600 transition-colors hover:text-red-700 dark:text-red-400"
                            @click="showDeleteModal = true"
                        >
                            <span class="material-icons text-[16px]">delete</span>
                            Delete
                        </button>
                        <Link
                            :href="editHref"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-primary-700"
                        >
                            <span class="material-icons text-[16px]">edit</span>
                            Edit
                        </Link>
                    </div>
                </div>
            </div>
        </template>

        <div class="w-full space-y-4 p-4">
            <!-- Tab bar -->
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-center gap-1 overflow-x-auto border-b border-gray-100 px-2 dark:border-gray-700">
                    <button
                        v-for="tab in tabs"
                        :key="tab.key"
                        type="button"
                        class="flex items-center gap-1.5 whitespace-nowrap border-b-2 px-4 py-3.5 text-sm font-medium transition-colors"
                        :class="activeTab === tab.key
                            ? 'border-primary-600 text-primary-600 dark:border-primary-400 dark:text-primary-400'
                            : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'"
                        @click="activeTab = tab.key"
                    >
                        <span class="material-icons text-[17px]">{{ tab.icon }}</span>
                        {{ tab.label }}
                    </button>
                </div>
            </div>

            <!-- Floor plans tab -->
            <div v-show="activeTab === 'floor_plans'" class="relative space-y-3">
                <div class="flex flex-wrap items-center gap-2 rounded-xl border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <span class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Layout</span>
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            v-for="layout in layouts"
                            :key="layout.id"
                            type="button"
                            class="rounded-lg border px-3 py-1.5 text-sm font-medium transition-colors"
                            :class="selectedLayoutId === layout.id
                                ? 'border-primary-500 bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300'
                                : 'border-gray-200 text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200'"
                            @click="switchLayout(layout.id)"
                        >
                            {{ layout.name || `Layout #${layout.id}` }}
                        </button>
                    </div>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1 rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200"
                        @click="showNewLayoutForm = !showNewLayoutForm"
                    >
                        <span class="material-icons text-[14px]">add</span>
                        New layout
                    </button>
                    <button
                        v-if="layouts.length > 1 && selectedLayoutId"
                        type="button"
                        class="inline-flex items-center gap-1 rounded-lg border border-red-200 px-2.5 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50 dark:border-red-800 dark:text-red-400"
                        @click="deleteLayout(layouts.find((l) => l.id === selectedLayoutId))"
                    >
                        <span class="material-icons text-[14px]">delete_outline</span>
                        Delete layout
                    </button>
                    <a
                        v-if="selectedLayoutId"
                        :href="printLayoutHref"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="ml-auto inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                    >
                        <span class="material-icons text-base leading-none">print</span>
                        Print layout
                    </a>
                </div>

                <div
                    v-if="showNewLayoutForm"
                    class="flex flex-wrap items-end gap-2 rounded-xl border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                >
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-500">Layout name</label>
                        <input
                            v-model="newLayoutName"
                            type="text"
                            placeholder="e.g. Main warehouse"
                            class="rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            @keydown.enter="createLayout"
                        />
                    </div>
                    <button
                        type="button"
                        class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700"
                        @click="createLayout"
                    >
                        Create
                    </button>
                </div>

                <LayoutBuilder
                    v-if="selectedLayoutId"
                    v-model:auto-save="layoutAutoSave"
                    item-link-field="placement_id"
                    :initial-layout-items="layoutItemsForBuilder"
                    :layout-space="layoutSpace"
                    :attach-asset-config="layoutAttachConfig"
                    :unit-store-url="layoutUnitStoreUrl"
                    @request-attach-asset="unitPickerOpen = true"
                    @save="onLayoutSave"
                    @change="onLayoutChange"
                />

                <div
                    v-if="layoutSavePending"
                    class="pointer-events-none absolute top-3 right-4 z-20 flex items-center gap-2 rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 shadow-md dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200"
                    role="status"
                >
                    <span class="h-3.5 w-3.5 shrink-0 animate-spin rounded-full border-2 border-primary-600 border-t-transparent" />
                    Saving floor plan…
                </div>
                <div
                    v-else-if="layoutSavedFlash"
                    class="pointer-events-none absolute top-3 right-4 z-20 flex items-center gap-2 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-medium text-emerald-800 shadow-md dark:border-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200"
                    role="status"
                >
                    <span class="material-icons text-[14px]">check_circle</span>
                    Floor plan saved
                </div>

                <LocationLayoutUnitPickerModal
                    v-if="layoutPickerUrl && layoutUnitStoreUrl"
                    v-model="unitPickerOpen"
                    :picker-url="layoutPickerUrl"
                    :store-url="layoutUnitStoreUrl"
                    :location-name="locationLabel"
                    :unit-status-options="unitStatusOptions"
                    :default-status-filter="defaultUnitStatusFilter"
                    @attached="onLayoutUnitAttached"
                />
            </div>

            <div v-show="activeTab === 'overview'">
            <div
                class="relative overflow-hidden rounded-xl bg-gradient-to-br from-primary-600 via-primary-700 to-primary-900 shadow-lg dark:from-primary-700 dark:via-primary-800 dark:to-primary-950 mb-4 lg:mb-6"
            >
                <div class="absolute inset-0 opacity-10">
                    <svg viewBox="0 0 1200 300" preserveAspectRatio="none" class="absolute bottom-0 h-full w-full">
                        <path
                            d="M0,200 C200,100 400,250 600,180 C800,110 1000,220 1200,160 L1200,300 L0,300 Z"
                            fill="white"
                        />
                        <path
                            d="M0,240 C300,170 500,270 700,210 C900,150 1100,240 1200,200 L1200,300 L0,300 Z"
                            fill="white"
                            opacity="0.5"
                        />
                    </svg>
                </div>

                <div class="pointer-events-none absolute right-8 top-1/2 -translate-y-1/2 select-none opacity-[0.1]">
                    <span class="material-icons text-[180px] leading-none text-white">location_on</span>
                </div>

                <div class="relative px-6 py-8 sm:px-10 sm:py-10">
                    <div class="flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
                        <div class="space-y-3">
                            <div class="min-w-0 space-y-1">
                                <h1 class="text-2xl font-bold leading-tight text-white sm:text-3xl">
                                    {{ locationLabel }}
                                </h1>
                                <p v-if="locationTypeLabel" class="text-sm font-medium text-primary-100">
                                    {{ locationTypeLabel }}
                                </p>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full bg-white/20 px-2.5 py-0.5 text-xs font-semibold text-white sm:hidden"
                                >
                                    <span
                                        :class="[
                                            'h-1.5 w-1.5 rounded-full',
                                            isActive ? 'bg-green-300' : 'bg-gray-300',
                                        ]"
                                    />
                                    {{ isActive ? 'Active' : 'Inactive' }}
                                </span>
                                <div
                                    v-if="timezoneLabel"
                                    class="flex items-center gap-2 rounded-lg bg-white/15 px-3 py-2 backdrop-blur-sm"
                                >
                                    <span class="material-icons text-[16px] text-white">schedule</span>
                                    <span class="text-sm font-medium text-white">{{ timezoneLabel }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <a
                                v-if="record.email"
                                :href="`mailto:${record.email}`"
                                class="flex items-center gap-2 rounded-lg bg-white/15 px-3 py-2 backdrop-blur-sm transition-colors hover:bg-white/20"
                            >
                                <span class="material-icons text-[16px] text-white">mail</span>
                                <span class="max-w-[14rem] truncate text-sm font-medium text-white sm:max-w-xs">
                                    {{ record.email }}
                                </span>
                            </a>
                            <a
                                v-if="formattedPhone"
                                :href="`tel:${record.phone}`"
                                class="flex items-center gap-2 rounded-lg bg-white/15 px-3 py-2 backdrop-blur-sm transition-colors hover:bg-white/20"
                            >
                                <span class="material-icons text-[16px] text-white">phone</span>
                                <span class="text-sm font-medium text-white">{{ formattedPhone }}</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-12">
                <div class="space-y-6 lg:col-span-8">
                    <section
                        class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
                    >
                        <header
                            class="flex items-center gap-2 border-b border-gray-200 bg-gray-50 px-5 py-3 dark:border-gray-700 dark:bg-gray-900/30"
                        >
                            <span class="material-icons text-base text-gray-500 dark:text-gray-400">place</span>
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Address</h3>
                        </header>
                        <div class="p-5">
                            <div v-if="hasAddress" class="flex items-start gap-3">
                                <span class="material-icons mt-0.5 shrink-0 text-[20px] text-gray-400">location_on</span>
                                <div class="space-y-0.5 text-sm leading-relaxed text-gray-800 dark:text-gray-200">
                                    <template v-if="addressLines.length">
                                        <div v-for="line in addressLines" :key="line">{{ line }}</div>
                                    </template>
                                    <div v-else-if="record.full_address">{{ record.full_address }}</div>
                                </div>
                            </div>
                            <p v-else class="text-sm text-gray-500 dark:text-gray-400">No address on file.</p>
                            <a
                                v-if="mapsUrl"
                                :href="mapsUrl"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="mt-4 inline-flex items-center gap-2 text-sm font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400"
                            >
                                <span class="material-icons text-[16px]">map</span>
                                Open in Google Maps
                            </a>
                        </div>
                    </section>

                    <section
                        class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
                    >
                        <header
                            class="flex items-center gap-2 border-b border-gray-200 bg-gray-50 px-5 py-3 dark:border-gray-700 dark:bg-gray-900/30"
                        >
                            <span class="material-icons text-base text-gray-500 dark:text-gray-400">groups</span>
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Management</h3>
                        </header>
                        <dl class="divide-y divide-gray-100 p-5 dark:divide-gray-700/60">
                            <div class="flex justify-between gap-3 py-2 text-sm">
                                <dt class="text-gray-500 dark:text-gray-400">Manager</dt>
                                <dd class="text-gray-900 dark:text-white">
                                    <Link
                                        v-if="managerUser"
                                        :href="userShowHref(managerUser.id)"
                                        class="font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300"
                                    >
                                        {{ managerName }}
                                    </Link>
                                    <span v-else>—</span>
                                </dd>
                            </div>
                            <div class="flex justify-between gap-3 py-2 text-sm">
                                <dt class="text-gray-500 dark:text-gray-400">Delivery approver</dt>
                                <dd class="text-gray-900 dark:text-white">
                                    <Link
                                        v-if="deliveryApproverUser"
                                        :href="userShowHref(deliveryApproverUser.id)"
                                        class="font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300"
                                    >
                                        {{ deliveryApproverName }}
                                    </Link>
                                    <span v-else>—</span>
                                </dd>
                            </div>
                        </dl>
                    </section>

                    <section
                        v-if="record.notes"
                        class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
                    >
                        <header
                            class="flex items-center gap-2 border-b border-gray-200 bg-gray-50 px-5 py-3 dark:border-gray-700 dark:bg-gray-900/30"
                        >
                            <span class="material-icons text-base text-gray-500 dark:text-gray-400">notes</span>
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Notes</h3>
                        </header>
                        <div class="p-5">
                            <p class="whitespace-pre-line text-sm leading-relaxed text-gray-700 dark:text-gray-300">
                                {{ record.notes }}
                            </p>
                        </div>
                    </section>

                    <Sublist
                        v-if="visibleSublists.length > 0 && domainName"
                        :parent-record="record"
                        :parent-domain="domainName"
                        :sublists="visibleSublists"
                    />
                </div>

                <div class="space-y-6 lg:col-span-4">
                    <section
                        class="overflow-hidden rounded-xl border border-amber-200 bg-amber-50 shadow-sm dark:border-amber-800/50 dark:bg-amber-950/30"
                    >
                        <header class="border-b border-amber-200 bg-amber-100/60 px-5 py-3 dark:border-amber-800/50 dark:bg-amber-900/20">
                            <h3 class="text-sm font-semibold text-amber-950 dark:text-amber-100">Delivery settings</h3>
                        </header>
                        <dl class="divide-y divide-amber-200/80 p-5 dark:divide-amber-800/50">
                            <div class="py-2 text-sm">
                                <dt class="font-medium text-amber-900 dark:text-amber-200">Effective approver</dt>
                                <dd class="mt-1 text-amber-950 dark:text-amber-100">
                                    <template v-if="effectiveDeliveryApprover">
                                        <Link
                                            :href="userShowHref(effectiveDeliveryApprover.id)"
                                            class="font-medium text-primary-700 hover:underline dark:text-primary-300"
                                        >
                                            {{ effectiveDeliveryApprover.display_name }}
                                        </Link>
                                        <span
                                            v-if="effectiveDeliveryApprover.uses_manager_fallback"
                                            class="text-xs text-amber-700 dark:text-amber-300"
                                        >
                                            (uses manager)
                                        </span>
                                    </template>
                                    <span v-else class="text-red-700 dark:text-red-300">
                                        Not configured — delivery requests cannot be submitted from this location.
                                    </span>
                                </dd>
                            </div>
                            <div class="flex justify-between gap-3 py-2 text-sm">
                                <dt class="font-medium text-amber-900 dark:text-amber-200">Pending requests</dt>
                                <dd>
                                    <Link
                                        v-if="pendingDeliveryRequestCount > 0"
                                        :href="requestsHref"
                                        class="font-semibold text-primary-700 hover:underline dark:text-primary-300"
                                    >
                                        {{ pendingDeliveryRequestCount }} pending
                                    </Link>
                                    <span v-else class="text-amber-800/70 dark:text-amber-200/70">None</span>
                                </dd>
                            </div>
                        </dl>
                        <div v-if="canManageDeliveryApprovers" class="border-t border-amber-200 px-5 py-3 dark:border-amber-800/50">
                            <Link
                                :href="route('account.delivery-management.index')"
                                class="inline-flex items-center gap-1 text-sm font-medium text-primary-700 hover:underline dark:text-primary-300"
                            >
                                Manage all location approvers
                                <span class="material-icons text-base">arrow_forward</span>
                            </Link>
                        </div>
                    </section>

                    <section
                        class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
                    >
                        <header class="border-b border-gray-200 bg-gray-50 px-5 py-3 dark:border-gray-700 dark:bg-gray-900/30">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Record details</h3>
                        </header>
                        <dl class="divide-y divide-gray-100 p-5 dark:divide-gray-700/60">
                            <div class="flex justify-between gap-3 py-2 text-sm">
                                <dt class="text-gray-500 dark:text-gray-400">Created</dt>
                                <dd class="text-gray-900 dark:text-white">{{ fmtDate(record.created_at) }}</dd>
                            </div>
                            <div class="flex justify-between gap-3 py-2 text-sm">
                                <dt class="text-gray-500 dark:text-gray-400">Updated</dt>
                                <dd class="text-gray-900 dark:text-white">{{ fmtDate(record.updated_at) }}</dd>
                            </div>
                            <div
                                v-if="record.latitude && record.longitude"
                                class="flex justify-between gap-3 py-2 text-sm"
                            >
                                <dt class="text-gray-500 dark:text-gray-400">Coordinates</dt>
                                <dd class="font-mono text-xs text-gray-900 dark:text-white">
                                    {{ Number(record.latitude).toFixed(4) }},
                                    {{ Number(record.longitude).toFixed(4) }}
                                </dd>
                            </div>
                        </dl>
                    </section>
                </div>
            </div>
            </div>
        </div>

        <Modal :show="showDeleteModal" max-width="md" @close="showDeleteModal = false">
            <div class="p-6 text-center">
                <div
                    class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30"
                >
                    <span class="material-icons text-2xl text-red-600 dark:text-red-400">delete</span>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Delete location</h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Delete
                    <span class="font-medium text-gray-700 dark:text-gray-300">{{ locationLabel }}</span>?
                    This cannot be undone.
                </p>
                <div class="mt-6 flex justify-center gap-3">
                    <button
                        type="button"
                        :disabled="isDeleting"
                        class="inline-flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-red-700 disabled:opacity-50"
                        @click="confirmDelete"
                    >
                        <span v-if="isDeleting" class="material-icons animate-spin text-[16px]">sync</span>
                        {{ isDeleting ? 'Deleting…' : 'Delete' }}
                    </button>
                    <button
                        type="button"
                        :disabled="isDeleting"
                        class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300"
                        @click="showDeleteModal = false"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </Modal>
    </TenantLayout>
</template>
