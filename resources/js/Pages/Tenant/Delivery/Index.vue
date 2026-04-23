<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    deliveries: { type: Object, default: () => ({ data: [] }) },
    todayDeliveries: { type: Array, default: () => [] },
    upcomingDeliveries: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({ scheduled: 0, en_route: 0, delivered: 0, cancelled: 0 }) },
    filters: { type: Object, default: () => ({}) },
    locationOptions: { type: Array, default: () => [] },
    subsidiaryOptions: { type: Array, default: () => [] },
    fieldsSchema: { type: Object, default: () => ({}) },
    enumOptions: { type: Object, default: () => ({}) },
});

const ALLOWED_STATUSES = ['scheduled', 'confirmed', 'en_route', 'delivered', 'cancelled', 'rescheduled'];
const DEFAULT_STATUSES = ['scheduled', 'en_route', 'rescheduled'];

const parseInitialStatuses = (filters) => {
    const s = filters?.status;
    if (s === 'all') {
        return [...ALLOWED_STATUSES];
    }
    if (Array.isArray(s) && s.length) {
        const cleaned = s.filter((x) => ALLOWED_STATUSES.includes(x));
        return cleaned.length ? cleaned : [...DEFAULT_STATUSES];
    }
    if (typeof s === 'string' && s !== '' && s !== 'all') {
        return ALLOWED_STATUSES.includes(s) ? [s] : [...DEFAULT_STATUSES];
    }
    return [...DEFAULT_STATUSES];
};

const selectedStatuses = ref(parseInitialStatuses(props.filters));
const searchQuery = ref(props.filters?.search ?? '');
const selectedSubsidiaryId = ref(
    props.filters?.subsidiary_id != null && props.filters?.subsidiary_id !== '' ? String(props.filters.subsidiary_id) : '',
);
const selectedLocationId = ref(
    props.filters?.location_id != null && props.filters?.location_id !== '' ? String(props.filters.location_id) : '',
);

watch(
    () => props.filters,
    (f) => {
        searchQuery.value = f?.search ?? '';
        selectedStatuses.value = parseInitialStatuses(f);
        selectedSubsidiaryId.value = f?.subsidiary_id != null && f?.subsidiary_id !== '' ? String(f.subsidiary_id) : '';
        selectedLocationId.value = f?.location_id != null && f?.location_id !== '' ? String(f.location_id) : '';
    },
    { deep: true },
);

const applyTableFilters = () => {
    const params = {};
    if (searchQuery.value?.trim()) {
        params.search = searchQuery.value.trim();
    }
    if (selectedSubsidiaryId.value) {
        params.subsidiary_id = selectedSubsidiaryId.value;
    }
    if (selectedLocationId.value) {
        params.location_id = selectedLocationId.value;
    }
    const sel = selectedStatuses.value;
    const allSelected = ALLOWED_STATUSES.every((x) => sel.includes(x));
    const noneSelected = sel.length === 0;
    if (allSelected || noneSelected) {
        params.status = 'all';
    } else {
        params.status = [...sel];
    }
    router.get(route('deliveries.index'), params, { preserveState: true, preserveScroll: true, replace: true });
};

let searchDebounce = null;
watch(searchQuery, () => {
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(() => applyTableFilters(), 350);
});

const clearSearch = () => {
    clearTimeout(searchDebounce);
    searchQuery.value = '';
    applyTableFilters();
};

const clearSubsidiaryFilter = () => {
    selectedSubsidiaryId.value = '';
    applyTableFilters();
};

const clearLocationFilter = () => {
    selectedLocationId.value = '';
    applyTableFilters();
};

const showDeliveryFilterPills = computed(
    () => !!(
        searchQuery.value?.trim()
        || selectedSubsidiaryId.value
        || selectedLocationId.value
        || selectedStatuses.value.length > 0
    ),
);

const removeStatusPill = (statusId) => {
    const str = String(statusId);
    selectedStatuses.value = selectedStatuses.value.map(String).filter((s) => s !== str);
    applyTableFilters();
};

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Deliveries' },
]);

const getCustomerName = (d) => d.customer?.display_name ?? d.customer?.name ?? '—';
const getLocationName = (d) => d.location?.display_name ?? '—';
const getTechnicianName = (d) => d.technician?.display_name ?? d.technician?.name ?? '—';

