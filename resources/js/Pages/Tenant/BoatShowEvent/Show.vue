<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import Checklist from '@/Components/Tenant/Checklist.vue';
import BoatShowEventAssetPickerModal from '@/Components/Tenant/BoatShowEventAssetPickerModal.vue';
import LayoutBuilder from '@/Components/Tenant/LayoutBuilder.vue';
import RelatableTasksBoard from '@/Components/Tenant/RelatableTasksBoard.vue';
import axios from 'axios';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, getCurrentInstance, onUnmounted, ref, watch } from 'vue';
import { formatCalendarDateShort, parseCalendarYmdToLocalDate, startOfLocalToday } from '@/Utils/calendarDate.js';

const appInstance = getCurrentInstance();
function toast(type, message) {
    appInstance?.appContext.config.globalProperties.$toast?.(type, message);
}

const props = defineProps({
    record: { type: Object, required: true },
    recordType: { type: String, required: true },
    recordTitle: { type: String, default: 'Boat Show Event' },
    formSchema: { type: Object, default: null },
    fieldsSchema: { type: Object, default: () => ({}) },
    enumOptions: { type: Object, default: () => ({}) },
    domainName: { type: String, default: 'BoatShowEvent' },
    imageUrls: { type: Object, default: () => ({}) },
    account: { type: Object, default: null },
    timezones: { type: Array, default: () => [] },
    extraRouteParams: { type: Object, default: () => ({}) },
    checklist: {
        type: Object,
        default: () => ({
            id: null,
            name: 'Event Checklist',
            checklist_template_id: null,
            items: [],
        }),
    },
    checklistTemplates: { type: Array, default: () => [] },
    tasks: { type: Array, default: () => [] },
    /** Captured boat-show leads for this event (from boat_show_leads + linked CRM lead). */
    eventLeads: { type: Array, default: () => [] },
    taskStatusOptions: { type: Array, default: () => [] },
    taskBoardFormSchema: { type: Object, default: null },
    taskBoardFieldsSchema: { type: Object, default: () => ({}) },
    taskBoardEnumOptions: { type: Object, default: () => ({}) },
    assets: {
        type: Object,
        default: () => ({ boats: [], engines: [], trailers: [] }),
    },
    layoutSpace: {
        type: Object,
        default: () => ({ width_ft: 60, height_ft: 40 }),
    },
    printUrl: { type: String, default: '' },
    followUpSettings: {
        type: Object,
        default: () => ({
            auto_followup: true,
            delay_amount: 1,
            delay_unit: 'days',
            recipient_user_count: 0,
        }),
    },
});

const isNested = computed(() => Object.keys(props.extraRouteParams).length > 0);

const parentShow = computed(() => props.record.boat_show ?? props.record.show ?? null);

const parentShowLabel = computed(() => {
    const p = parentShow.value;
    if (!p) return '';
    return p.display_name ?? p.name ?? 'Boat show';
});

const parentShowHref = computed(() => {
    const p = parentShow.value;
    if (!p) return null;
    return route('boat-shows.show', p.slug ?? p.id);
});

const indexHref = computed(() =>
    isNested.value
        ? route('boat-shows.events.index', props.extraRouteParams)
        : route('boat-show-events.index')
);

const editHref = computed(() =>
    isNested.value
        ? route('boat-shows.events.edit', { ...props.extraRouteParams, event: props.record.id })
        : route('boat-show-events.edit', props.record.id)
);

const duplicateHref = computed(() => {
    const params = { ...props.extraRouteParams, duplicate: props.record.id };

    return isNested.value
        ? route('boat-shows.events.create', params)
        : route('boat-show-events.create', { duplicate: props.record.id });
});

const printLayoutHref = computed(() => {
    if (props.printUrl) {
        return props.printUrl;
    }
    return isNested.value
        ? route('boat-shows.events.layout.print', { ...props.extraRouteParams, event: props.record.id })
        : route('boat-show-events.layout.print', props.record.id);
});

const breadcrumbItems = computed(() => {
    const items = [{ label: 'Home', href: route('dashboard') }];

    items.push({ label: 'Boat Shows', href: route('boat-shows.index') });

    if (isNested.value && parentShow.value) {
        items.push({ label: parentShowLabel.value, href: parentShowHref.value });
    } else {
        items.push({ label: 'Boat Show Events', href: indexHref.value });
    }

    items.push({ label: props.record.display_name ?? `Event #${props.record.id}` });
    return items;
});

// ── Tabs ────────────────────────────────────────────────────────
const tabs = [
    { key: 'details',     label: 'Details',         icon: 'info' },
    { key: 'layout',      label: 'Layout Builder',  icon: 'dashboard_customize' },
    { key: 'checklist',   label: 'Checklist',        icon: 'checklist' },
    { key: 'tasks',       label: 'Tasks',            icon: 'task_alt' },
    { key: 'event_leads', label: 'Event leads',     icon: 'contact_mail' },
    { key: 'assets',      label: 'Asset List',       icon: 'directions_boat' },
];

const totalAssets = computed(() =>
    (props.assets.boats?.length ?? 0) +
    (props.assets.engines?.length ?? 0) +
    (props.assets.trailers?.length ?? 0)
);

const activeTab = ref('details');

// ── Date helpers ─────────────────────────────────────────────────────────────
const formatDate = (val) => {
    if (!val) return null;
    return formatCalendarDateShort(val, { year: 'numeric' }) || null;
};

const formatDateShort = (val) => {
    const d = parseCalendarYmdToLocalDate(val);
    if (!d) return null;
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
};

const formatDateTime = (val) => {
    if (!val) return '—';
    const d = new Date(val);
    if (Number.isNaN(d.getTime())) return '—';
    return d.toLocaleString('en-US', { dateStyle: 'medium', timeStyle: 'short' });
};

const dateRange = computed(() => {
    const s = props.record.starts_at;
    const e = props.record.ends_at;
    if (s && e) return `${formatDateShort(s)} – ${formatDate(e)}`;
    if (s) return `From ${formatDate(s)}`;
    if (e) return `Until ${formatDate(e)}`;
    return null;
});

const isUpcoming = computed(() => {
    const start = parseCalendarYmdToLocalDate(props.record.starts_at);
    if (!start) return false;
    return start > startOfLocalToday();
});

const isActive = computed(() => {
    const today = startOfLocalToday();
    const s = parseCalendarYmdToLocalDate(props.record.starts_at);
    const e = parseCalendarYmdToLocalDate(props.record.ends_at);
    if (s && e) return today >= s && today <= e;
    return false;
});

