<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Table from '@/Components/Tenant/Table.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import QuickBooksImport from '@/Components/Tenant/QuickBooksImport.vue';
import BulkActionsGearModal from '@/Components/Tenant/BulkActionsGearModal.vue';
import PeopleIndexRoleLinks from '@/Components/Tenant/PeopleIndexRoleLinks.vue';
import ApexPieChart from '@/Components/Charts/ApexPieChart.vue';
import ApexLineChart from '@/Components/Charts/ApexLineChart.vue';
import LeadPriorityKanban from '@/Components/Tenant/LeadPriorityKanban.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import axios from 'axios';

const MOBILE_MEDIA_QUERY = '(max-width: 767px)';

const props = defineProps({
    records: { type: Object, required: true },
    schema: { type: Object, default: null },
    formSchema: { type: Object, default: null },
    fieldsSchema: { type: Object, default: () => ({}) },
    enumOptions: { type: Object, default: () => ({}) },
    recordType: { type: String, default: 'leads' },
    recordTitle: { type: String, default: 'Lead' },
    pluralTitle: { type: String, default: 'Leads' },
    stats: { type: Object, default: () => ({}) },
    charts: { type: Object, default: () => ({}) },
    openLeads: { type: Array, default: () => [] },
    kanbanLeads: { type: Array, default: () => [] },
    assignableUsers: { type: Array, default: () => [] },
});

const currentView = ref(localStorage.getItem('lead-index-view') || 'dashboard');
const openLeadsLayout = ref(localStorage.getItem('lead-open-layout') || 'list');
const isMobileViewport = ref(false);
const updatingLeadId = ref(null);
const localOpenLeads = ref([]);
let mobileMediaQuery = null;

const effectiveOpenLeadsLayout = computed(() => {
    if (isMobileViewport.value && openLeadsLayout.value === 'kanban') {
        return 'list';
    }

    return openLeadsLayout.value;
});

onMounted(() => {
    mobileMediaQuery = window.matchMedia(MOBILE_MEDIA_QUERY);
    isMobileViewport.value = mobileMediaQuery.matches;
    mobileMediaQuery.addEventListener('change', onMobileViewportChange);
});

onUnmounted(() => {
    mobileMediaQuery?.removeEventListener('change', onMobileViewportChange);
});

function onMobileViewportChange(event) {
    isMobileViewport.value = event.matches;
}

watch(currentView, (view) => {
    localStorage.setItem('lead-index-view', view);
});

watch(openLeadsLayout, (layout) => {
    localStorage.setItem('lead-open-layout', layout);
});

watch(
    () => props.openLeads,
    (rows) => {
        localOpenLeads.value = Array.isArray(rows) ? rows.map((r) => ({ ...r })) : [];
    },
    { immediate: true, deep: true },
);

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: props.pluralTitle },
]);

const quickBooksImportRef = ref(null);

const bulkActions = computed(() => {
    const raw = props.schema?.settings?.bulk_actions;
    if (Array.isArray(raw) && raw.length) {
        return raw.filter((a) => a && typeof a.label === 'string' && typeof a.action === 'string');
    }
    return [];
});

const handleBulkAction = (item) => {
    if (item.action === 'importFromQuickbooks') {
        quickBooksImportRef.value?.openImportModal?.();
    }
};

const statCardDefs = computed(() => {
    const raw = props.schema?.stats;
    return Array.isArray(raw) ? raw.filter((s) => s.hidden !== true) : [];
});

const statNumericValue = (key) => {
    const n = Number(props.stats?.[key]);
    return Number.isFinite(n) ? n : 0;
};

