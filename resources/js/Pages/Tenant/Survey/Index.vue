<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    surveys: {
        type: Object,
        required: true,
    },
    totalResponsesThisMonth: {
        type: Number,
        default: 0,
    },
    avgSatisfaction: {
        type: Number,
        default: 0,
    },
    topAgentName: {
        type: String,
        default: '—',
    },
    conversionRate: {
        type: [Number, String],
        default: 0,
    },
    filterName: {
        type: String,
        default: '',
    },
    filterType: {
        type: String,
        default: '',
    },
    filterStatus: {
        type: String,
        default: 'active',
    },
    surveyTypes: {
        type: Array,
        default: () => [],
    },
    surveyStatuses: {
        type: Array,
        default: () => [],
    },
    teamUsers: {
        type: Array,
        default: () => [],
    },
});

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Surveys' },
]);

const selectedIds = ref([]);
const showDeleteModal = ref(false);
const showSuccessModal = ref(false);
const sortCol = ref('');
const sortDir = ref('asc');

const typeLabel = (type) => {
    const map = { lead: 'Lead', feedback: 'Feedback', followup: 'Follow Up', custom: 'Custom' };
    return map[type] ?? 'Custom';
};

const statusClass = (status) => {
    if (status === 'active' || status === true || status === 1) return 'bg-green-500';
    if (status === 'draft') return 'bg-blue-500';
    return 'bg-red-500';
};

const statusLabel = (status) => {
    if (status === 'active' || status === true || status === 1) return 'Active';
    if (status === 'draft') return 'Draft';
    return 'Inactive';
};

const toggleSelectAll = (e) => {
    selectedIds.value = e.target.checked
        ? props.surveys.data.map((s) => s.id)
        : [];
};

const toggleSelect = (id) => {
    selectedIds.value.includes(id)
        ? (selectedIds.value = selectedIds.value.filter((i) => i !== id))
        : selectedIds.value.push(id);
};

const deleteSelected = () => {
    router.delete(route('surveysIndex'), {
        data: { ids: selectedIds.value },
        onSuccess: () => {
            selectedIds.value = [];
            showDeleteModal.value = false;
            showSuccessModal.value = true;
        },
    });
};

const colSort = (col) => {
    if (sortCol.value === col) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortCol.value = col;
        sortDir.value = 'asc';
    }
    router.get(route('surveysIndex'), { sort: col, dir: sortDir.value }, { preserveState: true });
};

const hasFilter = computed(() => props.filterName || props.filterType || (props.filterStatus && props.filterStatus !== 'active'));

