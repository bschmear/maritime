<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import WorkOrderCalendar from '@/Components/Tenant/WorkOrderCalendar.vue';
import WorkOrderKanbanBoard from '@/Components/Tenant/WorkOrderKanbanBoard.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const FILTERABLE_STATUS_IDS = [1, 2, 3, 4, 5, 6, 7];
const FILTERABLE_PRIORITY_IDS = [1, 2, 3, 4];

const parseInitialStatuses = (filters) => {
    const s = filters?.status;
    if (s === 'all') {
        return [];
    }
    if (Array.isArray(s) && s.length) {
        return s.map(String).filter((id) => FILTERABLE_STATUS_IDS.map(String).includes(id));
    }
    if (typeof s === 'string' && s !== '' && s !== 'all') {
        return FILTERABLE_STATUS_IDS.map(String).includes(String(s)) ? [String(s)] : [];
    }
    return [];
};

const parseInitialPriorities = (filters) => {
    const p = filters?.priority;
    if (p === 'all') {
        return [];
    }
    if (Array.isArray(p) && p.length) {
        return p.map(String).filter((id) => FILTERABLE_PRIORITY_IDS.map(String).includes(id));
    }
    if (typeof p === 'string' && p !== '' && p !== 'all') {
        return FILTERABLE_PRIORITY_IDS.map(String).includes(String(p)) ? [String(p)] : [];
    }
    return [];
};