const statBadgeClass = (color) => {
    const c = (color || 'gray').toString().toLowerCase();
    const m = {
        green: 'rounded-sm bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900 dark:text-green-300',
        red: 'rounded-sm bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800 dark:bg-red-900 dark:text-red-300',
        yellow: 'rounded-sm bg-yellow-100 px-2.5 py-0.5 text-xs font-medium text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
        amber: 'rounded-sm bg-yellow-100 px-2.5 py-0.5 text-xs font-medium text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
        gray: 'rounded-sm bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800 dark:bg-gray-700 dark:text-gray-300',
        blue: 'rounded-sm bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900 dark:text-blue-300',
        teal: 'rounded-sm bg-teal-100 px-2.5 py-0.5 text-xs font-medium text-teal-800 dark:bg-teal-900 dark:text-teal-300',
        purple: 'rounded-sm bg-purple-100 px-2.5 py-0.5 text-xs font-medium text-purple-800 dark:bg-purple-900 dark:text-purple-300',
    };
    return m[c] ?? m.gray;
};

const statusChart = computed(() => props.charts?.by_status ?? { labels: [], series: [], colors: [] });
const sourceChart = computed(() => props.charts?.by_source ?? { labels: [], series: [], colors: [] });
const trendChart = computed(() => props.charts?.created_trend ?? { categories: [], series: [], colors: [] });

const hasStatusChart = computed(() => statusChart.value.series?.some((n) => Number(n) > 0));
const hasSourceChart = computed(() => sourceChart.value.series?.some((n) => Number(n) > 0));
const hasTrendChart = computed(() => trendChart.value.categories?.length > 0);

const ENUM_LEAD_STATUS = 'App\\Enums\\Leads\\Status';
const ENUM_SOURCE = 'App\\Enums\\Entity\\Source';
const ENUM_PRIORITY = 'App\\Enums\\Entity\\Priority';

const ACTIVE_STATUS_IDS = [1, 2, 3];
const LEAD_STATUS_DISQUALIFIED = 5;

const statusOptions = computed(() => {
    const opts = props.enumOptions[ENUM_LEAD_STATUS] ?? [];

    return opts.filter((o) => ACTIVE_STATUS_IDS.includes(Number(o.id)));
});

const priorityOptions = computed(() => props.enumOptions[ENUM_PRIORITY] ?? []);

const activeLeadsFilterStatus = ref('');
const activeLeadsFilterPriority = ref('');
const activeLeadsFilterAssigned = ref('');

const hasActiveLeadsFilters = computed(() => (
    activeLeadsFilterStatus.value !== ''
    || activeLeadsFilterPriority.value !== ''
    || activeLeadsFilterAssigned.value !== ''
));

const filteredOpenLeads = computed(() => {
    let rows = localOpenLeads.value;

    if (activeLeadsFilterStatus.value !== '') {
        const statusId = Number(activeLeadsFilterStatus.value);
        rows = rows.filter((lead) => Number(lead.status_id) === statusId);
    }

    if (activeLeadsFilterPriority.value !== '') {
        if (activeLeadsFilterPriority.value === 'unset') {
            rows = rows.filter((lead) => lead.priority_id == null || lead.priority_id === '' || Number(lead.priority_id) === 0);
        } else {
            const priorityId = Number(activeLeadsFilterPriority.value);
            rows = rows.filter((lead) => Number(lead.priority_id) === priorityId);
        }
    }

    if (activeLeadsFilterAssigned.value !== '') {
        if (activeLeadsFilterAssigned.value === 'unassigned') {
            rows = rows.filter((lead) => lead.assigned_user_id == null || lead.assigned_user_id === '');
        } else {
            const userId = Number(activeLeadsFilterAssigned.value);
            rows = rows.filter((lead) => Number(lead.assigned_user_id) === userId);
        }
    }

    return rows;
});

function clearActiveLeadsFilters() {
    activeLeadsFilterStatus.value = '';
    activeLeadsFilterPriority.value = '';
    activeLeadsFilterAssigned.value = '';
}

const reloadKeys = ['openLeads', 'kanbanLeads', 'stats', 'charts', 'records'];

function normalizeLeadFieldValue(field, value) {
    if (value === '' || value === null || value === undefined) {
        return null;
    }
    if (field === 'next_followup_at') {
        return value;
    }

    return Number(value);
}