const isPast = computed(() => {
    const end = parseCalendarYmdToLocalDate(props.record.ends_at);
    if (!end) return false;
    return end < startOfLocalToday();
});

const eventHasStarted = computed(() => {
    const start = parseCalendarYmdToLocalDate(props.record.starts_at);
    if (!start) return false;
    return start <= startOfLocalToday();
});

const boatShowEventRelatableType = 'App\\Domain\\BoatShowEvent\\Models\\BoatShowEvent';

const eventStatus = computed(() => {
    if (!props.record.active) return { label: 'Inactive', color: 'text-gray-500 dark:text-gray-400', dot: 'bg-gray-400', badge: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' };
    if (isActive.value) return { label: 'Live Now', color: 'text-green-600 dark:text-green-400', dot: 'bg-green-500 animate-pulse', badge: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' };
    if (isUpcoming.value) return { label: 'Upcoming', color: 'text-blue-600 dark:text-blue-400', dot: 'bg-blue-500', badge: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300' };
    if (isPast.value) return { label: 'Past', color: 'text-gray-500 dark:text-gray-400', dot: 'bg-gray-400', badge: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' };
    return { label: 'Active', color: 'text-green-600 dark:text-green-400', dot: 'bg-green-500', badge: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' };
});

const hasAddress = computed(() =>
    props.record.address_line_1 || props.record.city || props.record.state
);

const fullAddress = computed(() => {
    const parts = [
        props.record.address_line_1,
        props.record.address_line_2,
        [props.record.city, props.record.state, props.record.postal_code].filter(Boolean).join(', '),
        props.record.country,
    ].filter(Boolean);
    return parts.join('\n');
});

const mapsUrl = computed(() => {
    if (props.record.latitude && props.record.longitude) {
        return `https://www.google.com/maps?q=${props.record.latitude},${props.record.longitude}`;
    }
    if (hasAddress.value) {
        const q = encodeURIComponent(fullAddress.value.replace(/\n/g, ', '));
        return `https://www.google.com/maps?q=${q}`;
    }
    return null;
});

/** Guest-facing inventory + QR / lead link (only active events are reachable). */
const publicShowcaseHref = computed(() => {
    if (!props.record.uuid || !props.record.active) {
        return null;
    }
    return route('boat-show-events.public.showcase', { uuid: props.record.uuid });
});

const confirmDelete = () => {
    if (!confirm(`Delete "${props.record.display_name}"? This cannot be undone.`)) return;
    const url = isNested.value
        ? route('boat-shows.events.destroy', { ...props.extraRouteParams, event: props.record.id })
        : route('boat-show-events.destroy', props.record.id);
    router.delete(url, { preserveScroll: true });
};

// ── Checklist local state ────────────────────────────────────────
const cloneChecklist = (c) => JSON.parse(JSON.stringify(c));
const checklistData = ref(cloneChecklist(props.checklist));
const savingChecklist = ref(false);
const checklistSaveError = ref(null);

watch(
    () => props.checklist,
    (c) => {
        checklistData.value = cloneChecklist(c);
    },
    { deep: true }
);

function checklistUpdateUrl() {
    return isNested.value
        ? route('boat-shows.events.checklist.update', { ...props.extraRouteParams, event: props.record.id })
        : route('boat-show-events.checklist.update', props.record.id);
}

async function handleSaveChecklistItem({ item, resolve }) {
    checklistSaveError.value = null
    try {
        const { data } = await axios.put(checklistUpdateUrl(), {
            name: checklistData.value.name,
            checklist_template_id: checklistData.value.checklist_template_id ?? null,
            items: checklistData.value.items.map((i) => ({
                id: i.id ?? null,
                label: i.label,
                completed: !!i.completed,
                required: !!i.required,
            })),
        })
        if (data.success && data.checklist) {
            checklistData.value = data.checklist
        }
    } catch (e) {
        const msg =
            e.response?.data?.message ??
            (e.response?.data?.errors ? Object.values(e.response.data.errors).flat().join(' ') : null) ??
            'Failed to save checklist.'
        checklistSaveError.value = msg
    } finally {
        resolve()  // always resolve so the Checklist component clears its saving state
    }
}

async function handleSaveTemplate(payload) {
    const resolve = typeof payload.resolve === 'function' ? payload.resolve : () => {};
    let success = false;

    try {
        const items = payload.items
            .map((i) => ({
                label: String(i.label ?? '').trim(),
                required: !!i.required,
            }))
            .filter((i) => i.label.length > 0);

        if (!items.length) {
            checklistSaveError.value =
                'Add at least one item with a label before saving as a template.';
            return;
        }

        checklistSaveError.value = null;
        const name = payload.name?.trim() || 'Untitled template';

        await axios.post(route('checklist-templates.store'), {
            name,
            context: 'boat_show_event',
            items,
        });
        await router.reload({ only: ['checklistTemplates'] });
        toast('success', `Template "${name}" saved`);
        success = true;
    } catch (e) {
        const msg =
            e.response?.data?.message ??
            (e.response?.data?.errors ? Object.values(e.response.data.errors).flat().join(' ') : null) ??
            'Failed to save template.';
        checklistSaveError.value = msg;
        toast('error', msg);
    } finally {
        resolve(success);
    }
}

// ── Layout (persisted on boat_show_event_assets + boat_show_layouts) ──
const LAYOUT_AUTO_SAVE_DELAY_MS = 1500;
const LAYOUT_AUTO_SAVE_KEY = 'layoutBuilderAutoSave';

const layoutAutoSave = ref(
    typeof window !== 'undefined' ? localStorage.getItem(LAYOUT_AUTO_SAVE_KEY) !== '0' : true,
);
const layoutSavePending = ref(false);
const layoutSavedFlash = ref(false);
let layoutPersistTimer = null;
let layoutSavedFlashTimer = null;

watch(layoutAutoSave, (enabled) => {
    if (typeof window !== 'undefined') {
        localStorage.setItem(LAYOUT_AUTO_SAVE_KEY, enabled ? '1' : '0');
    }
});

const eventLayoutSyncUrl = computed(() =>
    isNested.value
        ? route('boat-shows.events.layout.sync', { ...props.extraRouteParams, event: props.record.id })
        : route('boat-show-events.layout.sync', { event: props.record.id }),
);

async function persistEventLayout(payload, { showToast = false, reloadAfter = false } = {}) {
    if (!payload || typeof payload !== 'object') {
        return;
    }
    layoutSavePending.value = true;
    try {
        await axios.put(eventLayoutSyncUrl.value, payload, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        if (reloadAfter) {
            await router.reload({ only: ['assets', 'layoutSpace'] });
        }
        if (showToast) {
            toast('success', 'Layout saved');
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
            'Could not save layout.';
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
        () => persistEventLayout(payload, { showToast: false, reloadAfter: false }),
        LAYOUT_AUTO_SAVE_DELAY_MS,
    );
}

async function onLayoutSave(payload) {
    clearTimeout(layoutPersistTimer);
    await persistEventLayout(payload, { showToast: true, reloadAfter: true });
}

onUnmounted(() => {
    clearTimeout(layoutPersistTimer);
    clearTimeout(layoutSavedFlashTimer);
});

// ── Event assets (picker + remove) ───────────────────────────────
const assetPickerOpen = ref(false);

const eventAssetStoreUrl = computed(() =>
    isNested.value
        ? route('boat-shows.events.assets.store', { ...props.extraRouteParams, event: props.record.id })
        : route('boat-show-events.assets.store', { event: props.record.id }),
);

const eventAssetUnitsUrl = computed(() =>
    isNested.value
        ? route('boat-shows.events.assets.units', { ...props.extraRouteParams, event: props.record.id })
        : route('boat-show-events.assets.units', { event: props.record.id }),
);

/** Truthy prop for LayoutBuilder: use inventory picker instead of only the custom-size modal. */
const eventAssetAttachConfig = computed(() => ({}));

/** Boats, engines, and trailers share one floor plan (colors fixed by asset type). */
const layoutItemsForBuilder = computed(() => [
    ...(props.assets.boats ?? []),
    ...(props.assets.engines ?? []),
    ...(props.assets.trailers ?? []),
]);

function formatBoatLengthFt(boat) {
    const v = boat.length_display ?? boat.length;
    if (v === null || v === undefined || v === '') {
        return '—';
    }
    return `${v}'`;
}

function formatAssetUnitLabel(row) {
    return row.unit_label ?? row.asset_unit?.unit_label ?? row.asset_unit?.display_name ?? '—';
}

function assetUnitShowHref(row) {
    const unitId = row.asset_unit?.id ?? row.asset_unit_id ?? null;
    if (!unitId) {
        return null;
    }
    return route('assetunits.show', unitId);
}

async function onEventAssetAttached() {
    await router.reload({ only: ['assets'] });
    assetPickerOpen.value = false;
    toast('success', 'Asset added');
}

async function removeEventAsset(row) {
    if (!row.event_asset_id) {
        return;
    }
    if (!confirm('Remove this asset from the event?')) {
        return;
    }
    try {
        const url = isNested.value
            ? route('boat-shows.events.assets.destroy', {
                  ...props.extraRouteParams,
                  event: props.record.id,
                  eventAsset: row.event_asset_id,
              })
            : route('boat-show-events.assets.destroy', { event: props.record.id, eventAsset: row.event_asset_id });
        await axios.delete(url);
        await router.reload({ only: ['assets'] });
        toast('success', 'Asset removed');
    } catch (e) {
        const msg =
            e.response?.data?.message ??
            (e.response?.data?.errors ? Object.values(e.response.data.errors).flat().join(' ') : null) ??
            'Failed to remove asset.';
        toast('error', msg);
    }
}
</script>

<template>
    <Head :title="record.display_name ?? 'Boat Show Event'" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex min-w-0 flex-1 flex-wrap items-center gap-2 md:gap-3">
                        <h2 class="truncate text-lg font-semibold leading-tight text-gray-800 md:text-xl dark:text-gray-200">
                            {{ record.display_name ?? `Event #${record.id}` }}
                        </h2>
                        <span :class="['inline-flex shrink-0 items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-semibold md:px-2.5 md:py-1 md:text-sm', eventStatus.badge]">
                            <span :class="['w-1.5 h-1.5 rounded-full', eventStatus.dot]"></span>
                            {{ eventStatus.label }}
                        </span>
                    </div>
                    <div class="flex shrink-0 flex-wrap items-center justify-end gap-1 md:gap-2">
                        <a
                            v-if="publicShowcaseHref"
                            :href="publicShowcaseHref"
                            target="_blank"
                            rel="noopener noreferrer"
                            aria-label="Open public event page"
                            title="Open public event page"
                            class="inline-flex items-center justify-center gap-0 rounded-lg border border-primary-200 bg-primary-50 p-2 text-sm font-medium text-primary-700 transition-colors hover:bg-primary-100 dark:border-primary-800 dark:bg-primary-900/30 dark:text-primary-300 dark:hover:bg-primary-900/50 md:gap-1.5 md:px-4 md:py-2"
                        >
                            <span class="material-icons text-xl leading-none md:text-base">open_in_new</span>
                            <span class="hidden md:inline">Public event page</span>
                        </a>
                        <Link
                            :href="route('boat-show-email-templates.index')"
                            aria-label="Follow-up email template"
                            title="Follow-up email template"
                            class="inline-flex items-center justify-center gap-0 rounded-lg border border-gray-200 bg-white p-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700 md:gap-1.5 md:px-4 md:py-2"
                        >
                            <span class="material-icons text-xl leading-none md:text-base">mail_outline</span>
                            <span class="hidden md:inline">Follow-up template</span>
                        </Link>
                        <Link
                            :href="duplicateHref"
                            aria-label="Duplicate event"
                            title="Duplicate event"
                            class="inline-flex items-center justify-center gap-0 rounded-lg border border-gray-200 bg-white p-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700 md:gap-1.5 md:px-4 md:py-2"
                        >
                            <span class="material-icons text-xl leading-none md:text-base">content_copy</span>
                            <span class="hidden md:inline">Duplicate</span>
                        </Link>
                        <Link
                            :href="editHref"
                            aria-label="Edit event"
                            class="inline-flex items-center justify-center gap-0 rounded-lg bg-primary-600 p-2 text-sm font-medium text-white transition-colors hover:bg-primary-700 md:gap-1.5 md:px-4 md:py-2"
                        >
                            <span class="material-icons text-xl leading-none md:text-base">edit</span>
                            <span class="hidden md:inline">Edit</span>
                        </Link>
                        <button
                            type="button"
                            aria-label="Delete event"
                            class="inline-flex items-center justify-center gap-0 rounded-lg border border-red-200 bg-white p-2 text-sm font-medium text-red-600 transition-colors hover:bg-red-50 dark:border-red-800 dark:bg-gray-800 dark:text-red-400 dark:hover:bg-red-900/20 md:gap-1.5 md:px-4 md:py-2"
                            @click="confirmDelete"
                        >
                            <span class="material-icons text-xl leading-none md:text-base">delete_outline</span>
                            <span class="hidden md:inline">Delete</span>
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <div class="w-full p-4 space-y-4">

            <!-- ================================================================
                 HERO BANNER
                 ================================================================ -->
            <div class="relative overflow-hidden rounded-xl bg-gradient-to-br from-primary-600 via-primary-700 to-primary-900 dark:from-primary-700 dark:via-primary-800 dark:to-primary-950 shadow-lg">

                <!-- Decorative wave pattern -->
                <div class="absolute inset-0 opacity-10">
                    <svg viewBox="0 0 1200 300" preserveAspectRatio="none" class="absolute bottom-0 w-full h-full">
                        <path d="M0,200 C200,100 400,250 600,180 C800,110 1000,220 1200,160 L1200,300 L0,300 Z" fill="white"/>
                        <path d="M0,240 C300,170 500,270 700,210 C900,150 1100,240 1200,200 L1200,300 L0,300 Z" fill="white" opacity="0.5"/>
                    </svg>
                </div>

                <!-- Anchor watermark -->
                <div class="absolute right-8 top-1/2 -translate-y-1/2 opacity-[0.1] select-none pointer-events-none">
                    <span class="material-icons" style="font-size: 180px;">anchor</span>
                </div>

                <div class="relative px-6 py-8 sm:px-10 sm:py-10">
                    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-6">

                        <!-- Left: title block -->
                        <div class="space-y-3">
                            <!-- Boat show parent link -->
                            <div v-if="parentShow" class="flex items-center gap-2">
                                <span class="material-icons text-[14px] text-white">sailing</span>
                                <Link
                                    :href="parentShowHref"
                                    class="text-sm font-medium text-white hover:text-white transition-colors"
                                >
                                    {{ parentShowLabel }}
                                </Link>
                            </div>

                            <h1 class="text-2xl sm:text-3xl font-bold text-white leading-tight">
                                {{ record.display_name }}
                            </h1>

                            <!-- Date range pill -->
                            <div v-if="dateRange || record.year" class="flex flex-wrap items-center gap-3">
                                <div v-if="dateRange" class="flex items-center gap-2 text-white">
                                    <span class="material-icons text-[16px] text-white">calendar_month</span>
                                    <span class="text-sm font-medium">{{ dateRange }}</span>
                                </div>
                                <div v-if="record.year" class="flex items-center gap-2 text-white">
                                    <span class="material-icons text-[16px] text-white">tag</span>
                                    <span class="text-sm font-medium">{{ record.year }}</span>
                                </div>
                            </div>

                            <!-- Status badge mobile -->
                            <div class="sm:hidden">
                                <span :class="['inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-white/20 text-white']">
                                    <span :class="['w-1.5 h-1.5 rounded-full', eventStatus.dot]"></span>
                                    {{ eventStatus.label }}
                                </span>
                            </div>
                        </div>

                        <!-- Right: key stats chips -->
                        <div class="flex flex-wrap gap-2">
                            <a
                                v-if="record.venue && mapsUrl"
                                :href="mapsUrl"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="flex items-center gap-2 rounded-lg bg-white/15 backdrop-blur-sm px-3 py-2 transition-colors hover:bg-white/25"
                                title="Open in Google Maps"
                            >
                                <span class="material-icons text-[16px] text-white">location_city</span>
                                <span class="text-sm text-white font-medium">{{ record.venue }}</span>
                            </a>
                            <div
                                v-else-if="record.venue"
                                class="flex items-center gap-2 rounded-lg bg-white/15 backdrop-blur-sm px-3 py-2"
                            >
                                <span class="material-icons text-[16px] text-white">location_city</span>
                                <span class="text-sm text-white font-medium">{{ record.venue }}</span>
                            </div>
                            <div v-if="record.booth" class="flex items-center gap-2 rounded-lg bg-white/15 backdrop-blur-sm px-3 py-2">
                                <span class="material-icons text-[16px] text-white">store</span>
                                <span class="text-sm text-white font-medium">Booth {{ record.booth }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ================================================================
                 BODY: 2-col grid
                 ================================================================ -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

                <!-- Left / Main column -->
                <div class="lg:col-span-2 space-y-4">

                    <!-- ── Tabbed section ── -->
                    <div class="rounded-xl bg-white dark:bg-gray-800 shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">

                        <!-- Tab bar -->
                        <div class="border-b border-gray-100 dark:border-gray-700 px-2 flex items-center gap-1 overflow-x-auto">
                            <button
                                v-for="tab in tabs"
                                :key="tab.key"
                                @click="activeTab = tab.key"
                                :class="[
                                    'flex items-center gap-1.5 px-4 py-3.5 text-sm font-medium whitespace-nowrap border-b-2 transition-colors',
                                    activeTab === tab.key
                                        ? 'border-primary-600 text-primary-600 dark:text-primary-400 dark:border-primary-400'
                                        : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:border-gray-300'
                                ]"
                            >
                                <span class="material-icons text-[17px]">{{ tab.icon }}</span>
                                {{ tab.label }}
                                <span
                                    v-if="tab.key === 'tasks'"
                                    class="ml-1 rounded-full bg-gray-100 dark:bg-gray-700 px-2 py-0.5 text-xs font-semibold text-gray-600 dark:text-gray-300"
                                >
                                    {{ tasks.length }}
                                </span>
                                <span
                                    v-if="tab.key === 'event_leads'"
                                    class="ml-1 rounded-full bg-gray-100 dark:bg-gray-700 px-2 py-0.5 text-xs font-semibold text-gray-600 dark:text-gray-300"
                                >
                                    {{ eventLeads.length }}
                                </span>
                                <span
                                    v-if="tab.key === 'assets'"
                                    class="ml-1 rounded-full bg-gray-100 dark:bg-gray-700 px-2 py-0.5 text-xs font-semibold text-gray-600 dark:text-gray-300"
                                >
                                    {{ totalAssets }}
                                </span>
                            </button>
                        </div>

                        <!-- ── DETAILS TAB ── -->
                        <div v-show="activeTab === 'details'">

                            <!-- Event Details card -->
                            <div class="divide-y divide-gray-50 dark:divide-gray-700/60">

                                <!-- Display name -->
                                <div class="flex items-start gap-4 px-6 py-4">
                                    <span class="material-icons text-[20px] text-gray-400 mt-0.5 shrink-0">badge</span>
                                    <div class="min-w-0">
                                        <p class="text-xs font-medium text-gray-400 dark:text-gray-500 uppercase tracking-wide mb-0.5">Display Name</p>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ record.display_name }}</p>
                                    </div>
                                </div>

                                <!-- Boat show -->
                                <div v-if="parentShow" class="flex items-start gap-4 px-6 py-4">
                                    <span class="material-icons text-[20px] text-gray-400 mt-0.5 shrink-0">sailing</span>
                                    <div class="min-w-0">
                                        <p class="text-xs font-medium text-gray-400 dark:text-gray-500 uppercase tracking-wide mb-0.5">Boat Show</p>
                                        <Link
                                            :href="parentShowHref"
                                            class="inline-flex items-center gap-1 text-sm font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400 hover:underline"
                                        >
                                            {{ parentShowLabel }}
                                            <span class="material-icons text-[16px]">arrow_forward</span>
                                        </Link>
                                    </div>
                                </div>

                                <!-- Year -->
                                <div v-if="record.year" class="flex items-start gap-4 px-6 py-4">
                                    <span class="material-icons text-[20px] text-gray-400 mt-0.5 shrink-0">tag</span>
                                    <div class="min-w-0">
                                        <p class="text-xs font-medium text-gray-400 dark:text-gray-500 uppercase tracking-wide mb-0.5">Year</p>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ record.year }}</p>
                                    </div>
                                </div>

                                <!-- Dates -->
                                <div v-if="record.starts_at || record.ends_at" class="flex items-start gap-4 px-6 py-4">
                                    <span class="material-icons text-[20px] text-gray-400 mt-0.5 shrink-0">date_range</span>
                                    <div class="min-w-0 grid grid-cols-2 gap-4 w-full">
                                        <div v-if="record.starts_at">
                                            <p class="text-xs font-medium text-gray-400 dark:text-gray-500 uppercase tracking-wide mb-0.5">Starts</p>
                                            <p class="text-sm font-medium text-gray-900 dark:text-white">{{ formatDate(record.starts_at) }}</p>
                                        </div>
                                        <div v-if="record.ends_at">
                                            <p class="text-xs font-medium text-gray-400 dark:text-gray-500 uppercase tracking-wide mb-0.5">Ends</p>
                                            <p class="text-sm font-medium text-gray-900 dark:text-white">{{ formatDate(record.ends_at) }}</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Venue -->
                                <div v-if="record.venue" class="flex items-start gap-4 px-6 py-4">
                                    <span class="material-icons text-[20px] text-gray-400 mt-0.5 shrink-0">location_city</span>
                                    <div class="min-w-0">
                                        <p class="text-xs font-medium text-gray-400 dark:text-gray-500 uppercase tracking-wide mb-0.5">Venue</p>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ record.venue }}</p>
                                    </div>
                                </div>

                                <!-- Booth -->
                                <div v-if="record.booth" class="flex items-start gap-4 px-6 py-4">
                                    <span class="material-icons text-[20px] text-gray-400 mt-0.5 shrink-0">store</span>
                                    <div class="min-w-0">
                                        <p class="text-xs font-medium text-gray-400 dark:text-gray-500 uppercase tracking-wide mb-0.5">Booth</p>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ record.booth }}</p>
                                    </div>
                                </div>

                                <!-- Status row -->
                                <div class="flex items-start gap-4 px-6 py-4">
                                    <span class="material-icons text-[20px] text-gray-400 mt-0.5 shrink-0">toggle_on</span>
                                    <div class="min-w-0">
                                        <p class="text-xs font-medium text-gray-400 dark:text-gray-500 uppercase tracking-wide mb-0.5">Status</p>
                                        <span :class="['inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold', eventStatus.badge]">
                                            <span :class="['w-1.5 h-1.5 rounded-full', eventStatus.dot]"></span>
                                            {{ eventStatus.label }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Venue address section (within details tab) -->
                            <div v-if="hasAddress" class="border-t border-gray-100 dark:border-gray-700">
                                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-50 dark:border-gray-700/50">
                                    <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                                        Venue Address
                                    </h3>
                                    <a
                                        v-if="mapsUrl"
                                        :href="mapsUrl"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex items-center gap-1 text-xs font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400"
                                    >
                                        <span class="material-icons text-[14px]">open_in_new</span>
                                        Open in Maps
                                    </a>
                                </div>
                                <div class="px-6 py-5 space-y-4">

                                    <!-- Address lines -->
                                    <div class="flex items-start gap-4">
                                        <span class="material-icons text-[20px] text-gray-400 mt-0.5 shrink-0">location_on</span>
                                        <address class="not-italic text-sm text-gray-700 dark:text-gray-300 leading-relaxed">
                                            <span v-if="record.address_line_1" class="block font-medium text-gray-900 dark:text-white">{{ record.address_line_1 }}</span>
                                            <span v-if="record.address_line_2" class="block">{{ record.address_line_2 }}</span>
                                            <span class="block">
                                                {{ [record.city, record.state, record.postal_code].filter(Boolean).join(', ') }}
                                            </span>
                                            <span v-if="record.country" class="block text-gray-500 dark:text-gray-400">{{ record.country }}</span>
                                        </address>
                                    </div>

                                    <!-- Coordinates -->
                                    <div v-if="record.latitude && record.longitude" class="flex items-center gap-4">
                                        <span class="material-icons text-[20px] text-gray-400 shrink-0">my_location</span>
                                        <p class="text-xs font-mono text-gray-500 dark:text-gray-400">
                                            {{ Number(record.latitude).toFixed(6) }}, {{ Number(record.longitude).toFixed(6) }}
                                        </p>
                                    </div>

                                    <!-- Map link -->
                                    <div v-if="mapsUrl" class="mt-2 rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40">
                                        <a
                                            :href="mapsUrl"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="flex items-center justify-center gap-2 py-6 text-sm text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors group"
                                        >
                                            <span class="material-icons text-[32px] group-hover:scale-110 transition-transform">map</span>
                                            <span>View on Google Maps</span>
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <!-- Follow-up email (details tab) -->
                            <div class="border-t border-gray-100 dark:border-gray-700">
                                <div class="px-6 py-4 border-b border-gray-50 dark:border-gray-700/50">
                                    <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                                        Follow-up email
                                    </h3>
                                </div>
                                <div class="px-6 py-5 space-y-2 text-sm text-gray-600 dark:text-gray-300">
                                    <div class="flex items-start gap-4">
                                        <span class="material-icons text-[20px] text-gray-400 mt-0.5 shrink-0">mail_outline</span>
                                        <div class="min-w-0 space-y-2">
                                            <p>
                                                <span class="text-gray-500 dark:text-gray-400">Auto follow-up:</span>
                                                {{ followUpSettings.auto_followup ? 'On' : 'Off' }}
                                            </p>
                                            <p>
                                                <span class="text-gray-500 dark:text-gray-400">Delay:</span>
                                                {{ followUpSettings.delay_amount }} {{ followUpSettings.delay_unit }}
                                            </p>
                                            <p>
                                                <span class="text-gray-500 dark:text-gray-400">Staff recipients:</span>
                                                {{
                                                    followUpSettings.recipient_user_count
                                                        ? followUpSettings.recipient_user_count + ' user(s)'
                                                        : 'Account owner (default)'
                                                }}
                                            </p>
                                            <Link
                                                :href="editHref"
                                                class="inline-flex text-xs font-medium text-primary-600 hover:underline dark:text-primary-400"
                                            >
                                                Change on Edit event
                                            </Link>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ── LAYOUT BUILDER TAB ── -->
                        <div v-show="activeTab === 'layout'" class="relative p-4 space-y-2">
                            <div class="flex justify-end">
                                <a
                                    :href="printLayoutHref"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                                >
                                    <span class="material-icons text-base leading-none">print</span>
                                    Print layout
                                </a>
                            </div>
                            <LayoutBuilder
                                v-model:auto-save="layoutAutoSave"
                                :initial-layout-items="layoutItemsForBuilder"
                                :layout-space="layoutSpace"
                                :attach-asset-config="eventAssetAttachConfig"
                                @request-attach-asset="assetPickerOpen = true"
                                @save="onLayoutSave"
                                @change="onLayoutChange"
                            />
                            <div
                                v-if="layoutSavePending"
                                class="pointer-events-none absolute top-3 right-4 z-20 flex items-center gap-2 rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 shadow-md dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200"
                                role="status"
                                aria-live="polite"
                            >
                                <span
                                    class="h-3.5 w-3.5 shrink-0 animate-spin rounded-full border-2 border-primary-600 border-t-transparent dark:border-primary-400"
                                    aria-hidden="true"
                                />
                                Saving layout…
                            </div>
                            <div
                                v-else-if="layoutSavedFlash"
                                class="pointer-events-none absolute top-3 right-4 z-20 flex items-center gap-2 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-medium text-emerald-800 shadow-md dark:border-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200"
                                role="status"
                                aria-live="polite"
                            >
                                <span class="material-icons text-[14px]">check_circle</span>
                                Layout saved
                            </div>
                        </div>

                        <!-- ── CHECKLIST TAB ── -->
                        <div v-show="activeTab === 'checklist'" class="p-6">
                            <p v-if="checklistSaveError" class="mb-4 text-sm text-red-600 dark:text-red-400">
                                {{ checklistSaveError }}
                            </p>
                        
                            <Checklist
                                v-model="checklistData"
                                :templates="checklistTemplates"
                                @save-template="handleSaveTemplate"
                                @save-item="handleSaveChecklistItem"
                            />
                        </div>

                        <!-- ── TASKS TAB ── -->
                        <div v-show="activeTab === 'tasks'" class="p-6">
                            <div class="mb-5">
                                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Tasks</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                                    Manage and assign tasks related to this event.
                                </p>
                            </div>

                            <RelatableTasksBoard
                                v-if="taskStatusOptions.length"
                                :tasks="tasks"
                                :record="record"
                                :relatable-type="boatShowEventRelatableType"
                                :status-options="taskStatusOptions"
                                :default-hidden-status-ids="[3, 4]"
                                :task-form-schema="taskBoardFormSchema"
                                :task-fields-schema="taskBoardFieldsSchema"
                                :task-board-enum-options="taskBoardEnumOptions"
                                :enum-options="enumOptions"
                                :reload-only="['tasks']"
                            />
                            <p v-else class="text-sm text-gray-500 dark:text-gray-400">
                                Task board is not configured for this page.
                            </p>
                        </div>

                        <!-- ── EVENT LEADS TAB ── -->
                        <div v-show="activeTab === 'event_leads'" class="p-6">
                            <div class="mb-5">
                                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Event leads</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                                    Leads captured from the public event form or linked to this event.
                                </p>
                            </div>

                            <div class="rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                                <div class="overflow-x-auto">
                                    <table class="w-full text-left text-sm text-gray-900 dark:text-white">
                                        <thead class="bg-gray-50/60 dark:bg-gray-700/30 text-xs uppercase text-gray-500 dark:text-gray-400">
                                            <tr>
                                                <th class="px-4 py-3 font-semibold">Lead</th>
                                                <th class="px-4 py-3 font-semibold">Email</th>
                                                <th class="px-4 py-3 font-semibold">Phone</th>
                                                <th class="px-4 py-3 font-semibold">Captured</th>
                                                <th class="px-4 py-3 font-semibold text-right w-28">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-for="row in eventLeads"
                                                :key="row.id"
                                                class="border-t border-gray-100 dark:border-gray-700 hover:bg-gray-50/50 dark:hover:bg-gray-700/30 transition-colors"
                                            >
                                                <td class="px-4 py-3 font-medium">
                                                    <template v-if="row.lead">
                                                        {{ row.lead.display_name || '—' }}
                                                    </template>
                                                    <span v-else class="text-gray-400 dark:text-gray-500 italic">Lead record removed</span>
                                                </td>
                                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                                    {{ row.lead?.email || '—' }}
                                                </td>
                                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                                    {{ row.lead?.phone || '—' }}
                                                </td>
                                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300 whitespace-nowrap">
                                                    {{ formatDateTime(row.captured_at) }}
                                                </td>
                                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                                    <Link
                                                        v-if="row.lead?.id"
                                                        :href="route('leads.show', row.lead.id)"
                                                        class="text-primary-600 hover:text-primary-700 dark:text-primary-400 text-xs font-medium"
                                                    >
                                                        View lead
                                                    </Link>
                                                    <span v-else class="text-xs text-gray-400 dark:text-gray-500">—</span>
                                                </td>
                                            </tr>
                                            <tr v-if="!eventLeads.length">
                                                <td colspan="5" class="px-4 py-10 text-center text-sm text-gray-400 dark:text-gray-500">
                                                    No leads captured for this event yet.
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- ── ASSET LIST TAB ── -->
                        <div v-show="activeTab === 'assets'" class="p-6">
                            <div class="flex items-center justify-between mb-5">
                                <div>
                                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Asset List</h3>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                                        Boats, engines, and trailers assigned to this event.
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors"
                                    @click="assetPickerOpen = true"
                                >
                                    <span class="material-icons text-[16px]">add</span>
                                    Add asset
                                </button>
                            </div>

                            <div class="space-y-6">

                                <!-- ── Boats ── -->
                                <div class="rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                                    <div class="flex items-center justify-between px-4 py-3 bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700">
                                        <div class="flex items-center gap-2">
                                            <span class="material-icons text-[18px] text-blue-500">directions_boat</span>
                                            <span class="text-sm font-semibold text-gray-800 dark:text-white">Boats</span>
                                            <span class="rounded-full bg-blue-100 dark:bg-blue-900/40 px-2 py-0.5 text-xs font-semibold text-blue-700 dark:text-blue-300">
                                                {{ assets.boats?.length ?? 0 }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="overflow-x-auto">
                                        <table class="w-full text-left text-sm text-gray-900 dark:text-white">
                                            <thead class="bg-gray-50/60 dark:bg-gray-700/30 text-xs uppercase text-gray-500 dark:text-gray-400">
                                                <tr>
                                                    <th class="px-4 py-3 font-semibold">Name / Model</th>
                                                    <th class="px-4 py-3 font-semibold">Brand</th>
                                                    <th class="px-4 py-3 font-semibold">Year</th>
                                                    <th class="px-4 py-3 font-semibold">Length</th>
                                                    <th class="px-4 py-3 font-semibold">Unit</th>
                                                    <th class="px-4 py-3 font-semibold text-right w-36">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr
                                                    v-for="boat in assets.boats"
                                                    :key="boat.event_asset_id ?? boat.id"
                                                    class="border-t border-gray-100 dark:border-gray-700 hover:bg-gray-50/50 dark:hover:bg-gray-700/30 transition-colors"
                                                >
                                                    <td class="px-4 py-3 font-medium">{{ boat.display_name ?? boat.model ?? '—' }}</td>
                                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ boat.make ?? '—' }}</td>
                                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ boat.year ?? '—' }}</td>
                                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ formatBoatLengthFt(boat) }}</td>
                                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300 text-xs max-w-[200px] truncate" :title="formatAssetUnitLabel(boat)">
                                                        {{ formatAssetUnitLabel(boat) }}
                                                    </td>
                                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                                        <Link
                                                            v-if="assetUnitShowHref(boat)"
                                                            :href="assetUnitShowHref(boat)"
                                                            class="text-primary-600 hover:text-primary-700 dark:text-primary-400 text-xs font-medium me-3"
                                                        >
                                                            View
                                                        </Link>
                                                        <button
                                                            type="button"
                                                            class="text-xs font-medium text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                                                            @click="removeEventAsset(boat)"
                                                        >
                                                            Remove
                                                        </button>
                                                    </td>
                                                </tr>
                                                <tr v-if="!assets.boats?.length">
                                                    <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-400 dark:text-gray-500">
                                                        No boats assigned to this event.
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- ── Engines ── -->
                                <div class="rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                                    <div class="flex items-center justify-between px-4 py-3 bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700">
                                        <div class="flex items-center gap-2">
                                            <span class="material-icons text-[18px] text-orange-500">settings</span>
                                            <span class="text-sm font-semibold text-gray-800 dark:text-white">Engines</span>
                                            <span class="rounded-full bg-orange-100 dark:bg-orange-900/40 px-2 py-0.5 text-xs font-semibold text-orange-700 dark:text-orange-300">
                                                {{ assets.engines?.length ?? 0 }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="overflow-x-auto">
                                        <table class="w-full text-left text-sm text-gray-900 dark:text-white">
                                            <thead class="bg-gray-50/60 dark:bg-gray-700/30 text-xs uppercase text-gray-500 dark:text-gray-400">
                                                <tr>
                                                    <th class="px-4 py-3 font-semibold">Name / Model</th>
                                                    <th class="px-4 py-3 font-semibold">Brand</th>
                                                    <th class="px-4 py-3 font-semibold">Year</th>
                                                    <th class="px-4 py-3 font-semibold">HP</th>
                                                    <th class="px-4 py-3 font-semibold">Unit</th>
                                                    <th class="px-4 py-3 font-semibold text-right w-36">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr
                                                    v-for="engine in assets.engines"
                                                    :key="engine.event_asset_id ?? engine.id"
                                                    class="border-t border-gray-100 dark:border-gray-700 hover:bg-gray-50/50 dark:hover:bg-gray-700/30 transition-colors"
                                                >
                                                    <td class="px-4 py-3 font-medium">{{ engine.display_name ?? engine.model ?? '—' }}</td>
                                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ engine.make ?? '—' }}</td>
                                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ engine.year ?? '—' }}</td>
                                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ engine.horsepower ? `${engine.horsepower} hp` : '—' }}</td>
                                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300 text-xs max-w-[200px] truncate" :title="formatAssetUnitLabel(engine)">
                                                        {{ formatAssetUnitLabel(engine) }}
                                                    </td>
                                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                                        <Link
                                                            v-if="assetUnitShowHref(engine)"
                                                            :href="assetUnitShowHref(engine)"
                                                            class="text-primary-600 hover:text-primary-700 dark:text-primary-400 text-xs font-medium me-3"
                                                        >
                                                            View
                                                        </Link>
                                                        <button
                                                            type="button"
                                                            class="text-xs font-medium text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                                                            @click="removeEventAsset(engine)"
                                                        >
                                                            Remove
                                                        </button>
                                                    </td>
                                                </tr>
                                                <tr v-if="!assets.engines?.length">
                                                    <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-400 dark:text-gray-500">
                                                        No engines assigned to this event.
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- ── Trailers ── -->
                                <div class="rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                                    <div class="flex items-center justify-between px-4 py-3 bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700">
                                        <div class="flex items-center gap-2">
                                            <span class="material-icons text-[18px] text-green-500">local_shipping</span>
                                            <span class="text-sm font-semibold text-gray-800 dark:text-white">Trailers</span>
                                            <span class="rounded-full bg-green-100 dark:bg-green-900/40 px-2 py-0.5 text-xs font-semibold text-green-700 dark:text-green-300">
                                                {{ assets.trailers?.length ?? 0 }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="overflow-x-auto">
                                        <table class="w-full text-left text-sm text-gray-900 dark:text-white">
                                            <thead class="bg-gray-50/60 dark:bg-gray-700/30 text-xs uppercase text-gray-500 dark:text-gray-400">
                                                <tr>
                                                    <th class="px-4 py-3 font-semibold">Name / Model</th>
                                                    <th class="px-4 py-3 font-semibold">Brand</th>
                                                    <th class="px-4 py-3 font-semibold">Year</th>
                                                    <th class="px-4 py-3 font-semibold">Type</th>
                                                    <th class="px-4 py-3 font-semibold">Unit</th>
                                                    <th class="px-4 py-3 font-semibold text-right w-36">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr
                                                    v-for="trailer in assets.trailers"
                                                    :key="trailer.event_asset_id ?? trailer.id"
                                                    class="border-t border-gray-100 dark:border-gray-700 hover:bg-gray-50/50 dark:hover:bg-gray-700/30 transition-colors"
                                                >
                                                    <td class="px-4 py-3 font-medium">{{ trailer.display_name ?? trailer.model ?? '—' }}</td>
                                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ trailer.make ?? '—' }}</td>
                                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ trailer.year ?? '—' }}</td>
                                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ trailer.trailer_type ?? '—' }}</td>
                                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300 text-xs max-w-[200px] truncate" :title="formatAssetUnitLabel(trailer)">
                                                        {{ formatAssetUnitLabel(trailer) }}
                                                    </td>
                                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                                        <Link
                                                            v-if="assetUnitShowHref(trailer)"
                                                            :href="assetUnitShowHref(trailer)"
                                                            class="text-primary-600 hover:text-primary-700 dark:text-primary-400 text-xs font-medium me-3"
                                                        >
                                                            View
                                                        </Link>
                                                        <button
                                                            type="button"
                                                            class="text-xs font-medium text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                                                            @click="removeEventAsset(trailer)"
                                                        >
                                                            Remove
                                                        </button>
                                                    </td>
                                                </tr>
                                                <tr v-if="!assets.trailers?.length">
                                                    <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-400 dark:text-gray-500">
                                                        No trailers assigned to this event.
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                            </div>
                        </div>

                    </div>
                    <!-- /tabbed section -->

                </div>
                <!-- /main column -->

                <!-- Right / Sidebar column -->
                <div class="space-y-4">

                    <!-- Meta card -->
                    <div class="rounded-xl bg-white dark:bg-gray-800 shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700">
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">Record Info</span>
                        </div>
                        <ul class="divide-y divide-gray-50 dark:divide-gray-700/60 text-sm">
                            <li v-if="record.id" class="flex items-center gap-3 px-5 py-3">
                                <span class="material-icons text-[16px] text-gray-400">tag</span>
                                <span class="text-gray-500 dark:text-gray-400 flex-1">ID</span>
                                <span class="font-mono text-xs text-gray-900 dark:text-white">{{ record.id }}</span>
                            </li>
                            <li v-if="record.created_at" class="flex items-center gap-3 px-5 py-3">
                                <span class="material-icons text-[16px] text-gray-400">calendar_today</span>
                                <span class="text-gray-500 dark:text-gray-400 flex-1">Created</span>
                                <span class="text-xs text-gray-900 dark:text-white">{{ formatDate(record.created_at) }}</span>
                            </li>
                            <li v-if="record.updated_at" class="flex items-center gap-3 px-5 py-3">
                                <span class="material-icons text-[16px] text-gray-400">update</span>
                                <span class="text-gray-500 dark:text-gray-400 flex-1">Updated</span>
                                <span class="text-xs text-gray-900 dark:text-white">{{ formatDate(record.updated_at) }}</span>
                            </li>
                            <li class="flex items-center gap-3 px-5 py-3">
                                <span class="material-icons text-[16px] text-gray-400">toggle_on</span>
                                <span class="text-gray-500 dark:text-gray-400 flex-1">Active</span>
                                <span :class="record.active ? 'text-green-600 dark:text-green-400' : 'text-gray-400'">
                                    <span class="material-icons text-[18px]">{{ record.active ? 'check_circle' : 'cancel' }}</span>
                                </span>
                            </li>
                        </ul>
                    </div>

                    <!-- Timeline card (if dates available) -->
                    <div v-if="record.starts_at || record.ends_at" class="rounded-xl bg-white dark:bg-gray-800 shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700">
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">Timeline</span>
                        </div>
                        <div class="p-5">
                            <ol class="relative ms-2 border-s border-gray-200 dark:border-gray-700 space-y-4">
                                <li class="ms-6">
                                    <span class="absolute -start-3 flex h-6 w-6 items-center justify-center rounded-full bg-white dark:bg-gray-800">
                                        <span :class="['material-icons text-[20px]', eventHasStarted ? 'text-green-500' : 'text-gray-300 dark:text-gray-600']">check_circle</span>
                                    </span>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">Event starts</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                            {{ record.starts_at ? formatDate(record.starts_at) : 'TBD' }}
                                        </p>
                                    </div>
                                </li>
                                <li v-if="record.ends_at" class="ms-6">
                                    <span class="absolute -start-3 flex h-6 w-6 items-center justify-center rounded-full bg-white dark:bg-gray-800">
                                        <span :class="['material-icons text-[20px]', isPast ? 'text-green-500' : 'text-gray-300 dark:text-gray-600']">check_circle</span>
                                    </span>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">Event ends</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ formatDate(record.ends_at) }}</p>
                                    </div>
                                </li>
                            </ol>
                        </div>
                    </div>

                    <!-- Quick-jump to tabs -->
                    <div class="rounded-xl bg-white dark:bg-gray-800 shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700">
                            <span class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Jump to</span>
                        </div>
                        <div class="p-3 space-y-1">
                            <button
                                v-for="tab in tabs"
                                :key="tab.key"
                                @click="activeTab = tab.key"
                                :class="[
                                    'flex w-full items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors',
                                    activeTab === tab.key
                                        ? 'bg-primary-50 dark:bg-primary-900/20 text-primary-700 dark:text-primary-300'
                                        : 'text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700',
                                ]"
                            >
                                <span :class="['material-icons text-[18px]', activeTab === tab.key ? 'text-primary-500' : 'text-gray-400']">
                                    {{ tab.icon }}
                                </span>
                                {{ tab.label }}
                            </button>
                        </div>
                    </div>

                </div>
                <!-- /sidebar -->

            </div>
        </div>

        <BoatShowEventAssetPickerModal
            v-model="assetPickerOpen"
            :store-url="eventAssetStoreUrl"
            :units-base-url="eventAssetUnitsUrl"
            @attached="onEventAssetAttached"
        />
    </TenantLayout>
</template>