const clearFilters = () => {
    router.get(route('surveysIndex'));
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

        <div class="grid gap-4 mb-6 sm:grid-cols-2 xl:grid-cols-4">
            <div class="flex items-center p-4 bg-white border border-gray-200 rounded-lg shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-center justify-center w-12 h-12 text-blue-600 bg-blue-100 rounded-lg dark:bg-blue-900 dark:text-blue-300 flex-shrink-0">
                    <i class="fas fa-comments text-xl"></i>
                </div>
                <div class="flex-1 ms-4">
                    <h3 class="text-2xl font-bold text-gray-900 dark:text-white">{{ totalResponsesThisMonth.toLocaleString() }}</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total Responses This Month</p>
                </div>
            </div>

            <div class="flex items-center p-4 bg-white border border-gray-200 rounded-lg shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-center justify-center w-12 h-12 text-green-600 bg-green-100 rounded-lg dark:bg-green-900 dark:text-green-300 flex-shrink-0">
                    <i class="fas fa-star text-xl"></i>
                </div>
                <div class="flex-1 ms-4">
                    <h3 class="text-2xl font-bold text-gray-900 dark:text-white">{{ avgSatisfaction.toFixed(1) }}/5.0</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Avg. Satisfaction Score</p>
                </div>
            </div>

            <div class="flex items-center p-4 bg-white border border-gray-200 rounded-lg shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-center justify-center w-12 h-12 text-purple-600 bg-purple-100 rounded-lg dark:bg-purple-900 dark:text-purple-300 flex-shrink-0">
                    <i class="fas fa-user-tie text-xl"></i>
                </div>
                <div class="flex-1 ms-4">
                    <h3 class="text-2xl font-bold text-gray-900 dark:text-white">{{ topAgentName }}</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Top Performing Agent</p>
                </div>
            </div>

            <div class="flex items-center p-4 bg-white border border-gray-200 rounded-lg shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-center justify-center w-12 h-12 text-yellow-600 bg-yellow-100 rounded-lg dark:bg-yellow-900 dark:text-yellow-300 flex-shrink-0">
                    <i class="fas fa-chart-line text-xl"></i>
                </div>
                <div class="flex-1 ms-4">
                    <h3 class="text-2xl font-bold text-gray-900 dark:text-white">{{ conversionRate }}%</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Conversion Rate</p>
                </div>
            </div>
        </div>

        <div class="relative bg-white dark:bg-gray-800 sm:rounded-lg overflow-hidden flex flex-col"
            :class="surveys.data.length > 0 ? 'shadow-md' : ''">
            <div class="px-4">
                <div class="border-b dark:border-gray-700 space-y-4">

                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between pt-4 pb-4 border-b dark:border-gray-700">
                        <h5 class="text-lg font-semibold text-gray-900 dark:text-white">Surveys</h5>

                        <div class="flex flex-col sm:flex-row sm:items-center sm:space-x-2 gap-2 mt-3 sm:mt-0">
                            <button v-if="selectedIds.length > 0"
                                class="red-button sm flex items-center justify-center space-x-2"
                                @click="showDeleteModal = true">
                                <i class="far fa-trash-alt"></i>
                                <span>Delete selected</span>
                            </button>

                            <a :href="route('surveyResponses')"
                                class="btn btn-outline sm flex items-center justify-center space-x-2">
                                <i class="fas fa-comments"></i>
                                <span>View All Responses</span>
                            </a>

                            <a :href="route('surveysCreate')"
                                class="btn btn-primary sm flex items-center justify-center space-x-2">
                                <i class="far fa-plus"></i>
                                <span>Create New Survey</span>
                            </a>
                        </div>
                    </div>

                    <div class="flex flex-col pb-4 space-y-3">
                        <div class="w-full flex flex-col space-y-3 lg:space-y-0 lg:flex-row lg:items-center">

                            <input
                                type="text"
                                :value="filterName"
                                placeholder="Search surveys..."
                                class="form-input"
                                @input="e => router.get(route('surveysIndex'), { n: e.target.value }, { preserveState: true })"
                            />

                            <div class="flex flex-wrap items-center gap-2 ml-2">
                                <template v-for="opt in surveyTypes" :key="opt.value">
                                    <button
                                        class="btn sm"
                                        :class="filterType === opt.value ? 'btn-primary' : 'btn-outline'"
                                        @click="router.get(route('surveysIndex'), { type: opt.value }, { preserveState: true })">
                                        {{ opt.label }}
                                    </button>
                                </template>

                                <select v-if="teamUsers.length > 1"
                                    class="form-select sm"
                                    @change="e => router.get(route('surveysIndex'), { user: e.target.value }, { preserveState: true })">
                                    <option value="0">All Users</option>
                                    <option v-for="u in teamUsers" :key="u.id" :value="u.id">{{ u.name }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 mx-4">
                            <template v-for="opt in surveyStatuses" :key="opt.value">
                                <button
                                    class="btn sm"
                                    :class="filterStatus === opt.value ? 'btn-primary' : 'btn-outline'"
                                    @click="router.get(route('surveysIndex'), { status: opt.value }, { preserveState: true })">
                                    {{ opt.label }}
                                </button>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="hasFilter" class="flex items-center justify-between px-4 py-3">
                <span class="text-sm text-gray-500 dark:text-gray-400">Filters active</span>
                <button class="link warning text-sm" @click="clearFilters">Clear filters</button>
            </div>

            <div class="overflow-x-auto grow flex flex-col">

                <div v-if="surveys.data.length === 0"
                    class="text-gray-700 bg-gray-50 dark:bg-gray-900 dark:text-gray-400 flex items-center justify-center grow">
                    <div class="text-center flex flex-col items-center p-8">
                        <template v-if="hasFilter">
                            <svg class="w-12 h-12 text-gray-400 dark:text-gray-500 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                            </svg>
                            <h3 class="mb-4 text-lg font-bold text-gray-900 dark:text-white">No surveys match your filters</h3>
                            <button @click="clearFilters" class="btn-link">Clear Filters</button>
                        </template>
                        <template v-else>
                            <i class="fas fa-poll-h text-4xl text-gray-400 dark:text-gray-500 mb-4"></i>
                            <h3 class="mb-4 text-lg font-bold text-gray-900 dark:text-white">No surveys yet</h3>
                            <p class="mb-4 text-gray-500 dark:text-gray-400">Create your first survey to start collecting feedback and data.</p>
                            <a :href="route('surveysCreate')" class="btn btn-primary inline-flex items-center">
                                <i class="fas fa-plus mr-2"></i>
                                Create Your First Survey
                            </a>
                        </template>
                    </div>
                </div>

                <table v-else class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-3">
                                <input type="checkbox" @change="toggleSelectAll" />
                            </th>
                            <th class="px-4 py-3 whitespace-nowrap cursor-pointer" @click="colSort('title')">
                                Survey Name <i class="fas fa-sort ml-1"></i>
                            </th>
                            <th class="px-4 py-3 whitespace-nowrap cursor-pointer" @click="colSort('type')">
                                Type <i class="fas fa-sort ml-1"></i>
                            </th>
                            <th class="px-4 py-3 whitespace-nowrap cursor-pointer" @click="colSort('user_id')">
                                Created By <i class="fas fa-sort ml-1"></i>
                            </th>
                            <th class="px-4 py-3 whitespace-nowrap">
                                # of Responses
                            </th>
                            <th class="px-4 py-3 whitespace-nowrap cursor-pointer" @click="colSort('status')">
                                Status <i class="fas fa-sort ml-1"></i>
                            </th>
                            <th class="px-4 py-3 whitespace-nowrap">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in surveys.data" :key="item.id"
                            class="border-b dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700">
                            <td class="px-4 py-2">
                                <input type="checkbox"
                                    :checked="selectedIds.includes(item.id)"
                                    @change="toggleSelect(item.id)" />
                            </td>
                            <td class="table-td">
                                <a :href="`${route('surveysShow')}?id=${item.uuid}`"
                                    class="hover:underline underline-offset-2">
                                    {{ item.title }}
                                </a>
                            </td>
                            <td class="table-td">
                                <span class="font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                    {{ typeLabel(item.type) }}
                                </span>
                            </td>
                            <td class="table-td">
                                <div class="avatar-wrap small">
                                    <avatar :name="item.user?.name" />
                                </div>
                            </td>
                            <td class="table-td font-semibold">
                                {{ item.responses_count ?? 0 }}
                            </td>
                            <td class="px-4 py-2 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                <div class="flex items-center">
                                    <div class="w-3 h-3 mr-2 rounded-full" :class="statusClass(item.status)"></div>
                                    <span>{{ statusLabel(item.status) }}</span>
                                </div>
                            </td>
                            <td class="table-td">
                                <div class="flex items-center gap-3">
                                    <a :href="`${route('surveyResponsesByUuid')}?id=${item.uuid}`"
                                        class="inline-flex items-center text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300"
                                        title="View Responses">
                                        <i class="fas fa-comments mr-1.5"></i>
                                        <span>{{ item.responses_count ?? 0 }}</span>
                                    </a>

                                    <surveystatustoggle
                                        :small="true"
                                        :status="item.status === 'active' || item.status === true || item.status === 1"
                                        :updateroute="`${route('surveysUpdate')}?id=${item.uuid}`"
                                    />
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="px-4 py-3 border-t dark:border-gray-700" v-if="surveys.last_page > 1">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        Showing {{ surveys.from }}–{{ surveys.to }} of {{ surveys.total }}
                    </span>
                    <div class="flex gap-1">
                        <template v-for="link in surveys.links" :key="link.label">
                            <button
                                v-if="link.url"
                                class="px-3 py-1 text-sm rounded border"
                                :class="link.active ? 'bg-primary-600 text-white border-primary-600' : 'border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'"
                                @click="router.get(link.url, {}, { preserveState: true })"
                                v-html="link.label"
                            />
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <Teleport to="body">
            <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center">
                <div class="absolute inset-0 bg-gray-900 bg-opacity-50 dark:bg-opacity-80" @click="showDeleteModal = false"></div>
                <div class="relative bg-white rounded-lg shadow dark:bg-gray-700 p-4 md:p-5 w-full max-w-md text-center z-10">
                    <button class="absolute top-3 end-2.5 text-gray-400 hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 inline-flex justify-center items-center dark:hover:bg-gray-600 dark:hover:text-white"
                        @click="showDeleteModal = false">
                        <i class="fas fa-times"></i>
                    </button>
                    <svg class="mx-auto mb-4 text-gray-400 w-12 h-12 dark:text-gray-200" fill="none" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 11V6m0 8h.01M19 10a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                    </svg>
                    <h3 class="mb-5 text-lg font-normal text-gray-500 dark:text-gray-400">Are you sure you want to delete the selected surveys?</h3>
                    <button @click="deleteSelected" class="text-white bg-red-600 hover:bg-red-800 font-medium rounded-lg text-sm px-5 py-2.5 me-2">
                        Yes, I'm sure
                    </button>
                    <button @click="showDeleteModal = false" class="text-gray-500 bg-white hover:bg-gray-100 border border-gray-200 rounded-lg text-sm font-medium px-5 py-2.5 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-500 dark:hover:text-white dark:hover:bg-gray-600">
                        No, cancel
                    </button>
                </div>
            </div>

            <div v-if="showSuccessModal" class="fixed inset-0 z-50 flex items-center justify-center">
                <div class="absolute inset-0 bg-gray-900 bg-opacity-50 dark:bg-opacity-80" @click="showSuccessModal = false"></div>
                <div class="relative bg-white rounded-lg shadow dark:bg-gray-800 p-4 sm:p-5 w-full max-w-md text-center z-10">
                    <button class="text-gray-400 absolute top-2.5 right-2.5 hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm p-1.5 inline-flex items-center dark:hover:bg-gray-600 dark:hover:text-white"
                        @click="showSuccessModal = false">
                        <i class="fas fa-times"></i>
                    </button>
                    <div class="w-12 h-12 rounded-full bg-green-100 dark:bg-green-900 p-2 flex items-center justify-center mx-auto mb-3.5">
                        <svg class="w-8 h-8 text-green-500 dark:text-green-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <p class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Successfully deleted survey(s).</p>
                    <button @click="showSuccessModal = false" class="py-2 px-3 text-sm font-medium text-white rounded-lg bg-primary-600 hover:bg-primary-700">
                        Continue
                    </button>
                </div>
            </div>
        </Teleport>
    </TenantLayout>
</template>