function syncAssignedUser(lead, userId) {
    if (!userId) {
        lead.assigned_user = null;

        return;
    }

    const match = props.assignableUsers.find((user) => Number(user.id) === Number(userId));
    lead.assigned_user = match
        ? { id: match.id, display_name: match.name }
        : null;
}

async function updateLeadField(lead, field, value) {
    const prev = lead[field];
    const prevAssigned = field === 'assigned_user_id' ? lead.assigned_user : null;
    const normalized = normalizeLeadFieldValue(field, value);
    lead[field] = normalized;
    if (field === 'assigned_user_id') {
        syncAssignedUser(lead, normalized);
    }
    updatingLeadId.value = lead.id;

    try {
        await axios.put(
            route(`${props.recordType}.update`, lead.id),
            { [field]: normalized },
            {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
            },
        );
        router.reload({ only: reloadKeys, preserveScroll: true });
    } catch {
        lead[field] = prev;
        if (field === 'assigned_user_id') {
            lead.assigned_user = prevAssigned;
        }
    } finally {
        updatingLeadId.value = null;
    }
}

function enumLabel(enumKey, id) {
    if (id == null || id === '') {
        return '—';
    }
    const opts = props.enumOptions[enumKey];
    if (!Array.isArray(opts)) {
        return '—';
    }
    const match = opts.find((o) => String(o.id) === String(id) || String(o.value) === String(id));
    return match?.name ?? '—';
}

function statusOptionColor(statusId) {
    const opts = props.enumOptions[ENUM_LEAD_STATUS] ?? [];
    const match = opts.find((o) => Number(o.id) === Number(statusId));

    return match?.color ?? 'gray';
}

function convertLead(lead) {
    if (!lead.contact_id || updatingLeadId.value === lead.id) {
        return;
    }

    updatingLeadId.value = lead.id;
    router.post(route(`${props.recordType}.convert`, lead.id), {}, {
        preserveScroll: true,
        only: reloadKeys,
        onFinish: () => {
            updatingLeadId.value = null;
        },
    });
}

function disqualifyLead(lead) {
    updateLeadField(lead, 'status_id', LEAD_STATUS_DISQUALIFIED);
}

function leadShowHref(id) {
    return route(`${props.recordType}.show`, id);
}

function switchToTable() {
    currentView.value = 'table';
}
</script>