/** Line items: prefer delivery_items; fall back to legacy single asset_unit. */
const getDeliveryLineItems = (d) => {
    if (Array.isArray(d.items) && d.items.length) {
        return d.items;
    }
    const u = d.asset_unit ?? d.assetUnit;
    if (u) {
        return [
            {
                id: 'legacy-asset',
                name: u.display_name ?? u.name,
                asset_unit: u,
                assetUnit: u,
            },
        ];
    }
    return [];
};

const itemTitle = (item) => {
    const unit = item.asset_unit ?? item.assetUnit ?? null;
    const variant = item.asset_variant ?? item.assetVariant ?? null;
    const assetDisplay = unit?.asset?.display_name;
    if (assetDisplay) return assetDisplay;
    if (variant?.display_name) return variant.display_name;
    if (variant?.name) return variant.name;
    return item.name ?? 'Asset';
};

const itemUnitDetail = (item) => {
    const unit = item.asset_unit ?? item.assetUnit ?? null;
    if (!unit) return item.serial_number_snapshot ?? null;
    const raw = unit.display_name ?? null;
    if (raw) {
        const parts = String(raw).split(' - ');
        return parts.length > 1 ? parts.slice(1).join(' - ') : parts[0];
    }
    return unit.serial_number ?? unit.hin ?? unit.sku ?? item.serial_number_snapshot ?? null;
};

const itemsSummaryLabel = (d) => {
    const lineItems = getDeliveryLineItems(d);
    if (lineItems.length === 0) return '—';
    if (lineItems.length === 1) {
        return itemTitle(lineItems[0]);
    }
    return `${lineItems.length} assets`;
};

const subsidiaryLabel = (id) => {
    const n = String(id);
    const o = (props.subsidiaryOptions ?? []).find((s) => String(s.id) === n);
    return o?.display_name ?? `Subsidiary #${id}`;
};

const locationLabel = (id) => {
    const n = String(id);
    const o = (props.locationOptions ?? []).find((l) => String(l.id) === n);
    return o?.display_name ?? `Location #${id}`;
};

const formatTime = (val) => {
    if (!val) return '—';
    const d = typeof val === 'string' ? new Date(val) : val;
    return d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
};

const formatDateTime = (val) => {
    if (!val) return '—';
    const d = typeof val === 'string' ? new Date(val) : val;
    return d.toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true });
};

const formatScheduledShort = (val) => {
    if (!val) return '—';
    const d = typeof val === 'string' ? new Date(val) : val;
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const dayStart = new Date(d);
    dayStart.setHours(0, 0, 0, 0);
    if (dayStart.getTime() === today.getTime()) {
        return d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
    }
    return d.toLocaleString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true });
};

// Mini calendar - current month, days with deliveries
const calendarDays = computed(() => {
    const now = new Date();
    const year = now.getFullYear();
    const month = now.getMonth();
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const startPad = firstDay.getDay();
    const days = [];
    for (let i = 0; i < startPad; i++) days.push({ day: null });
    const deliveryDays = {};
    [...props.todayDeliveries, ...props.upcomingDeliveries].forEach((d) => {
        const dt = d.scheduled_at ? new Date(d.scheduled_at) : null;
        if (dt && dt.getMonth() === month && dt.getFullYear() === year) {
            const day = dt.getDate();
            deliveryDays[day] = (deliveryDays[day] || 0) + 1;
        }
    });
    for (let d = 1; d <= lastDay.getDate(); d++) {
        days.push({ day: d, count: deliveryDays[d] || 0, isToday: d === now.getDate() });
    }
    return days;
});

const calendarTitle = computed(() => {
    const now = new Date();
    return now.toLocaleString('en-US', { month: 'long', year: 'numeric' });
});