const props = defineProps({
    records: {
        type: Object,
        required: true,
    },
    schema: {
        type: Object,
        default: null,
    },
    formSchema: {
        type: Object,
        default: null,
    },
    fieldsSchema: {
        type: Object,
        default: () => ({}),
    },
    enumOptions: {
        type: Object,
        default: () => ({}),
    },
    recordType: {
        type: String,
        default: 'workorders',
    },
    recordTitle: {
        type: String,
        default: 'Work Order',
    },
    pluralTitle: {
        type: String,
        default: 'Work Orders',
    },
    currentUser: {
        type: Object,
        required: true,
    },
    users: {
        type: Array,
        default: () => [],
    },
    stats: {
        type: Object,
        default: () => ({}),
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    kanbanRecords: {
        type: Array,
        default: () => [],
    },
    workOrderStatusOptions: {
        type: Array,
        default: () => [],
    },
});

const breadcrumbItems = computed(() => {
    return [
        { label: 'Home', href: route('dashboard') },
        { label: props.pluralTitle },
    ];
});

const ASSIGNED_USER_FILTER_KEY = 'workorder-assigned-user-filter';

function readInitialAssignedUserFilter() {
    const urlParams = new URLSearchParams(window.location.search);
    const fromUrl = urlParams.get('user');
    if (fromUrl !== null && fromUrl !== '') {
        return fromUrl;
    }
    const stored = localStorage.getItem(ASSIGNED_USER_FILTER_KEY);
    if (stored !== null && stored !== '') {
        return stored;
    }
    return props.filters?.user ?? 'all';
}

const selectedUser = ref(readInitialAssignedUserFilter());
const selectedStatuses = ref(parseInitialStatuses(props.filters));
const selectedPriorities = ref(parseInitialPriorities(props.filters));
const showClosed = ref(!!props.filters?.show_closed);
const showCancelled = ref(!!props.filters?.show_cancelled);
const viewMode = ref(localStorage.getItem('workorder-view') || 'cards');

const openStatusFilter = ref(false);
const openPriorityFilter = ref(false);

watch(
    () => props.filters,
    (f) => {
        selectedStatuses.value = parseInitialStatuses(f);
        selectedPriorities.value = parseInitialPriorities(f);
        showClosed.value = !!f?.show_closed;
        showCancelled.value = !!f?.show_cancelled;
    },
    { deep: true },
);

watch(viewMode, (mode) => {
    localStorage.setItem('workorder-view', mode);
    if (mode !== 'table' && (showClosed.value || showCancelled.value)) {
        showClosed.value = false;
        showCancelled.value = false;
        filterWorkOrders();
    }
});

watch(selectedUser, (value) => {
    localStorage.setItem(ASSIGNED_USER_FILTER_KEY, String(value));
});

const statusFilterOptions = computed(() =>
    (props.enumOptions['App\\Enums\\WorkOrder\\Status'] || []).filter((s) =>
        FILTERABLE_STATUS_IDS.includes(Number(s.id)),
    ),
);

const priorityFilterOptions = computed(() =>
    (props.enumOptions['App\\Enums\\WorkOrder\\Priority'] || []).filter((p) =>
        FILTERABLE_PRIORITY_IDS.includes(Number(p.id)),
    ),
);

const statusSelectionCount = computed(() => selectedStatuses.value.length);
const prioritySelectionCount = computed(() => selectedPriorities.value.length);
const statusFilterButtonActive = computed(() => selectedStatuses.value.length > 0);
const priorityFilterButtonActive = computed(() => selectedPriorities.value.length > 0);

const statusDotClass = (status) => {
    const map = {
        gray: 'bg-gray-500',
        blue: 'bg-blue-500',
        indigo: 'bg-indigo-500',
        yellow: 'bg-yellow-500',
        red: 'bg-red-500',
        green: 'bg-green-500',
        slate: 'bg-slate-500',
    };
    return map[status?.color] || map.gray;
};

const buildWorkOrdersIndexQueryParams = () => {
    const params = { user: selectedUser.value };

    const statuses = selectedStatuses.value.map(String);
    const allStatuses =
        statuses.length === 0
        || FILTERABLE_STATUS_IDS.every((id) => statuses.includes(String(id)));
    params.status = allStatuses ? 'all' : statuses;

    const priorities = selectedPriorities.value.map(String);
    const allPriorities =
        priorities.length === 0
        || FILTERABLE_PRIORITY_IDS.every((id) => priorities.includes(String(id)));
    params.priority = allPriorities ? 'all' : priorities;

    if (viewMode.value === 'table') {
        if (showClosed.value) {
            params.show_closed = 1;
        }
        if (showCancelled.value) {
            params.show_cancelled = 1;
        }
    }

    return params;
};

const filterWorkOrders = () => {
    localStorage.setItem(ASSIGNED_USER_FILTER_KEY, String(selectedUser.value));
    router.get(route('workorders.index'), buildWorkOrdersIndexQueryParams(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const toggleStatusFilterDropdown = () => {
    openStatusFilter.value = !openStatusFilter.value;
    if (openStatusFilter.value) {
        openPriorityFilter.value = false;
    }
};

const togglePriorityFilterDropdown = () => {
    openPriorityFilter.value = !openPriorityFilter.value;
    if (openPriorityFilter.value) {
        openStatusFilter.value = false;
    }
};

const toggleStatusValue = (statusId) => {
    const str = String(statusId);
    const cur = selectedStatuses.value.map(String);
    selectedStatuses.value = cur.includes(str) ? cur.filter((s) => s !== str) : [...cur, str];
    filterWorkOrders();
};

const togglePriorityValue = (priorityId) => {
    const str = String(priorityId);
    const cur = selectedPriorities.value.map(String);
    selectedPriorities.value = cur.includes(str) ? cur.filter((p) => p !== str) : [...cur, str];
    filterWorkOrders();
};

const clearStatusFilter = () => {
    selectedStatuses.value = [];
    filterWorkOrders();
};

const clearPriorityFilter = () => {
    selectedPriorities.value = [];
    filterWorkOrders();
};

const removeStatusPill = (statusId) => {
    selectedStatuses.value = selectedStatuses.value.map(String).filter((s) => s !== String(statusId));
    filterWorkOrders();
};

const removePriorityPill = (priorityId) => {
    selectedPriorities.value = selectedPriorities.value.map(String).filter((p) => p !== String(priorityId));
    filterWorkOrders();
};

const getStatusName = (statusId) =>
    statusFilterOptions.value.find((s) => String(s.id) === String(statusId))?.name ?? 'Status';

const getPriorityName = (priorityId) =>
    priorityFilterOptions.value.find((p) => String(p.id) === String(priorityId))?.name ?? 'Priority';

const onDocumentClick = (e) => {
    if (!e.target.closest('[data-wo-status-filter]')) {
        openStatusFilter.value = false;
    }
    if (!e.target.closest('[data-wo-priority-filter]')) {
        openPriorityFilter.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
    const urlUser = new URLSearchParams(window.location.search).get('user');
    const serverUser = String(props.filters?.user ?? 'all');
    if (urlUser === null && String(selectedUser.value) !== serverUser) {
        filterWorkOrders();
    }
});

onUnmounted(() => {
    document.removeEventListener('click', onDocumentClick);
});

const getStatusLabel = (statusId) => {
    const status = props.enumOptions['App\\Enums\\WorkOrder\\Status']?.find(s => s.id === statusId);
    return status?.name || 'Unknown';
};

const getPriorityLabel = (priorityId) => {
    const priority = props.enumOptions['App\\Enums\\WorkOrder\\Priority']?.find(p => p.id === priorityId);
    return priority?.name || 'Unknown';
};

const getTypeLabel = (typeId) => {
    const type = props.enumOptions['App\\Enums\\WorkOrder\\Type']?.find(t => t.id === typeId);
    return type?.name || 'Unknown';
};

const getPriorityBgClass = (priorityId) => {
    const priority = props.enumOptions['App\\Enums\\WorkOrder\\Priority']?.find(p => p.id === priorityId);
    return priority?.bgClass || 'bg-gray-200 dark:bg-gray-900 dark:text-white';
};

const getStatusBgClass = (statusId) => {
    const status = props.enumOptions['App\\Enums\\WorkOrder\\Status']?.find(s => s.id === statusId);
    return status?.bgClass || 'bg-gray-200 dark:bg-gray-900 dark:text-white';
};

const formatDate = (date) => {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
};

const formatDateTime = (date) => {
    if (!date) return '—';
    return new Date(date).toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
};

const isOverdue = (dueDate) => {
    if (!dueDate) return false;
    return new Date(dueDate) < new Date();
};
</script>

<template>
    <Head title="Work Orders" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
            </div>
        </template>

        <div class="space-y-6">
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <!-- Total Open -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Open Work Orders</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white mt-2">{{ stats.open || 0 }}</p>
                        </div>
                        <div class="h-12 w-12 rounded-lg bg-blue-100 dark:bg-blue-900/20 flex items-center justify-center">
                            <span class="material-icons text-2xl text-blue-600 dark:text-blue-400">assignment</span>
                        </div>
                    </div>
                </div>

                <!-- In Progress -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">In Progress</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white mt-2">{{ stats.in_progress || 0 }}</p>
                        </div>
                        <div class="h-12 w-12 rounded-lg bg-yellow-100 dark:bg-yellow-900/20 flex items-center justify-center">
                            <span class="material-icons text-2xl text-yellow-600 dark:text-yellow-400">pending_actions</span>
                        </div>
                    </div>
                </div>

                <!-- Overdue -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Overdue</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white mt-2">{{ stats.overdue || 0 }}</p>
                        </div>
                        <div class="h-12 w-12 rounded-lg bg-red-100 dark:bg-red-900/20 flex items-center justify-center">
                            <span class="material-icons text-2xl text-red-600 dark:text-red-400">warning</span>
                        </div>
                    </div>
                </div>

                <!-- Completed This Week -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Completed This Week</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white mt-2">{{ stats.completed_week || 0 }}</p>
                        </div>
                        <div class="h-12 w-12 rounded-lg bg-green-100 dark:bg-green-900/20 flex items-center justify-center">
                            <span class="material-icons text-2xl text-green-600 dark:text-green-400">check_circle</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
    <div class="flex flex-col gap-4">
        <!-- Filters Row -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <!-- User Filter -->
            <div class="w-full">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Assigned To
                </label>
                <select
                    v-model="selectedUser"
                    @change="filterWorkOrders"
                    class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >
                    <option value="all">All Users</option>
                    <option :value="currentUser?.id">{{ currentUser?.display_name }}</option>
                    <option v-for="user in users" :key="user.id" :value="user.id">
                        {{ user?.display_name || user?.email || `User ${user?.id}` }}
                    </option>
                </select>
            </div>

            <!-- Status multi-select -->
            <div class="w-full" data-wo-status-filter>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Status
                </label>
                <div class="relative">
                    <button
                        type="button"
                        @click.stop="toggleStatusFilterDropdown"
                        :class="[
                            'inline-flex w-full items-center justify-between gap-1.5 rounded-lg border px-3 py-2 text-sm font-medium transition-colors',
                            statusFilterButtonActive
                                ? 'border-primary-400 bg-primary-50 text-primary-700 dark:border-primary-600 dark:bg-primary-900/20 dark:text-primary-300'
                                : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600',
                        ]"
                    >
                        <span>Status</span>
                        <span class="flex items-center gap-1">
                            <span
                                v-if="statusSelectionCount"
                                class="rounded-full bg-primary-100 px-1.5 py-0.5 text-[10px] font-semibold text-primary-700 dark:bg-primary-900/30 dark:text-primary-400"
                            >
                                {{ statusSelectionCount }}
                            </span>
                            <svg
                                class="h-3.5 w-3.5 opacity-60"
                                :class="openStatusFilter ? 'rotate-180' : ''"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </span>
                    </button>
                    <div
                        v-if="openStatusFilter"
                        class="absolute left-0 top-full z-50 mt-1.5 max-h-64 w-full min-w-[200px] overflow-y-auto rounded-xl border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800"
                    >
                        <label
                            v-for="opt in statusFilterOptions"
                            :key="opt.id"
                            class="flex cursor-pointer items-center gap-2.5 px-3 py-2.5 hover:bg-gray-50 dark:hover:bg-gray-700/50"
                        >
                            <input
                                type="checkbox"
                                :checked="selectedStatuses.map(String).includes(String(opt.id))"
                                class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700"
                                @change="toggleStatusValue(opt.id)"
                            />
                            <span class="h-2 w-2 shrink-0 rounded-full" :class="statusDotClass(opt)" />
                            <span class="truncate text-sm text-gray-900 dark:text-white">{{ opt.name }}</span>
                        </label>
                        <div
                            v-if="statusSelectionCount"
                            class="border-t border-gray-100 px-3 py-2 dark:border-gray-700/60"
                        >
                            <button
                                type="button"
                                class="text-xs font-medium text-gray-500 hover:text-rose-600 dark:text-gray-400 dark:hover:text-rose-400"
                                @click="clearStatusFilter"
                            >
                                Clear
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Priority multi-select -->
            <div class="w-full" data-wo-priority-filter>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Priority
                </label>
                <div class="relative">
                    <button
                        type="button"
                        @click.stop="togglePriorityFilterDropdown"
                        :class="[
                            'inline-flex w-full items-center justify-between gap-1.5 rounded-lg border px-3 py-2 text-sm font-medium transition-colors',
                            priorityFilterButtonActive
                                ? 'border-primary-400 bg-primary-50 text-primary-700 dark:border-primary-600 dark:bg-primary-900/20 dark:text-primary-300'
                                : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600',
                        ]"
                    >
                        <span>Priority</span>
                        <span class="flex items-center gap-1">
                            <span
                                v-if="prioritySelectionCount"
                                class="rounded-full bg-primary-100 px-1.5 py-0.5 text-[10px] font-semibold text-primary-700 dark:bg-primary-900/30 dark:text-primary-400"
                            >
                                {{ prioritySelectionCount }}
                            </span>
                            <svg
                                class="h-3.5 w-3.5 opacity-60"
                                :class="openPriorityFilter ? 'rotate-180' : ''"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </span>
                    </button>
                    <div
                        v-if="openPriorityFilter"
                        class="absolute left-0 top-full z-50 mt-1.5 max-h-64 w-full min-w-[200px] overflow-y-auto rounded-xl border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800"
                    >
                        <label
                            v-for="opt in priorityFilterOptions"
                            :key="opt.id"
                            class="flex cursor-pointer items-center gap-2.5 px-3 py-2.5 hover:bg-gray-50 dark:hover:bg-gray-700/50"
                        >
                            <input
                                type="checkbox"
                                :checked="selectedPriorities.map(String).includes(String(opt.id))"
                                class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700"
                                @change="togglePriorityValue(opt.id)"
                            />
                            <span class="truncate text-sm text-gray-900 dark:text-white">{{ opt.name }}</span>
                        </label>
                        <div
                            v-if="prioritySelectionCount"
                            class="border-t border-gray-100 px-3 py-2 dark:border-gray-700/60"
                        >
                            <button
                                type="button"
                                class="text-xs font-medium text-gray-500 hover:text-rose-600 dark:text-gray-400 dark:hover:text-rose-400"
                                @click="clearPriorityFilter"
                            >
                                Clear
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Active filter pills -->
        <div
            v-if="statusSelectionCount || prioritySelectionCount"
            class="flex flex-wrap gap-1.5"
        >
            <span
                v-for="sid in selectedStatuses"
                :key="'status-' + sid"
                class="inline-flex items-center gap-1 rounded-full border border-primary-200 bg-primary-50 py-0.5 pl-2 pr-1 text-xs font-medium text-primary-700 dark:border-primary-800 dark:bg-primary-900/20 dark:text-primary-400"
            >
                {{ getStatusName(sid) }}
                <button
                    type="button"
                    class="rounded-full p-0.5 hover:bg-primary-100 dark:hover:bg-primary-800/50"
                    :aria-label="'Remove ' + getStatusName(sid)"
                    @click="removeStatusPill(sid)"
                >
                    <svg class="h-2.5 w-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </span>
            <span
                v-for="pid in selectedPriorities"
                :key="'priority-' + pid"
                class="inline-flex items-center gap-1 rounded-full border border-primary-200 bg-primary-50 py-0.5 pl-2 pr-1 text-xs font-medium text-primary-700 dark:border-primary-800 dark:bg-primary-900/20 dark:text-primary-400"
            >
                {{ getPriorityName(pid) }}
                <button
                    type="button"
                    class="rounded-full p-0.5 hover:bg-primary-100 dark:hover:bg-primary-800/50"
                    :aria-label="'Remove ' + getPriorityName(pid)"
                    @click="removePriorityPill(pid)"
                >
                    <svg class="h-2.5 w-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </span>
        </div>

        <!-- Table-only: closed / cancelled toggles -->
        <div v-if="viewMode === 'table'" class="flex flex-wrap items-center gap-4">
            <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <input
                    v-model="showClosed"
                    type="checkbox"
                    class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700"
                    @change="filterWorkOrders"
                />
                Show closed
            </label>
            <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <input
                    v-model="showCancelled"
                    type="checkbox"
                    class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700"
                    @change="filterWorkOrders"
                />
                Show cancelled
            </label>
        </div>

        <!-- Actions Row -->
        <div class="flex flex-col sm:flex-row gap-2 sm:justify-between sm:items-center">
            <!-- View Toggle -->
            <div class="flex rounded-lg border border-gray-300 dark:border-gray-600 overflow-hidden w-fit">

                <button
                    @click="viewMode = 'cards'"
                    :class="[
                        'px-3 py-2 text-sm font-medium transition-colors border-l border-gray-300 dark:border-gray-600',
                        viewMode === 'cards'
                            ? 'bg-blue-600 text-white'
                            : 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600'
                    ]"
                >
                    <span class="material-icons text-base">grid_view</span>
                </button>
                <button
                    @click="viewMode = 'table'"
                    :class="[
                        'px-3 py-2 text-sm font-medium transition-colors border-l border-gray-300 dark:border-gray-600',
                        viewMode === 'table'
                            ? 'bg-blue-600 text-white'
                            : 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600'
                    ]"
                >
                    <span class="material-icons text-base">view_list</span>
                </button>
                <button
                    @click="viewMode = 'calendar'"
                    :class="[
                        'px-3 py-2 text-sm font-medium transition-colors border-l border-gray-300 dark:border-gray-600',
                        viewMode === 'calendar'
                            ? 'bg-blue-600 text-white'
                            : 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600'
                    ]"
                >
                    <span class="material-icons text-base">calendar_view_month</span>
                </button>
                <button
                    type="button"
                    :class="[
                        'px-3 py-2 text-sm font-medium transition-colors border-l border-gray-300 dark:border-gray-600',
                        viewMode === 'kanban'
                            ? 'bg-blue-600 text-white'
                            : 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600',
                    ]"
                    @click="viewMode = 'kanban'"
                >
                    <span class="material-icons text-base">view_kanban</span>
                </button>
            </div>

            <!-- Create Button -->
            <a
                :href="route('workorders.create')"
                class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-medium text-sm"
            >
                <span class="material-icons text-base">add</span>
                <span>New Work Order</span>
            </a>
        </div>
    </div>
