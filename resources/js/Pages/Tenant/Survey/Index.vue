<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import UserInitialsAvatar from '@/Components/Tenant/UserInitialsAvatar.vue';
import SurveyStatusToggle from '@/Components/surveys/SurveyStatusToggle.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, onMounted, onBeforeUnmount } from 'vue';

function userDisplayName(user) {
    if (!user) return '';
    if (user.name != null && String(user.name).trim() !== '') return String(user.name).trim();
    return (
        (user.display_name && String(user.display_name).trim())
        || [user.first_name, user.last_name].filter(Boolean).join(' ').trim()
        || user.email
        || ''
    );
}

const props = defineProps({
    surveys:                 { type: Object,         required: true },
    totalResponsesThisMonth: { type: Number,          default: 0 },
    avgSatisfaction:         { type: Number,          default: 0 },
    topUsers:                { type: Object,          default: null },
    conversionRate:          { type: [Number, String], default: 0 },
    filterName:              { type: String,          default: '' },
    filterType:              { type: [String, Array], default: () => [] },
    filterStatus:            { type: [String, Array], default: () => [] },
    surveyTypes:             { type: Array,           default: () => [] },
    surveyStatuses:          { type: Array,           default: () => [] },
    users:                   { type: Array,           default: () => [] },
});

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Surveys' },
]);

// ── Selection state ──────────────────────────────────────────────────────────
const selectedIds    = ref([]);
const showDeleteModal  = ref(false);
const showSuccessModal = ref(false);
const sortCol = ref('');
const sortDir = ref('asc');

// ── Multi-select filter state ─────────────────────────────────────────────────
const normalizeArray = (val) => {
    if (!val) return [];
    return Array.isArray(val) ? val : [val];
};

const selectedTypes    = ref(normalizeArray(props.filterType));
const selectedStatuses = ref(normalizeArray(props.filterStatus));

const typeDropdownOpen   = ref(false);
const statusDropdownOpen = ref(false);
const typeDropdownRef    = ref(null);
const statusDropdownRef  = ref(null);

const toggleType = (value) => {
    selectedTypes.value.includes(value)
        ? (selectedTypes.value = selectedTypes.value.filter(v => v !== value))
        : selectedTypes.value.push(value);
    applyFilters();
};

const toggleStatus = (value) => {
    selectedStatuses.value.includes(value)
        ? (selectedStatuses.value = selectedStatuses.value.filter(v => v !== value))
        : selectedStatuses.value.push(value);
    applyFilters();
};

const removeType = (value) => {
    selectedTypes.value = selectedTypes.value.filter(v => v !== value);
    applyFilters();
};

const removeStatus = (value) => {
    selectedStatuses.value = selectedStatuses.value.filter(v => v !== value);
    applyFilters();
};

const applyFilters = () => {
    router.get(route('surveysIndex'), {
        ...(props.filterName   ? { n: props.filterName } : {}),
        ...(selectedTypes.value.length    ? { type:   selectedTypes.value }   : {}),
        ...(selectedStatuses.value.length ? { status: selectedStatuses.value } : {}),
    }, { preserveState: true });
};

const hasFilter = computed(() =>
    props.filterName || selectedTypes.value.length > 0 || selectedStatuses.value.length > 0
);

const clearFilters = () => {
    selectedTypes.value    = [];
    selectedStatuses.value = [];
    router.get(route('surveysIndex'));
};

// label helpers
const typeLabelFor   = (val) => props.surveyTypes.find(o => o.value === val)?.label ?? val;
const statusLabelFor = (val) => props.surveyStatuses.find(o => o.value === val)?.label ?? val;

// close dropdowns on outside click
const handleOutsideClick = (e) => {
    if (typeDropdownRef.value && !typeDropdownRef.value.contains(e.target))   typeDropdownOpen.value = false;
    if (statusDropdownRef.value && !statusDropdownRef.value.contains(e.target)) statusDropdownOpen.value = false;
};
onMounted(()       => document.addEventListener('mousedown', handleOutsideClick));
onBeforeUnmount(() => document.removeEventListener('mousedown', handleOutsideClick));

// ── Table helpers ─────────────────────────────────────────────────────────────
const statusDotClass = (status) => (status === true || status === 1) ? 'bg-green-500' : 'bg-gray-400';
const statusLabel    = (status) => (status === true || status === 1) ? 'Active' : 'Draft';

