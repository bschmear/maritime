<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import UserInitialsAvatar from '@/Components/Tenant/UserInitialsAvatar.vue';
import SurveyStatusToggle from '@/Components/surveys/SurveyStatusToggle.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

function userDisplayName(user) {
    if (!user) return '';
    // SurveyController sends { id, name, email }
    if (user.name != null && String(user.name).trim() !== '') {
        return String(user.name).trim();
    }
    return (
        (user.display_name && String(user.display_name).trim())
        || [user.first_name, user.last_name].filter(Boolean).join(' ').trim()
        || user.email
        || ''
    );
}

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
    topUsers: {
        type: Object,
        default: null,
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
    users: {
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
    const opt = props.surveyTypes.find((o) => o.value === type);
    return opt?.label ?? opt?.name ?? type ?? '—';
};

const statusDotClass = (status) => {
    if (status === true || status === 1) return 'bg-green-500';
    if (status === false || status === 0) return 'bg-gray-500';
    return 'bg-gray-500';
};

const statusLabel = (status) => {
    if (status === true || status === 1) return 'Active';
    if (status === false || status === 0) return 'Draft';
    return '—';
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
    router.post(route('surveysDeleteSelected'), { ids: selectedIds.value }, {
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

const hasFilter = computed(() =>
    props.filterName || props.filterType || (props.filterStatus && props.filterStatus !== 'active')
);

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
            <div class="flex items-center p-4 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm">
                <div class="flex items-center justify-center w-12 h-12 text-blue-600 dark:text-blue-300 bg-blue-100 dark:bg-blue-900 rounded-lg flex-shrink-0">
                    <span class="material-icons">forum</span>
                </div>
                <div class="flex-1 ms-4">
                    <h3 class="text-2xl font-bold text-gray-900 dark:text-white">{{ totalResponsesThisMonth.toLocaleString() }}</h3>
                    <p class="text-md text-gray-500 dark:text-gray-400">Total Responses This Month</p>
                </div>
            </div>

            <div class="flex items-center p-4 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm">
                <div class="flex items-center justify-center w-12 h-12 text-green-600 dark:text-green-300 bg-green-100 dark:bg-green-900 rounded-lg flex-shrink-0">
                    <span class="material-icons">star</span>
                </div>
                <div class="flex-1 ms-4">
                    <h3 class="text-2xl font-bold text-gray-900 dark:text-white">{{ avgSatisfaction.toFixed(1) }}/5.0</h3>
                    <p class="text-md text-gray-500 dark:text-gray-400">Avg. Satisfaction Score</p>
                </div>
            </div>

            <div class="flex items-center p-4 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm">
                <div class="flex items-center justify-center w-12 h-12 text-purple-600 dark:text-purple-300 bg-purple-100 dark:bg-purple-900 rounded-lg flex-shrink-0">
                    <span class="material-icons">manage_accounts</span>
                </div>
                <div class="flex-1 ms-4">
                    <h3 class="text-2xl font-bold text-gray-900 dark:text-white">{{ topUsers?.name ?? '—' }}</h3>
                    <p class="text-md text-gray-500 dark:text-gray-400">Most surveys created</p>
                </div>
            </div>

            <div class="flex items-center p-4 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm">
                <div class="flex items-center justify-center w-12 h-12 text-yellow-600 dark:text-yellow-300 bg-yellow-100 dark:bg-yellow-900 rounded-lg flex-shrink-0">
                    <span class="material-icons">trending_up</span>
                </div>
                <div class="flex-1 ms-4">
                    <h3 class="text-2xl font-bold text-gray-900 dark:text-white">{{ conversionRate }}%</h3>
                    <p class="text-md text-gray-500 dark:text-gray-400">Conversion Rate</p>
                </div>
            </div>
        </div>

        <div
            class="relative bg-white dark:bg-gray-800 sm:rounded-lg overflow-hidden flex flex-col"
            :class="surveys.data.length > 0 ? 'shadow-md' : ''"
        >
            <div class="px-4">
                <div class="border-b border-gray-200 dark:border-gray-700 space-y-4">

                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between pt-4 pb-4 border-b border-gray-200 dark:border-gray-700">
                        <h5 class="text-lg font-semibold text-gray-900 dark:text-white">Surveys</h5>

                        <div class="flex flex-col sm:flex-row sm:items-center sm:space-x-2 gap-2 mt-3 sm:mt-0">
                            <button
                                v-if="selectedIds.length > 0"
                                class="red-button sm flex items-center justify-center gap-2"
                                @click="showDeleteModal = true"
                            >
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

                    <div class="flex flex-col pb-4 gap-3">
                        <div class="w-full flex flex-col gap-3 lg:flex-row lg:items-center ">
                            <input
                                type="text"
                                :value="filterName"
                                placeholder="Search surveys..."
                                class="input-style max-w-96"
                                @input="e => router.get(route('surveysIndex'), { n: e.target.value }, { preserveState: true })"
                            />

                            <div class="flex flex-wrap items-center gap-2 w-full">
                                <button
                                    v-for="opt in surveyTypes"
                                    :key="opt.value"
                                    class="btn sm"
                                    :class="filterType === opt.value ? 'btn-primary' : 'btn-outline'"
                                    @click="router.get(route('surveysIndex'), { type: opt.value }, { preserveState: true })"
                                >
                                    {{ opt.label ?? opt.name }}
                                </button>

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

                        <div class="flex items-center gap-2 mx-4">
                            <button
                                v-for="opt in surveyStatuses"
                                :key="opt.value"
                                class="btn sm"
                                :class="filterStatus === opt.value ? 'btn-primary' : 'btn-outline'"
                                @click="router.get(route('surveysIndex'), { status: opt.value }, { preserveState: true })"
                            >
                                {{ opt.label ?? opt.name }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="hasFilter" class="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-gray-700">
                <span class="text-md text-gray-500 dark:text-gray-400">Filters active</span>
                <button class="link warning text-md text-gray-500 dark:text-gray-400" @click="clearFilters">Clear filters</button>
            </div>

            <div class="overflow-x-auto grow flex flex-col">

                <div
                    v-if="surveys.data.length === 0"
                    class="bg-gray-50 dark:bg-gray-900 flex items-center justify-center grow"
                >
                    <div class="text-center flex flex-col items-center p-8">
                        <template v-if="hasFilter">
                            <span class="material-icons text-5xl text-gray-400 dark:text-gray-500 mb-4">filter_list_off</span>
                            <h3 class="mb-4 text-lg font-bold text-gray-900 dark:text-white">No surveys match your filters</h3>
                            <button @click="clearFilters" class="btn-link text-gray-500 dark:text-gray-400">Clear Filters</button>
                        </template>
                        <template v-else>
                            <span class="material-icons text-5xl text-gray-400 dark:text-gray-500 mb-4">poll</span>
                            <h3 class="mb-3 text-lg font-bold text-gray-900 dark:text-white">No surveys yet</h3>
                            <p class="mb-4 text-md text-gray-500 dark:text-gray-400">Create your first survey to start collecting feedback and data.</p>
                            <a :href="route('surveysCreate')" class="btn btn-primary inline-flex items-center gap-2">
                                <span class="material-icons text-base leading-none">add</span>
                                Create Your First Survey
                            </a>
                        </template>
                    </div>
                </div>

                <table v-else class="w-full text-md text-left text-gray-600 dark:text-gray-300">
                    <thead class="text-md font-semibold text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
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
                        <tr
                            v-for="item in surveys.data"
                            :key="item.id"
                            class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
                        >
                            <td class="px-4 py-3">
                                <input
                                    type="checkbox"
                                    :checked="selectedIds.includes(item.id)"
                                    @change="toggleSelect(item.id)"
                                    class="rounded"
                                />
                            </td>
                            <td class="px-4 py-3">
                                <a
                                    :href="`${route('surveysShow')}?id=${item.uuid}`"
                                    class="font-medium text-gray-900 dark:text-white hover:underline underline-offset-2"
                                >
                                    {{ item.title }}
                                </a>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ typeLabel(item.type) }}</td>
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
                                    <a
                                        :href="`${route('surveyResponsesByUuid')}?id=${item.uuid}`"
                                        class="inline-flex items-center gap-1 text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 transition-colors"
                                        title="View Responses"
                                    >
                                        <span class="material-icons text-base leading-none">comment</span>
                                        <span>{{ item.responses_count ?? 0 }}</span>
                                    </a>

                                    <SurveyStatusToggle
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
            <div v-if="surveys.last_page > 1" class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <span class="text-md text-gray-500 dark:text-gray-400">
                        Showing {{ surveys.from }}–{{ surveys.to }} of {{ surveys.total }}
                    </span>
                    <div class="flex gap-1">
                        <template v-for="link in surveys.links" :key="link.label">
                            <button
                                v-if="link.url"
                                class="px-3 py-1 text-md rounded border transition-colors"
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

        <Teleport to="body">

            <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center">
                <div class="absolute inset-0 bg-black/50 dark:bg-black/70" @click="showDeleteModal = false" />
                <div class="relative bg-white dark:bg-gray-800 rounded-xl shadow-xl p-6 w-full max-w-md text-center z-10 border border-gray-200 dark:border-gray-700">
                    <button
                        class="absolute top-3 right-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 rounded-lg p-1 transition-colors"
                        @click="showDeleteModal = false"
                    >
                        <span class="material-icons text-xl">close</span>
                    </button>
                    <span class="material-icons text-5xl text-gray-400 dark:text-gray-500 mb-3">help_outline</span>
                    <h3 class="mb-2 text-lg font-semibold text-gray-900 dark:text-white">Delete selected surveys?</h3>
                    <p class="text-md text-gray-500 dark:text-gray-400 mb-6">This action cannot be undone.</p>
                    <div class="flex justify-center gap-3">
                        <button
                            @click="deleteSelected"
                            class="px-5 py-2 text-md font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors"
                        >
                            Yes, delete
                        </button>
                        <button
                            @click="showDeleteModal = false"
                            class="px-5 py-2 text-md font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 rounded-lg transition-colors"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
            </div>

            <div v-if="showSuccessModal" class="fixed inset-0 z-50 flex items-center justify-center">
                <div class="absolute inset-0 bg-black/50 dark:bg-black/70" @click="showSuccessModal = false" />
                <div class="relative bg-white dark:bg-gray-800 rounded-xl shadow-xl p-6 w-full max-w-md text-center z-10 border border-gray-200 dark:border-gray-700">
                    <button
                        class="absolute top-3 right-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 rounded-lg p-1 transition-colors"
                        @click="showSuccessModal = false"
                    >
                        <span class="material-icons text-xl">close</span>
                    </button>
                    <div class="w-14 h-14 rounded-full bg-green-100 dark:bg-green-900/40 flex items-center justify-center mx-auto mb-4">
                        <span class="material-icons text-3xl text-green-600 dark:text-green-400">check_circle</span>
                    </div>
                    <p class="text-lg font-semibold text-gray-900 dark:text-white mb-5">Survey(s) deleted successfully.</p>
                    <button
                        @click="showSuccessModal = false"
                        class="px-5 py-2 text-md font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors"
                    >
                        Continue
                    </button>
                </div>
            </div>

        </Teleport>
    </TenantLayout>
</template>