const statusConfig = {
    scheduled: { label: 'Scheduled', bg: 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300', icon: 'schedule', dot: 'bg-blue-500' },
    confirmed: { label: 'Confirmed', bg: 'bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300', icon: 'task_alt', dot: 'bg-indigo-500' },
    en_route: { label: 'En Route', bg: 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-300', icon: 'local_shipping', dot: 'bg-yellow-500' },
    delivered: { label: 'Delivered', bg: 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300', icon: 'check_circle', dot: 'bg-green-500' },
    cancelled: { label: 'Cancelled', bg: 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300', icon: 'cancel', dot: 'bg-red-500' },
    rescheduled: { label: 'Rescheduled', bg: 'bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300', icon: 'event_repeat', dot: 'bg-purple-500' },
};

const getStatus = (s) => statusConfig[s] || statusConfig.scheduled;

/** Full Tailwind classes for filter dots (avoid dynamic `bg-${color}-500` in template). */
const STATUS_FILTER_DOT = {
    scheduled: 'bg-blue-500',
    confirmed: 'bg-indigo-500',
    en_route: 'bg-yellow-500',
    delivered: 'bg-green-500',
    cancelled: 'bg-red-500',
    rescheduled: 'bg-purple-500',
};

const statusFilterOptions = computed(() =>
    ALLOWED_STATUSES.map((id) => ({
        id,
        name: getStatus(id).label,
        dotClass: STATUS_FILTER_DOT[id] ?? 'bg-gray-500',
    })),
);

const openStatusFilter = ref(false);

const toggleStatusFilterDropdown = () => {
    openStatusFilter.value = !openStatusFilter.value;
};

const handleStatusFilterClickOutside = (e) => {
    if (!e.target.closest('[data-status-quick-filter]')) {
        openStatusFilter.value = false;
    }
};

const statusSelectionCount = computed(() => selectedStatuses.value.length);

const statusFilterButtonActive = computed(() => selectedStatuses.value.length > 0);

const toggleStatusValue = (statusId) => {
    const str = String(statusId);
    const cur = selectedStatuses.value.map(String);
    const next = cur.includes(str) ? cur.filter((v) => v !== str) : [...cur, str];
    selectedStatuses.value = next;
    applyTableFilters();
};

const clearStatusFilter = () => {
    selectedStatuses.value = [];
    applyTableFilters();
    openStatusFilter.value = false;
};

onMounted(() => {
    document.addEventListener('click', handleStatusFilterClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleStatusFilterClickOutside);
});
</script>

<template>
    <Head title="Deliveries" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
            </div>
        </template>

        <div class="space-y-6">

            <!-- ── Stat Cards ──────────────────────────────────────── -->
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">

                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-md font-medium text-gray-500 dark:text-gray-400">Scheduled</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white mt-2">{{ stats.scheduled }}</p>
                        </div>
                        <div class="h-12 w-12 rounded-lg bg-blue-100 dark:bg-blue-900/20 flex items-center justify-center">
                            <span class="material-icons text-2xl text-blue-600 dark:text-blue-400">schedule</span>
                        </div>
                    </div>
                    <p class="mt-3 text-sm text-blue-600 dark:text-blue-400 font-medium flex items-center gap-1">
                        <span class="material-icons text-md">arrow_upward</span> 4 added this week
                    </p>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-md font-medium text-gray-500 dark:text-gray-400">En Route</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white mt-2">{{ stats.en_route }}</p>
                        </div>
                        <div class="h-12 w-12 rounded-lg bg-yellow-100 dark:bg-yellow-900/20 flex items-center justify-center">
                            <span class="material-icons text-2xl text-yellow-600 dark:text-yellow-400">local_shipping</span>
                        </div>
                    </div>
                    <p class="mt-3 text-sm text-yellow-600 dark:text-yellow-400 font-medium flex items-center gap-1">
                        <span class="material-icons text-md">radio_button_checked</span> Live now
                    </p>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-md font-medium text-gray-500 dark:text-gray-400">Delivered</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white mt-2">{{ stats.delivered }}</p>
                        </div>
                        <div class="h-12 w-12 rounded-lg bg-green-100 dark:bg-green-900/20 flex items-center justify-center">
                            <span class="material-icons text-2xl text-green-600 dark:text-green-400">inventory</span>
                        </div>
                    </div>
                    <p class="mt-3 text-sm text-green-600 dark:text-green-400 font-medium flex items-center gap-1">
                        <span class="material-icons text-md">arrow_upward</span> 94% on-time rate
                    </p>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-md font-medium text-gray-500 dark:text-gray-400">Cancelled</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white mt-2">{{ stats.cancelled }}</p>
                        </div>
                        <div class="h-12 w-12 rounded-lg bg-red-100 dark:bg-red-900/20 flex items-center justify-center">
                            <span class="material-icons text-2xl text-red-600 dark:text-red-400">cancel</span>
                        </div>
                    </div>
                    <p class="mt-3 text-sm text-gray-400 dark:text-gray-500 font-medium flex items-center gap-1">
                        <span class="material-icons text-md">remove</span> This month
                    </p>
                </div>
            </div>

            <!-- ── Main Content: Timeline + Sidebar ────────────────── -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <!-- Today's Schedule (Timeline) -->
                <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">

                    <!-- Card Header -->
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center gap-2">
                            <span class="material-icons text-blue-600 dark:text-blue-400">today</span>
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Today's Schedule</h2>
                            <span class="ml-1 px-2 py-0.5 rounded-full bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 text-sm font-bold">
                                {{ todayDeliveries.length }}
                            </span>
                        </div>
                        <span class="text-md text-gray-400 dark:text-gray-500">{{ new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) }}</span>
                    </div>

                    <!-- Timeline -->
                    <div v-if="!todayDeliveries.length" class="px-6 py-16 flex flex-col items-center justify-center text-center">
                        <div class="h-16 w-16 rounded-full bg-gray-100 dark:bg-gray-700/80 flex items-center justify-center mb-4">
                            <span class="material-icons text-4xl text-gray-400 dark:text-gray-500">event_busy</span>
                        </div>
                        <p class="text-base font-medium text-gray-900 dark:text-white">No deliveries scheduled for today</p>
                    </div>
                    <div v-else class="p-6 space-y-0">
                        <div
                            v-for="(delivery, idx) in todayDeliveries"
                            :key="delivery.id"
                            class="relative flex gap-4"
                        >
                            <!-- Timeline spine -->
                            <div class="flex flex-col items-center">
                                <div
                                    class="w-3 h-3 rounded-full ring-2 ring-white dark:ring-gray-800 mt-1 flex-shrink-0 z-10"
                                    :class="getStatus(delivery.status).dot"
                                />
                                <div v-if="idx < todayDeliveries.length - 1" class="w-px flex-1 bg-gray-200 dark:bg-gray-700 my-1" />
                            </div>

                            <!-- Card -->
                            <Link
                                :href="route('deliveries.show', delivery.id)"
                                class="flex-1 mb-4 group rounded-lg border p-4 cursor-pointer transition-all hover:shadow-md block"
                                :class="delivery.status === 'delivered'
                                    ? 'border-green-200 dark:border-green-900/50 bg-green-50/40 dark:bg-green-900/10'
                                    : delivery.status === 'en_route'
                                    ? 'border-yellow-200 dark:border-yellow-900/50 bg-yellow-50/40 dark:bg-yellow-900/10'
                                    : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800'"
                            >
                                <div class="flex items-start justify-between gap-2 mb-2">
                                    <div>
                                        <div class="flex items-center gap-2 mb-1">
                                            <span class="text-sm font-mono text-gray-400 dark:text-gray-500">{{ delivery.display_name }}</span>
                                            <span :class="['inline-flex items-center gap-1 px-2 py-0.5 rounded text-sm font-medium', getStatus(delivery.status).bg]">
                                                <span class="material-icons text-sm">{{ getStatus(delivery.status).icon }}</span>
                                                {{ getStatus(delivery.status).label }}
                                            </span>
                                        </div>
                                        <p class="text-md font-semibold text-gray-900 dark:text-white">{{ getCustomerName(delivery) }}</p>
                                    </div>
                                    <span class="material-icons text-gray-300 dark:text-gray-600 group-hover:text-blue-400 transition-colors">chevron_right</span>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1.5 text-sm text-gray-500 dark:text-gray-400">
                                    <div class="flex items-center gap-1.5 min-w-0">
                                        <span class="material-icons text-md text-gray-400 shrink-0">warehouse</span>
                                        <span class="truncate" :title="getLocationName(delivery)">{{ getLocationName(delivery) }}</span>
                                    </div>
                                    <div class="flex items-center gap-1.5 min-w-0">
                                        <span class="material-icons text-md text-gray-400 shrink-0">engineering</span>
                                        <span class="truncate">{{ getTechnicianName(delivery) }}</span>
                                    </div>
                                    <div class="flex items-center gap-1.5 sm:col-span-2">
                                        <span class="material-icons text-md text-gray-400 shrink-0">directions_boat</span>
                                        <div class="min-w-0 space-y-0.5">
                                            <div
                                                v-for="(item, iidx) in getDeliveryLineItems(delivery)"
                                                :key="item.id ?? iidx"
                                                class="text-gray-600 dark:text-gray-300"
                                            >
                                                <span class="font-medium text-gray-800 dark:text-white">{{ itemTitle(item) }}</span>
                                                <span v-if="itemUnitDetail(item)" class="text-gray-500"> · {{ itemUnitDetail(item) }}</span>
                                            </div>
                                            <p v-if="!getDeliveryLineItems(delivery).length" class="text-gray-400 italic">No assets listed</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <span class="material-icons text-md text-gray-400">schedule</span>
                                        <span>{{ formatTime(delivery.scheduled_at) }}</span>
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <span class="material-icons text-md text-gray-400">location_on</span>
                                        <span>ETA {{ formatTime(delivery.estimated_arrival_at) }}</span>
                                    </div>
                                    <div v-if="delivery.recipient_name" class="flex items-center gap-1.5 col-span-2">
                                        <span class="material-icons text-md text-green-500">how_to_reg</span>
                                        <span class="text-green-600 dark:text-green-400 font-medium">Signed by {{ delivery.recipient_name }}</span>
                                    </div>
                                </div>
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Sidebar: Calendar + Upcoming -->
                <div class="flex flex-col gap-6">

                    <!-- Mini Calendar -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-5">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-md font-semibold text-gray-900 dark:text-white">{{ calendarTitle }}</h3>
                            <div class="flex gap-1">
                                <button class="p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500 dark:text-gray-400">
                                    <span class="material-icons text-md">chevron_left</span>
                                </button>
                                <button class="p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500 dark:text-gray-400">
                                    <span class="material-icons text-md">chevron_right</span>
                                </button>
                            </div>
                        </div>

                        <!-- Day headers -->
                        <div class="grid grid-cols-7 mb-1">
                            <div v-for="d in ['S','M','T','W','T','F','S']" :key="d" class="text-center text-sm font-medium text-gray-400 dark:text-gray-500 py-1">
                                {{ d }}
                            </div>
                        </div>

                        <!-- Day grid -->
                        <div class="grid grid-cols-7 gap-y-1">
                            <div
                                v-for="(cell, i) in calendarDays"
                                :key="i"
                                class="relative flex flex-col items-center py-1"
                            >
                                <template v-if="cell.day">
                                    <button
                                        :class="[
                                            'w-7 h-7 rounded-full text-sm font-medium flex items-center justify-center transition-colors',
                                            cell.isToday
                                                ? 'bg-blue-600 text-white font-bold'
                                                : cell.count > 0
                                                ? 'text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700'
                                                : 'text-gray-400 dark:text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700'
                                        ]"
                                    >
                                        {{ cell.day }}
                                    </button>
                                    <!-- Delivery dot indicator -->
                                    <span
                                        v-if="cell.count > 0 && !cell.isToday"
                                        class="absolute bottom-0.5 w-1 h-1 rounded-full bg-blue-500"
                                    />
                                    <span
                                        v-if="cell.count > 2 && !cell.isToday"
                                        class="absolute bottom-0.5 right-1 w-1 h-1 rounded-full bg-blue-300"
                                    />
                                </template>
                            </div>
                        </div>

                        <!-- Legend -->
                        <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-700 flex items-center gap-3 text-sm text-gray-500 dark:text-gray-400">
                            <div class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-blue-600 inline-block"></span> Today</div>
                            <div class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-blue-500 inline-block"></span> Deliveries</div>
                        </div>
                    </div>

                    <!-- Upcoming Deliveries -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden flex-1">
                        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                            <div class="flex items-center gap-2">
                                <span class="material-icons text-gray-500 dark:text-gray-400 text-xl">event_upcoming</span>
                                <h3 class="text-md font-semibold text-gray-900 dark:text-white">Upcoming</h3>
                            </div>
                            <Link :href="route('deliveries.index')" class="text-sm text-blue-600 dark:text-blue-400 hover:underline font-medium">View all</Link>
                        </div>

                        <div class="divide-y divide-gray-100 dark:divide-gray-700">
                            <Link
                                v-for="delivery in upcomingDeliveries"
                                :key="delivery.id"
                                :href="route('deliveries.show', delivery.id)"
                                class="flex items-start gap-3 px-5 py-3.5 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors group"
                            >
                                <div :class="['w-2 h-2 rounded-full mt-1.5 flex-shrink-0', getStatus(delivery.status).dot]" />
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-gray-800 dark:text-white truncate">{{ getCustomerName(delivery) }}</p>
                                    <p class="text-sm text-gray-400 dark:text-gray-500 truncate">
                                        <span v-if="getLocationName(delivery) !== '—'" class="text-gray-500">{{ getLocationName(delivery) }} · </span>
                                        {{ itemsSummaryLabel(delivery) }}
                                    </p>
                                    <div class="flex items-center gap-1 mt-1 text-sm text-gray-400 dark:text-gray-500">
                                        <span class="material-icons text-sm">schedule</span>
                                        {{ formatScheduledShort(delivery.scheduled_at) }}
                                    </div>
                                </div>
                                <span :class="['inline-flex items-center px-1.5 py-0.5 rounded text-sm font-medium flex-shrink-0', getStatus(delivery.status).bg]">
                                    {{ getStatus(delivery.status).label }}
                                </span>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Filters & All Deliveries Table ──────────────────── -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">

                <!-- Toolbar -->
                <div class="flex flex-col sm:flex-row gap-4 items-start sm:items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">

                        <!-- Search -->
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <span class="material-icons text-lg text-gray-400">search</span>
                            </div>
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Search deliveries..."
                                class="block pl-10 pr-4 py-2 text-md rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full sm:w-64"
                            />
                        </div>

                        <!-- Subsidiary -->
                        <div class="w-full sm:w-48">
                            <label for="filter-subsidiary" class="sr-only">Subsidiary</label>
                            <select
                                id="filter-subsidiary"
                                v-model="selectedSubsidiaryId"
                                class="block w-full py-2 px-3 text-md rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500"
                                @change="applyTableFilters"
                            >
                                <option value="">All subsidiaries</option>
                                <option
                                    v-for="s in subsidiaryOptions"
                                    :key="s.id"
                                    :value="String(s.id)"
                                >
                                    {{ s.display_name }}
                                </option>
                            </select>
                        </div>

                        <!-- Location -->
                        <div class="w-full sm:w-48">
                            <label for="filter-location" class="sr-only">Location</label>
                            <select
                                id="filter-location"
                                v-model="selectedLocationId"
                                class="block w-full py-2 px-3 text-md rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500"
                                @change="applyTableFilters"
                            >
                                <option value="">All locations</option>
                                <option
                                    v-for="loc in locationOptions"
                                    :key="loc.id"
                                    :value="String(loc.id)"
                                >
                                    {{ loc.display_name }}
                                </option>
                            </select>
                        </div>

                        <!-- Status quick filter (same pattern as Table.vue) -->
                        <div class="relative shrink-0 w-full sm:w-auto" data-status-quick-filter>
                            <button
                                type="button"
                                @click.stop="toggleStatusFilterDropdown"
                                :class="[
                                    'inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-lg border transition-colors w-full sm:w-auto justify-center sm:justify-start',
                                    statusFilterButtonActive
                                        ? 'border-primary-400 bg-primary-50 dark:bg-primary-900/20 text-primary-700 dark:text-primary-300'
                                        : 'border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700',
                                ]"
                            >
                                Status
                                <span
                                    v-if="statusSelectionCount"
                                    class="px-1.5 py-0.5 text-[10px] font-semibold bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-400 rounded-full"
                                >
                                    {{ statusSelectionCount }}
                                </span>
                                <svg
                                    class="w-3.5 h-3.5 opacity-60 shrink-0"
                                    :class="openStatusFilter ? 'rotate-180' : ''"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>

                            <div
                                v-if="openStatusFilter"
                                class="absolute left-0 sm:right-0 sm:left-auto top-full mt-1.5 z-50 min-w-[200px] bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-lg overflow-hidden"
                            >
                                <div class="max-h-64 overflow-y-auto divide-y divide-gray-50 dark:divide-gray-700/60">
                                    <label
                                        v-for="opt in statusFilterOptions"
                                        :key="opt.id"
                                        class="flex items-center gap-2.5 px-3 py-2.5 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
                                    >
                                        <input
                                            type="checkbox"
                                            :value="opt.id"
                                            :checked="selectedStatuses.map(String).includes(String(opt.id))"
                                            @change="toggleStatusValue(opt.id)"
                                            class="w-4 h-4 rounded text-primary-600 border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-primary-500"
                                        />
                                        <div class="flex items-center gap-1.5 min-w-0">
                                            <span class="w-2 h-2 rounded-full shrink-0" :class="opt.dotClass" />
                                            <span class="text-sm text-gray-900 dark:text-white truncate">{{ opt.name }}</span>
                                        </div>
                                    </label>
                                </div>
                                <div
                                    v-if="statusSelectionCount"
                                    class="px-3 py-2 border-t border-gray-100 dark:border-gray-700/60"
                                >
                                    <button
                                        type="button"
                                        @click="clearStatusFilter"
                                        class="text-xs font-medium text-gray-500 dark:text-gray-400 hover:text-rose-600 dark:hover:text-rose-400 transition-colors"
                                    >
                                        Clear
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- New Delivery -->
                    <Link
                        :href="route('deliveries.create')"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-medium text-md flex-shrink-0"
                    >
                        <span class="material-icons text-lg">add</span>
                        <span>New Delivery</span>
                    </Link>
                </div>

                <!-- Active filter pills (same pattern as Table.vue) -->
                <div
                    v-if="showDeliveryFilterPills"
                    class="px-6 py-2.5 border-b border-gray-50 dark:border-gray-700/60 flex flex-wrap gap-1.5"
                >
                    <span
                        v-if="selectedSubsidiaryId"
                        class="inline-flex items-center gap-1 pl-2 pr-1 py-0.5 text-xs font-medium rounded-full bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-800 text-primary-700 dark:text-primary-400"
                    >
                        Subsidiary: {{ subsidiaryLabel(selectedSubsidiaryId) }}
                        <button
                            type="button"
                            @click="clearSubsidiaryFilter"
                            class="ml-0.5 p-0.5 rounded-full hover:bg-primary-100 dark:hover:bg-primary-800/50 transition-colors"
                            aria-label="Clear subsidiary filter"
                        >
                            <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </span>
                    <span
                        v-if="selectedLocationId"
                        class="inline-flex items-center gap-1 pl-2 pr-1 py-0.5 text-xs font-medium rounded-full bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-800 text-primary-700 dark:text-primary-400"
                    >
                        Location: {{ locationLabel(selectedLocationId) }}
                        <button
                            type="button"
                            @click="clearLocationFilter"
                            class="ml-0.5 p-0.5 rounded-full hover:bg-primary-100 dark:hover:bg-primary-800/50 transition-colors"
                            aria-label="Clear location filter"
                        >
                            <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </span>
                    <span
                        v-if="searchQuery?.trim()"
                        class="inline-flex items-center gap-1 pl-2 pr-1 py-0.5 text-xs font-medium rounded-full bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-800 text-primary-700 dark:text-primary-400"
                    >
                        Search: {{ searchQuery.trim() }}
                        <button
                            type="button"
                            @click="clearSearch"
                            class="ml-0.5 p-0.5 rounded-full hover:bg-primary-100 dark:hover:bg-primary-800/50 transition-colors"
                            aria-label="Clear search"
                        >
                            <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </span>
                    <span
                        v-for="sid in selectedStatuses"
                        :key="String(sid)"
                        class="inline-flex items-center gap-1 pl-2 pr-1 py-0.5 text-xs font-medium rounded-full bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-800 text-primary-700 dark:text-primary-400"
                    >
                        {{ getStatus(sid).label }}
                        <button
                            type="button"
                            @click="removeStatusPill(sid)"
                            class="ml-0.5 p-0.5 rounded-full hover:bg-primary-100 dark:hover:bg-primary-800/50 transition-colors"
                            :aria-label="'Remove ' + getStatus(sid).label + ' from status filter'"
                        >
                            <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </span>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-6 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Delivery #</th>
                                <th class="px-6 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Customer</th>
                                <th class="px-6 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Location</th>
                                <th class="px-6 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Assets</th>
                                <th class="px-6 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Technician</th>
                                <th class="px-6 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Scheduled</th>
                                <th class="px-6 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-center text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Signed</th>
                                <th class="px-6 py-3 text-right text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800">
                            <template v-for="delivery in (deliveries?.data ?? [])" :key="delivery.id">
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors border-b border-gray-200 dark:border-gray-700">
                                    <td class="px-6 py-4 whitespace-nowrap text-md font-mono text-gray-900 dark:text-white">{{ delivery.display_name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-md text-gray-800 dark:text-gray-200">{{ getCustomerName(delivery) }}</td>
                                    <td class="px-6 py-4 text-md text-gray-500 dark:text-gray-400 max-w-[10rem] truncate" :title="getLocationName(delivery)">
                                        {{ getLocationName(delivery) }}
                                    </td>
                                    <td class="px-6 py-4 text-md text-gray-600 dark:text-gray-300 max-w-[14rem]">
                                        <span class="font-medium">{{ itemsSummaryLabel(delivery) }}</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-md text-gray-500 dark:text-gray-400">{{ getTechnicianName(delivery) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-md text-gray-500 dark:text-gray-400">{{ formatDateTime(delivery.scheduled_at) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span :class="['inline-flex items-center gap-1 px-2 py-1 rounded text-sm font-medium', getStatus(delivery.status).bg]">
                                            <span class="material-icons text-sm">{{ getStatus(delivery.status).icon }}</span>
                                            {{ getStatus(delivery.status).label }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <span v-if="delivery.recipient_name" class="material-icons text-md text-green-500">verified</span>
                                        <span v-else class="text-gray-300 dark:text-gray-600">—</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-md">
                                        <Link :href="route('deliveries.show', delivery.id)" class="text-blue-600 dark:text-blue-400 hover:underline font-medium">View</Link>
                                    </td>
                                </tr>
                                <tr
                                    v-if="getDeliveryLineItems(delivery).length"
                                    class="bg-gray-50 dark:bg-gray-900/25 border-b border-gray-200 dark:border-gray-700"
                                >
                                    <td colspan="9" class="px-6 py-3 pl-8">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-1.5">Line items</p>
                                        <ul class="space-y-1.5 text-sm text-gray-700 dark:text-gray-200">
                                            <li
                                                v-for="(item, iidx) in getDeliveryLineItems(delivery)"
                                                :key="item.id ?? iidx"
                                                class="flex flex-wrap items-baseline gap-x-2"
                                            >
                                                <span class="font-medium text-gray-900 dark:text-white">{{ itemTitle(item) }}</span>
                                                <span v-if="itemUnitDetail(item)" class="text-gray-500 text-xs">· {{ itemUnitDetail(item) }}</span>
                                            </li>
                                        </ul>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="(deliveries?.data ?? []).length && deliveries?.total > 0" class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <div class="text-md text-gray-500 dark:text-gray-400">
                        Showing {{ (deliveries.current_page - 1) * deliveries.per_page + 1 }}–{{ Math.min(deliveries.current_page * deliveries.per_page, deliveries.total) }} of {{ deliveries.total }} results
                    </div>
                    <div class="flex gap-2">
                        <Link
                            v-if="deliveries.prev_page_url"
                            :href="deliveries.prev_page_url"
                            class="px-3 py-1.5 text-md font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600"
                        >
                            Previous
                        </Link>
                        <button v-else disabled class="px-3 py-1.5 text-md font-medium text-gray-400 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg cursor-not-allowed opacity-50">Previous</button>
                        <Link
                            v-if="deliveries.next_page_url"
                            :href="deliveries.next_page_url"
                            class="px-3 py-1.5 text-md font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600"
                        >
                            Next
                        </Link>
                        <button v-else disabled class="px-3 py-1.5 text-md font-medium text-gray-400 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg cursor-not-allowed opacity-50">Next</button>
                    </div>
                </div>
            </div>

        </div>

    </TenantLayout>
</template>