const toggleSelectAll = (e) => {
    selectedIds.value = e.target.checked ? props.surveys.data.map(s => s.id) : [];
};
const toggleSelect = (id) => {
    selectedIds.value.includes(id)
        ? (selectedIds.value = selectedIds.value.filter(i => i !== id))
        : selectedIds.value.push(id);
};

const deleteSelected = () => {
    router.post(route('surveysDeleteSelected'), { ids: selectedIds.value }, {
        onSuccess: () => {
            selectedIds.value    = [];
            showDeleteModal.value  = false;
            showSuccessModal.value = true;
        },
    });
};

const colSort = (col) => {
    sortDir.value = (sortCol.value === col && sortDir.value === 'asc') ? 'desc' : 'asc';
    sortCol.value = col;
    router.get(route('surveysIndex'), { sort: col, dir: sortDir.value }, { preserveState: true });
};
</script>

<template>
    <Head title="Surveys" />
    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
            </div>
        </template>

        <!-- Stats Cards -->
        <div class="grid gap-4 mb-6 sm:grid-cols-2 xl:grid-cols-4">
            <div class="flex items-center p-4 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm">
                <div class="flex items-center justify-center w-12 h-12 text-blue-600 dark:text-blue-300 bg-blue-100 dark:bg-blue-900 rounded-lg flex-shrink-0">
                    <span class="material-icons">forum</span>
                </div>
                <div class="flex-1 ms-4">
                    <h3 class="text-2xl font-bold text-gray-900 dark:text-white">{{ totalResponsesThisMonth.toLocaleString() }}</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total Responses This Month</p>
                </div>
            </div>

            <div class="flex items-center p-4 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm">
                <div class="flex items-center justify-center w-12 h-12 text-green-600 dark:text-green-300 bg-green-100 dark:bg-green-900 rounded-lg flex-shrink-0">
                    <span class="material-icons">star</span>
                </div>
                <div class="flex-1 ms-4">
                    <h3 class="text-2xl font-bold text-gray-900 dark:text-white">{{ avgSatisfaction.toFixed(1) }}/5.0</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Avg. Satisfaction Score</p>
                </div>
            </div>

            <div class="flex items-center p-4 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm">
                <div class="flex items-center justify-center w-12 h-12 text-purple-600 dark:text-purple-300 bg-purple-100 dark:bg-purple-900 rounded-lg flex-shrink-0">
                    <span class="material-icons">manage_accounts</span>
                </div>
                <div class="flex-1 ms-4">
                    <h3 class="text-2xl font-bold text-gray-900 dark:text-white">{{ topUsers?.name ?? '—' }}</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Most Surveys Created</p>
                </div>
            </div>

            <div class="flex items-center p-4 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm">
                <div class="flex items-center justify-center w-12 h-12 text-yellow-600 dark:text-yellow-300 bg-yellow-100 dark:bg-yellow-900 rounded-lg flex-shrink-0">
                    <span class="material-icons">trending_up</span>
                </div>
                <div class="flex-1 ms-4">
                    <h3 class="text-2xl font-bold text-gray-900 dark:text-white">{{ conversionRate }}%</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Conversion Rate</p>
                </div>
            </div>
        </div>

        <!-- Main Table Container -->
        <div class="relative bg-white dark:bg-gray-800 sm:rounded-lg overflow-hidden flex flex-col"
            :class="surveys.data.length > 0 ? 'shadow-md' : ''">

            <div class="px-4">
                <div class="border-b border-gray-200 dark:border-gray-700 space-y-4">

                    <!-- Top Bar -->
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between pt-4 pb-4 border-b border-gray-200 dark:border-gray-700">
                        <h5 class="text-lg font-semibold text-gray-900 dark:text-white">Surveys</h5>

                        <div class="flex flex-col sm:flex-row sm:items-center gap-2 mt-3 sm:mt-0">
                            <button v-if="selectedIds.length > 0"
                                class="red-button sm flex items-center justify-center gap-2"
                                @click="showDeleteModal = true">
                                <span class="material-icons text-base leading-none">delete_outline</span>
                                <span>Delete selected</span>
                            </button>

                            <a :href="route('surveyResponses')" class="btn btn-outline sm flex items-center justify-center gap-2">
                                <span class="material-icons text-base leading-none">comment</span>
                                <span>View All Responses</span>
                            </a>

                            <a :href="route('surveysCreate')" class="btn btn-primary sm flex items-center justify-center gap-2">
                                <span class="material-icons text-base leading-none">add</span>
                                <span>Create New Survey</span>
                            </a>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="flex flex-col pb-4 gap-3">
                        <div class="w-full flex flex-col gap-3 lg:flex-row lg:items-center">

                            <!-- Search -->
                            <input
                                type="text"
                                :value="filterName"
                                placeholder="Search surveys..."
                                class="input-style max-w-96"
                                @input="e => router.get(route('surveysIndex'), { n: e.target.value }, { preserveState: true })"
                            />

                            <div class="flex flex-wrap items-center gap-2 w-full">

                                <!-- Type multi-select dropdown -->
                                <div class="relative" ref="typeDropdownRef">
                                    <button
                                        @click="typeDropdownOpen = !typeDropdownOpen"
                                        class="btn btn-outline sm inline-flex items-center gap-1.5 min-w-32"
                                        :class="selectedTypes.length > 0 ? 'border-primary-500 text-primary-600 dark:text-primary-400' : ''"
                                    >
                                        <span>Type</span>
                                        <span v-if="selectedTypes.length > 0"
                                            class="inline-flex items-center justify-center w-5 h-5 text-sm font-semibold rounded-full bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-300">
                                            {{ selectedTypes.length }}
                                        </span>
                                        <span class="material-icons text-base leading-none ml-auto">
                                            {{ typeDropdownOpen ? 'expand_less' : 'expand_more' }}
                                        </span>
                                    </button>

                                    <div v-if="typeDropdownOpen"
                                        class="absolute left-0 top-full mt-1 z-20 w-48 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg py-1">
                                        <label
                                            v-for="opt in surveyTypes"
                                            :key="opt.value"
                                            class="flex items-center gap-3 px-3 py-2 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
                                        >
                                            <input
                                                type="checkbox"
                                                :value="opt.value"
                                                :checked="selectedTypes.includes(opt.value)"
                                                @change="toggleType(opt.value)"
                                                class="w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-primary-600"
                                            />
                                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ opt.label ?? opt.name }}</span>
                                        </label>
                                    </div>
                                </div>

                                <!-- Status multi-select dropdown -->
                                <div class="relative" ref="statusDropdownRef">
                                    <button
                                        @click="statusDropdownOpen = !statusDropdownOpen"
                                        class="btn btn-outline sm inline-flex items-center gap-1.5 min-w-32"
                                        :class="selectedStatuses.length > 0 ? 'border-primary-500 text-primary-600 dark:text-primary-400' : ''"
                                    >
                                        <span>Status</span>
                                        <span v-if="selectedStatuses.length > 0"
                                            class="inline-flex items-center justify-center w-5 h-5 text-sm font-semibold rounded-full bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-300">
                                            {{ selectedStatuses.length }}
                                        </span>
                                        <span class="material-icons text-base leading-none ml-auto">
                                            {{ statusDropdownOpen ? 'expand_less' : 'expand_more' }}
                                        </span>
                                    </button>

                                    <div v-if="statusDropdownOpen"
                                        class="absolute left-0 top-full mt-1 z-20 w-48 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg py-1">
                                        <label
                                            v-for="opt in surveyStatuses"
                                            :key="opt.value"
                                            class="flex items-center gap-3 px-3 py-2 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
                                        >
                                            <input
                                                type="checkbox"
                                                :value="opt.value"
                                                :checked="selectedStatuses.includes(opt.value)"
                                                @change="toggleStatus(opt.value)"
                                                class="w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-primary-600"
                                            />
                                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ opt.label ?? opt.name }}</span>
                                        </label>
                                    </div>
                                </div>

                                <!-- User filter (single select, kept as-is) -->
                                <select
                                    v-if="users.length > 1"
                                    class="input-style max-w-48"
                                    @change="e => router.get(route('surveysIndex'), { user: e.target.value }, { preserveState: true })"
                                >
                                    <option value="0">All Users</option>
                                    <option v-for="u in users" :key="u.id" :value="u.id">
                                        {{ userDisplayName(u) }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <!-- Active filter pills -->
                        <div v-if="hasFilter" class="flex flex-wrap items-center gap-2 min-h-6">
                            <span class="text-sm text-gray-500 dark:text-gray-400">Filters:</span>

                            <span
                                v-for="val in selectedTypes"
                                :key="`type-${val}`"
                                class="inline-flex items-center gap-1 pl-2.5 pr-1 py-0.5 rounded-full text-sm font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300"
                            >
                                {{ typeLabelFor(val) }}
                                <button @click="removeType(val)" class="flex items-center rounded-full hover:bg-blue-200 dark:hover:bg-blue-800 p-0.5 transition-colors">
                                    <span class="material-icons text-base leading-none">close</span>
                                </button>
                            </span>

                            <span
                                v-for="val in selectedStatuses"
                                :key="`status-${val}`"
                                class="inline-flex items-center gap-1 pl-2.5 pr-1 py-0.5 rounded-full text-sm font-medium bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300"
                            >
                                {{ statusLabelFor(val) }}
                                <button @click="removeStatus(val)" class="flex items-center rounded-full hover:bg-green-200 dark:hover:bg-green-800 p-0.5 transition-colors">
                                    <span class="material-icons text-base leading-none">close</span>
                                </button>
                            </span>

                            <button @click="clearFilters" class="text-sm text-gray-500 dark:text-gray-400 hover:text-red-600 dark:hover:text-red-400 transition-colors ml-1">
                                Clear all
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Table / Empty State -->
            <div class="overflow-x-auto grow flex flex-col">

                <div v-if="surveys.data.length === 0"
                    class="bg-gray-50 dark:bg-gray-900 flex items-center justify-center grow">
                    <div class="text-center flex flex-col items-center p-8">
                        <template v-if="hasFilter">
                            <span class="material-icons text-5xl text-gray-400 dark:text-gray-500 mb-4">filter_list_off</span>
                            <h3 class="mb-4 text-lg font-bold text-gray-900 dark:text-white">No surveys match your filters</h3>
                            <button @click="clearFilters" class="btn-link">Clear Filters</button>
                        </template>
                        <template v-else>
                            <span class="material-icons text-5xl text-gray-400 dark:text-gray-500 mb-4">poll</span>
                            <h3 class="mb-3 text-lg font-bold text-gray-900 dark:text-white">No surveys yet</h3>
                            <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">Create your first survey to start collecting feedback and data.</p>
                            <a :href="route('surveysCreate')" class="btn btn-primary inline-flex items-center gap-2">
                                <span class="material-icons text-base leading-none">add</span>
                                Create Your First Survey
                            </a>
                        </template>
                    </div>
                </div>

                <table v-else class="w-full text-sm text-left text-gray-600 dark:text-gray-300">
                    <thead class="text-sm font-semibold text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                        <tr>
                            <th class="px-4 py-3 w-10">
                                <input type="checkbox" @change="toggleSelectAll" class="rounded" />
                            </th>
                            <th class="px-4 py-3 whitespace-nowrap cursor-pointer select-none" @click="colSort('title')">
                                <div class="flex items-center gap-1">
                                    Survey Name
                                    <span class="material-icons text-base leading-none text-gray-400">swap_vert</span>
                                </div>
                            </th>
                            <th class="px-4 py-3 whitespace-nowrap cursor-pointer select-none" @click="colSort('type')">
                                <div class="flex items-center gap-1">
                                    Type
                                    <span class="material-icons text-base leading-none text-gray-400">swap_vert</span>
                                </div>
                            </th>
                            <th class="px-4 py-3 whitespace-nowrap cursor-pointer select-none" @click="colSort('user_id')">
                                <div class="flex items-center gap-1">
                                    Created By
                                    <span class="material-icons text-base leading-none text-gray-400">swap_vert</span>
                                </div>
                            </th>
                            <th class="px-4 py-3 whitespace-nowrap">Responses</th>
                            <th class="px-4 py-3 whitespace-nowrap cursor-pointer select-none" @click="colSort('status')">
                                <div class="flex items-center gap-1">
                                    Status
                                    <span class="material-icons text-base leading-none text-gray-400">swap_vert</span>
                                </div>
                            </th>
                            <th class="px-4 py-3 whitespace-nowrap">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        <tr v-for="item in surveys.data" :key="item.id"
                            class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <td class="px-4 py-3">
                                <input type="checkbox" :checked="selectedIds.includes(item.id)"
                                    @change="toggleSelect(item.id)" class="rounded" />
                            </td>
                            <td class="px-4 py-3">
                                <a :href="`${route('surveysShow')}?id=${item.uuid}`"
                                    class="font-medium text-gray-900 dark:text-white hover:underline underline-offset-2">
                                    {{ item.title }}
                                </a>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                {{ props.surveyTypes.find(o => o.value === item.type)?.label ?? item.type }}
                            </td>
                            <td class="px-4 py-3">
                                <UserInitialsAvatar :name="userDisplayName(item.user)" small />
                            </td>
                            <td class="px-4 py-3 font-semibold text-gray-900 dark:text-white">
                                {{ item.responses_count ?? 0 }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <div class="w-2.5 h-2.5 rounded-full flex-shrink-0" :class="statusDotClass(item.status)"></div>
                                    <span>{{ statusLabel(item.status) }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <a :href="`${route('surveyResponsesByUuid')}?id=${item.uuid}`"
                                        class="inline-flex items-center gap-1 text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 transition-colors"
                                        title="View Responses">
                                        <span class="material-icons text-base leading-none">comment</span>
                                        <span>{{ item.responses_count ?? 0 }}</span>
                                    </a>

                                    <SurveyStatusToggle
                                        :small="true"
                                        :status="item.status === true || item.status === 1"
                                        :updateroute="`${route('surveysUpdate')}?id=${item.uuid}`"
                                    />
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="surveys.last_page > 1" class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        Showing {{ surveys.from }}–{{ surveys.to }} of {{ surveys.total }}
                    </span>
                    <div class="flex gap-1">
                        <template v-for="link in surveys.links" :key="link.label">
                            <button v-if="link.url"
                                class="px-3 py-1 text-sm rounded border transition-colors"
                                :class="link.active
                                    ? 'bg-primary-600 text-white border-primary-600'
                                    : 'border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'"
                                @click="router.get(link.url, {}, { preserveState: true })"
                                v-html="link.label"
                            />
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modals -->
        <Teleport to="body">
            <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center">
                <div class="absolute inset-0 bg-black/50 dark:bg-black/70" @click="showDeleteModal = false" />
                <div class="relative bg-white dark:bg-gray-800 rounded-xl shadow-xl p-6 w-full max-w-md text-center z-10 border border-gray-200 dark:border-gray-700">
                    <button class="absolute top-3 right-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 rounded-lg p-1 transition-colors"
                        @click="showDeleteModal = false">
                        <span class="material-icons text-xl">close</span>
                    </button>
                    <span class="material-icons text-5xl text-gray-400 dark:text-gray-500 mb-3">help_outline</span>
                    <h3 class="mb-2 text-lg font-semibold text-gray-900 dark:text-white">Delete selected surveys?</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">This action cannot be undone.</p>
                    <div class="flex justify-center gap-3">
                        <button @click="deleteSelected"
                            class="px-5 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors">
                            Yes, delete
                        </button>
                        <button @click="showDeleteModal = false"
                            class="px-5 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 rounded-lg transition-colors">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>

            <div v-if="showSuccessModal" class="fixed inset-0 z-50 flex items-center justify-center">
                <div class="absolute inset-0 bg-black/50 dark:bg-black/70" @click="showSuccessModal = false" />
                <div class="relative bg-white dark:bg-gray-800 rounded-xl shadow-xl p-6 w-full max-w-md text-center z-10 border border-gray-200 dark:border-gray-700">
                    <button class="absolute top-3 right-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 rounded-lg p-1 transition-colors"
                        @click="showSuccessModal = false">
                        <span class="material-icons text-xl">close</span>
                    </button>
                    <div class="w-14 h-14 rounded-full bg-green-100 dark:bg-green-900/40 flex items-center justify-center mx-auto mb-4">
                        <span class="material-icons text-3xl text-green-600 dark:text-green-400">check_circle</span>
                    </div>
                    <p class="text-lg font-semibold text-gray-900 dark:text-white mb-5">Survey(s) deleted successfully.</p>
                    <button @click="showSuccessModal = false"
                        class="px-5 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors">
                        Continue
                    </button>
                </div>
            </div>
        </Teleport>
    </TenantLayout>
</template>