<template>
    <Head :title="pluralTitle" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex flex-wrap items-center gap-3">
                        <PeopleIndexRoleLinks active-page="leads" />
                        <div class="flex overflow-hidden rounded-lg border border-gray-300 dark:border-gray-600">
                            <button
                                type="button"
                                :class="[
                                    'px-3 py-1.5 text-sm font-medium transition-colors',
                                    currentView === 'dashboard'
                                        ? 'bg-primary-600 text-white'
                                        : 'bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600',
                                ]"
                                @click="currentView = 'dashboard'"
                            >
                                Dashboard
                            </button>
                            <button
                                type="button"
                                :class="[
                                    'border-l border-gray-300 px-3 py-1.5 text-sm font-medium transition-colors dark:border-gray-600',
                                    currentView === 'table'
                                        ? 'bg-primary-600 text-white'
                                        : 'bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600',
                                ]"
                                @click="currentView = 'table'"
                            >
                                Table
                            </button>
                        </div>
                    </div>
                    <BulkActionsGearModal :actions="bulkActions" @action="handleBulkAction" />
                </div>
            </div>
        </template>

        <!-- Dashboard view -->
        <div v-if="currentView === 'dashboard'" class="space-y-6">
            <div
                v-if="statCardDefs.length"
                class="grid grid-cols-2 gap-4 lg:grid-cols-4"
            >
                <div
                    v-for="st in statCardDefs"
                    :key="st.key"
                    class="space-y-3 rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800"
                >
                    <span class="inline-block" :class="statBadgeClass(st.color)">
                        {{ st.badge_label ?? st.label ?? st.key }}
                    </span>
                    <h2 class="text-2xl font-bold leading-none text-gray-900 tabular-nums dark:text-white">
                        {{ statNumericValue(st.key) }}
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Active pipeline
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="text-base font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Active leads by status
                    </h3>
                    <div
                        v-if="hasStatusChart"
                        class="mt-4 flex flex-col items-center gap-4 sm:flex-row sm:justify-center"
                    >
                        <ApexPieChart
                            :labels="statusChart.labels"
                            :series="statusChart.series"
                            :colors="statusChart.colors"
                            :height="200"
                        />
                        <ul class="space-y-2 text-sm text-gray-600 dark:text-gray-300">
                            <li
                                v-for="(label, i) in statusChart.labels"
                                :key="label"
                                class="flex items-center gap-2"
                            >
                                <span
                                    class="h-2.5 w-2.5 shrink-0 rounded-full"
                                    :style="{ backgroundColor: statusChart.colors[i] }"
                                />
                                <span>{{ label }}</span>
                                <span class="font-semibold tabular-nums text-gray-900 dark:text-white">
                                    {{ statusChart.series[i] }}
                                </span>
                            </li>
                        </ul>
                    </div>
                    <p v-else class="py-12 text-center text-sm text-gray-400 dark:text-gray-500">
                        No active leads in the pipeline yet.
                    </p>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="text-base font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Leads by source
                    </h3>
                    <div
                        v-if="hasSourceChart"
                        class="mt-4 flex flex-col items-center gap-4 sm:flex-row sm:justify-center"
                    >
                        <ApexPieChart
                            :labels="sourceChart.labels"
                            :series="sourceChart.series"
                            :colors="sourceChart.colors"
                            :height="200"
                        />
                        <ul class="space-y-2 text-sm text-gray-600 dark:text-gray-300">
                            <li
                                v-for="(label, i) in sourceChart.labels"
                                :key="label"
                                class="flex items-center gap-2"
                            >
                                <span
                                    class="h-2.5 w-2.5 shrink-0 rounded-full"
                                    :style="{ backgroundColor: sourceChart.colors[i] }"
                                />
                                <span>{{ label }}</span>
                                <span class="font-semibold tabular-nums text-gray-900 dark:text-white">
                                    {{ sourceChart.series[i] }}
                                </span>
                            </li>
                        </ul>
                    </div>
                    <p v-else class="py-12 text-center text-sm text-gray-400 dark:text-gray-500">
                        No source data for active leads.
                    </p>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:col-span-2">
                    <h3 class="text-base font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        New leads trend
                    </h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Leads created per week — last 12 weeks.
                    </p>
                    <div class="mt-4">
                        <ApexLineChart
                            v-if="hasTrendChart"
                            :categories="trendChart.categories"
                            :series="trendChart.series"
                            :colors="trendChart.colors"
                            :height="280"
                            value-format="number"
                        />
                        <p v-else class="py-12 text-center text-sm text-gray-400 dark:text-gray-500">
                            No leads created in this period yet.
                        </p>
                    </div>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-4 dark:border-gray-700">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Active leads</h3>
                        <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                            Open, contacted, and qualified — sorted by next follow-up
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="hidden overflow-hidden rounded-lg border border-gray-300 md:flex dark:border-gray-600">
                            <button
                                type="button"
                                :class="[
                                    'px-2.5 py-1.5 text-xs font-medium transition-colors',
                                    effectiveOpenLeadsLayout === 'list'
                                        ? 'bg-primary-600 text-white'
                                        : 'bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300',
                                ]"
                                @click="openLeadsLayout = 'list'"
                            >
                                List
                            </button>
                            <button
                                type="button"
                                :class="[
                                    'border-l border-gray-300 px-2.5 py-1.5 text-xs font-medium transition-colors dark:border-gray-600',
                                    effectiveOpenLeadsLayout === 'kanban'
                                        ? 'bg-primary-600 text-white'
                                        : 'bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300',
                                ]"
                                @click="openLeadsLayout = 'kanban'"
                            >
                                Priority board
                            </button>
                        </div>
                        <button
                            type="button"
                            class="text-sm font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400"
                            @click="switchToTable"
                        >
                            View all in table
                        </button>
                    </div>
                </div>

                <div v-if="effectiveOpenLeadsLayout === 'kanban'">
                    <div v-if="kanbanLeads.length" class="p-4">
                        <LeadPriorityKanban
                            :leads="kanbanLeads"
                            :priority-options="priorityOptions"
                            :record-type="recordType"
                            :reload-only="reloadKeys"
                        />
                    </div>
                    <p v-else class="px-5 py-12 text-center text-sm text-gray-400 dark:text-gray-500">
                        No active leads right now.
                    </p>
                </div>

                <div v-else-if="effectiveOpenLeadsLayout === 'list'">
                    <div
                        v-if="localOpenLeads.length"
                        class="flex flex-wrap items-end gap-3 border-b border-gray-100 bg-gray-50/80 px-5 py-3 dark:border-gray-700 dark:bg-gray-900/30"
                    >
                        <div class="min-w-[8rem] flex-1 sm:flex-none">
                            <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Status
                            </label>
                            <select v-model="activeLeadsFilterStatus" class="input-style w-full py-1.5 text-xs">
                                <option value="">All statuses</option>
                                <option
                                    v-for="opt in statusOptions"
                                    :key="opt.id"
                                    :value="opt.id"
                                >
                                    {{ opt.name }}
                                </option>
                            </select>
                        </div>
                        <div class="min-w-[8rem] flex-1 sm:flex-none">
                            <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Priority
                            </label>
                            <select v-model="activeLeadsFilterPriority" class="input-style w-full py-1.5 text-xs">
                                <option value="">All priorities</option>
                                <option value="unset">Unset</option>
                                <option
                                    v-for="opt in priorityOptions"
                                    :key="opt.id"
                                    :value="opt.id"
                                >
                                    {{ opt.name }}
                                </option>
                            </select>
                        </div>
                        <div class="min-w-[8rem] flex-1 sm:flex-none">
                            <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Assigned
                            </label>
                            <select v-model="activeLeadsFilterAssigned" class="input-style w-full py-1.5 text-xs">
                                <option value="">All assignees</option>
                                <option value="unassigned">Unassigned</option>
                                <option
                                    v-for="user in assignableUsers"
                                    :key="user.id"
                                    :value="user.id"
                                >
                                    {{ user.name }}
                                </option>
                            </select>
                        </div>
                        <button
                            v-if="hasActiveLeadsFilters"
                            type="button"
                            class="text-xs font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400"
                            @click="clearActiveLeadsFilters"
                        >
                            Clear filters
                        </button>
                        <p
                            v-if="hasActiveLeadsFilters"
                            class="w-full text-xs text-gray-500 dark:text-gray-400 sm:ml-auto sm:w-auto"
                        >
                            {{ filteredOpenLeads.length }} of {{ localOpenLeads.length }} shown
                        </p>
                    </div>

                    <div v-if="filteredOpenLeads.length" class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/40">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Name</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Source</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Priority</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Assigned</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Next follow-up</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-gray-700/60">
                            <tr
                                v-for="lead in filteredOpenLeads"
                                :key="lead.id"
                                class="hover:bg-gray-50 dark:hover:bg-gray-700/30"
                                :class="updatingLeadId === lead.id ? 'opacity-60' : ''"
                            >
                                <td class="px-5 py-3">
                                    <Link
                                        :href="leadShowHref(lead.id)"
                                        class="font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400"
                                    >
                                        {{ lead.display_name || `Lead #${lead.id}` }}
                                    </Link>
                                    <p v-if="lead.email" class="text-xs text-gray-500 dark:text-gray-400">{{ lead.email }}</p>
                                </td>
                                <td class="px-5 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    {{ enumLabel(ENUM_SOURCE, lead.source_id) }}
                                </td>
                                <td class="px-5 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    <span
                                        class="inline-flex rounded-md px-2 py-0.5 text-xs font-medium"
                                        :class="statBadgeClass(statusOptionColor(lead.status_id))"
                                    >
                                        {{ enumLabel(ENUM_LEAD_STATUS, lead.status_id) }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    <select
                                        :value="lead.priority_id ?? ''"
                                        class="input-style min-w-[7rem] py-1.5 text-xs"
                                        :disabled="updatingLeadId === lead.id"
                                        @change="updateLeadField(lead, 'priority_id', $event.target.value)"
                                    >
                                        <option value="">Unset</option>
                                        <option
                                            v-for="opt in priorityOptions"
                                            :key="opt.id"
                                            :value="opt.id"
                                        >
                                            {{ opt.name }}
                                        </option>
                                    </select>
                                </td>
                                <td class="px-5 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    <select
                                        :value="lead.assigned_user_id ?? ''"
                                        class="input-style min-w-[8rem] py-1.5 text-xs"
                                        :disabled="updatingLeadId === lead.id"
                                        @change="updateLeadField(lead, 'assigned_user_id', $event.target.value)"
                                    >
                                        <option value="">Unassigned</option>
                                        <option
                                            v-for="user in assignableUsers"
                                            :key="user.id"
                                            :value="user.id"
                                        >
                                            {{ user.name }}
                                        </option>
                                    </select>
                                </td>
                                <td class="px-5 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    <input
                                        type="date"
                                        :value="lead.next_followup_at ?? ''"
                                        class="input-style min-w-[9rem] py-1.5 text-xs"
                                        :disabled="updatingLeadId === lead.id"
                                        @change="updateLeadField(lead, 'next_followup_at', $event.target.value)"
                                    />
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex min-w-[12rem] flex-col gap-2">
                                        <select
                                            :value="lead.status_id ?? ''"
                                            class="input-style w-full py-1.5 text-xs"
                                            :disabled="updatingLeadId === lead.id"
                                            @change="updateLeadField(lead, 'status_id', $event.target.value)"
                                        >
                                            <option
                                                v-for="opt in statusOptions"
                                                :key="opt.id"
                                                :value="opt.id"
                                            >
                                                {{ opt.name }}
                                            </option>
                                        </select>
                                        <div class="flex flex-wrap gap-1">
                                            <button
                                                type="button"
                                                class="rounded-md bg-purple-50 px-2 py-1 text-[11px] font-medium text-purple-700 hover:bg-purple-100 dark:bg-purple-900/30 dark:text-purple-300 disabled:cursor-not-allowed disabled:opacity-50"
                                                :disabled="updatingLeadId === lead.id || !lead.contact_id"
                                                :title="lead.contact_id ? 'Convert to customer' : 'Link a contact before converting'"
                                                @click="convertLead(lead)"
                                            >
                                                Convert
                                            </button>
                                            <button
                                                type="button"
                                                class="rounded-md bg-gray-100 px-2 py-1 text-[11px] font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                                                :disabled="updatingLeadId === lead.id"
                                                @click="disqualifyLead(lead)"
                                            >
                                                Disqualify
                                            </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    </div>
                    <p
                        v-else-if="localOpenLeads.length"
                        class="px-5 py-12 text-center text-sm text-gray-400 dark:text-gray-500"
                    >
                        No leads match your filters.
                        <button
                            type="button"
                            class="ml-1 font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400"
                            @click="clearActiveLeadsFilters"
                        >
                            Clear filters
                        </button>
                    </p>
                    <p
                        v-else
                        class="px-5 py-12 text-center text-sm text-gray-400 dark:text-gray-500"
                    >
                        No active leads right now.
                    </p>
                </div>
            </div>
        </div>

        <!-- Table view -->
        <Table
            v-else
            :records="records"
            :schema="schema"
            :form-schema="formSchema"
            :fields-schema="fieldsSchema"
            :enum-options="enumOptions"
            :record-type="recordType"
            :record-title="recordTitle"
            :plural-title="pluralTitle"
        />

        <QuickBooksImport ref="quickBooksImportRef" record-type="lead" />
    </TenantLayout>
</template>