</div>
            <!-- Work Orders List - Calendar View -->
            <WorkOrderCalendar v-if="viewMode === 'calendar'" :work-orders="records.data" :enum-options="enumOptions" />

            <!-- Kanban View -->
            <WorkOrderKanbanBoard
                v-else-if="viewMode === 'kanban'"
                :work-orders="kanbanRecords"
                :status-options="workOrderStatusOptions.length ? workOrderStatusOptions : (enumOptions['App\\Enums\\WorkOrder\\Status'] || [])"
                :enum-options="enumOptions"
                :record-type="recordType"
            />

            <!-- Work Orders List - Card View -->
            <div v-else-if="viewMode === 'cards'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <a
                v-for="workOrder in records.data"
                    :key="workOrder.id"
                    :href="route('workorders.show', workOrder.id)"
                    class="group bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 hover:shadow-md hover:border-gray-300 dark:hover:border-gray-600 transition-all"
                >
                    <div class="p-4">
                        <!-- Header -->
                        <div class="flex items-start justify-between mb-3">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white truncate group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">
                                        {{ workOrder.display_name }}
                                    </h3>
                                    <span
                                        v-if="isOverdue(workOrder.due_at)"
                                        class="inline-flex shrink-0 items-center gap-1 px-2 py-0.5 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300 rounded text-xs font-medium"
                                    >
                                        <span class="material-icons text-xs">warning</span>
                                        Overdue
                                    </span>
                                </div>
                            </div>
                            <span class="material-icons text-gray-400 group-hover:translate-x-1 transition-transform">
                                chevron_right
                            </span>
                        </div>

                        <!-- Status & Priority -->
                        <div class="flex gap-2 mb-3">
                            <span
                                :class="[
                                    'inline-flex items-center px-2 py-1 rounded text-xs font-medium',
                                    getStatusBgClass(workOrder.status)
                                ]"
                            >
                                {{ getStatusLabel(workOrder.status) }}
                            </span>
                            <span
                                :class="[
                                    'inline-flex items-center px-2 py-1 rounded text-xs font-medium',
                                    getPriorityBgClass(workOrder.priority)
                                ]"
                            >
                                {{ getPriorityLabel(workOrder.priority) }}
                            </span>
                        </div>

                        <!-- Customer & Asset -->
                        <div class="space-y-2 mb-3">
                            <div class="flex items-start gap-2 text-sm">
                                <span class="material-icons text-base text-gray-400">person</span>
                                <span class="text-gray-600 dark:text-gray-300 truncate">
                                    {{ workOrder.customer?.display_name || '—' }}
                                </span>
                            </div>
                            <div v-if="workOrder.asset_unit" class="flex items-start gap-2 text-sm">
                                <span class="material-icons text-base text-gray-400">directions_boat</span>
                                <span class="text-gray-600 dark:text-gray-300 truncate">
                                    {{ workOrder.asset_unit?.display_name || '—' }}
                                </span>
                            </div>
                        </div>

                        <!-- Footer Info -->
                        <div class="pt-3 border-t border-gray-200 dark:border-gray-700 space-y-2">
                            <div class="flex items-center space-x-2 text-xs text-gray-500 dark:text-gray-400">
                                <div class="flex items-center gap-1">
                                    <span class="material-icons text-sm">schedule</span>
                                    <span class="font-medium">Scheduled:</span>
                                </div>
                                <span>{{ formatDate(workOrder.scheduled_start_at) }}</span>
                            </div>
                            <div class="flex items-center space-x-2 text-xs text-gray-500 dark:text-gray-400">
                                <div class="flex items-center gap-1">
                                    <span class="material-icons text-sm">event</span>
                                    <span class="font-medium">Due:</span>
                                </div>
                                <span>{{ formatDate(workOrder.due_at) }}</span>
                            </div>
                            <div class="flex items-center space-x-2 text-xs text-gray-500 dark:text-gray-400">
                                <div class="flex items-center gap-1">
                                    <span class="material-icons text-sm">person</span>
                                    <span class="font-medium">Assigned:</span>
                                </div>
                                <span class="truncate ml-2">{{ workOrder.assigned_user?.display_name || 'Unassigned' }}</span>
                            </div>
                        </div>
                    </div>
                </a>

                <!-- Empty State -->
                <div v-if="records.data.length === 0" class="col-span-full">
                    <div class="text-center py-12 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                        <span class="material-icons text-6xl text-gray-400 dark:text-gray-500 mb-4">assignment</span>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No work orders found</h3>
                        <p class="text-gray-500 dark:text-gray-400 mb-4">Get started by creating your first work order.</p>
                        <a
                            :href="route('workorders.create')"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                        >
                            <span class="material-icons text-base">add</span>
                            Create Work Order
                        </a>
                    </div>
                </div>
            </div>

            <!-- Work Orders List - Table View -->
            <div v-else-if="viewMode === 'table'" class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Work order</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Customer</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Priority</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Assigned To</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Due Date</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="workOrder in records.data" :key="workOrder.id" class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-900 dark:text-white">
                                    {{ workOrder.display_name }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ workOrder.customer?.display_name || '—' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span
                                        :class="[
                                            'inline-flex items-center px-2 py-1 rounded text-xs font-medium',
                                            getStatusBgClass(workOrder.status)
                                        ]"
                                    >
                                        {{ getStatusLabel(workOrder.status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span
                                        :class="[
                                            'inline-flex items-center px-2 py-1 rounded text-xs font-medium',
                                            getPriorityBgClass(workOrder.priority)
                                        ]"
                                    >
                                        {{ getPriorityLabel(workOrder.priority) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ workOrder.assigned_user?.display_name || 'Unassigned' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <span :class="isOverdue(workOrder.due_at) ? 'text-red-600 dark:text-red-400 font-medium' : 'text-gray-500 dark:text-gray-400'">
                                        {{ formatDate(workOrder.due_at || workOrder.scheduled_start_at || workOrder.created_at) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <a :href="route('workorders.show', workOrder.id)" class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300">
                                        View
                                    </a>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <nav
                    v-if="records.links?.length > 3"
                    class="flex flex-col items-center justify-between gap-3 border-t border-gray-200 px-6 py-3 dark:border-gray-700 sm:flex-row"
                >
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        Showing
                        <span class="font-semibold text-gray-900 dark:text-white">{{ records.from }}</span>
                        to
                        <span class="font-semibold text-gray-900 dark:text-white">{{ records.to }}</span>
                        of
                        <span class="font-semibold text-gray-900 dark:text-white">{{ records.total }}</span>
                    </span>
                    <div class="flex flex-wrap justify-center gap-1">
                        <template v-for="(link, i) in records.links" :key="i">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                class="flex items-center justify-center rounded-lg border px-3 py-1 text-sm transition-colors"
                                :class="link.active
                                    ? 'border-blue-600 bg-blue-600 text-white'
                                    : 'border-gray-200 text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700'"
                                v-html="link.label"
                            />
                            <span
                                v-else
                                class="flex items-center justify-center rounded-lg border border-gray-200 px-3 py-1 text-sm text-gray-400 dark:border-gray-600"
                                v-html="link.label"
                            />
                        </template>
                    </div>
                </nav>
            </div>
        </div>
    </TenantLayout>
</template>





<!-- <template>
    <Head title="Work Orders" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
            </div>
        </template>

        <Table :records="records" :schema="schema" :form-schema="formSchema" :fields-schema="fieldsSchema" :enum-options="enumOptions" :record-type="recordType" :record-title="recordTitle" :plural-title="pluralTitle" :create-modal="false" />
    </TenantLayout>
</template>
 -->
