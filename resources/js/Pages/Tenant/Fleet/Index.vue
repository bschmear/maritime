<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    trucks:    { type: Object, default: () => ({ data: [] }) },
    trailers:  { type: Object, default: () => ({ data: [] }) },
    stats:     { type: Object, default: () => ({ total_trucks: 0, total_trailers: 0, available: 0, in_use: 0, maintenance: 0, out_of_service: 0 }) },
    locations: { type: Array,  default: () => [] },
    filters:   { type: Object, default: () => ({}) },
});

// ── Tabs ─────────────────────────────────────────────────
const activeTab = ref(props.filters?.tab === 'trailers' ? 'trailers' : 'trucks'); // 'trucks' | 'trailers'

// ── Filters ───────────────────────────────────────────────
const searchQuery         = ref(props.filters?.search ?? '');
const selectedLocationId  = ref(props.filters?.location_id ? String(props.filters.location_id) : '');
const selectedStatus      = ref(props.filters?.status ?? '');

watch(() => props.filters, (f) => {
    searchQuery.value        = f?.search ?? '';
    selectedLocationId.value = f?.location_id ? String(f.location_id) : '';
    selectedStatus.value     = f?.status ?? '';
    if (f?.tab === 'trailers' || f?.tab === 'trucks') {
        activeTab.value = f.tab;
    }
}, { deep: true });

const applyFilters = () => {
    const params = { tab: activeTab.value };
    if (searchQuery.value?.trim())   params.search      = searchQuery.value.trim();
    if (selectedLocationId.value)    params.location_id = selectedLocationId.value;
    if (selectedStatus.value)        params.status      = selectedStatus.value;
    router.get(route('fleet.index'), params, { preserveState: true, preserveScroll: true, replace: true });
};

let searchDebounce = null;
watch(searchQuery, () => {
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(() => applyFilters(), 350);
});

const clearSearch = () => { clearTimeout(searchDebounce); searchQuery.value = ''; applyFilters(); };

// ── Status config (matches DB enum) ─────────────────────
const STATUS = {
    active: { label: 'Active', bg: 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300', dot: 'bg-green-500', icon: 'check_circle' },
    inactive: { label: 'Inactive', bg: 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300', dot: 'bg-red-500', icon: 'cancel' },
    maintenance: { label: 'In Maintenance', bg: 'bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-300', dot: 'bg-orange-500', icon: 'build' },
};
const getStatus = (s) => STATUS[s] || STATUS.active;

// ── Helpers ───────────────────────────────────────────────
const getLocationName = (item) => item.location?.display_name ?? '—';
const formatDate      = (val)  => {
    if (!val) return '—';
    return new Date(val).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
};

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Fleet' },
]);

const activeData = computed(() => activeTab.value === 'trucks' ? (props.trucks?.data ?? []) : (props.trailers?.data ?? []));
const activePagination = computed(() => activeTab.value === 'trucks' ? props.trucks : props.trailers);

const setTab = (tab) => {
    activeTab.value = tab;
    applyFilters();
};
</script>

<template>
    <Head title="Fleet" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
            </div>
        </template>

        <div class="space-y-6">

            <!-- ── Stat Cards ── -->
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">

                <!-- Total Trucks -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-md font-medium text-gray-500 dark:text-gray-400">Trucks</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white mt-2">{{ stats.total_trucks }}</p>
                        </div>
                        <div class="h-12 w-12 rounded-lg bg-blue-100 dark:bg-blue-900/20 flex items-center justify-center">
                            <span class="material-icons text-2xl text-blue-600 dark:text-blue-400">local_shipping</span>
                        </div>
                    </div>
                    <p class="mt-3 text-sm text-blue-600 dark:text-blue-400 font-medium flex items-center gap-1">
                        <span class="material-icons text-md">circle</span>
                        {{ stats.in_use }} currently in use
                    </p>
                </div>

                <!-- Total Trailers -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-md font-medium text-gray-500 dark:text-gray-400">Trailers</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white mt-2">{{ stats.total_trailers }}</p>
                        </div>
                        <div class="h-12 w-12 rounded-lg bg-indigo-100 dark:bg-indigo-900/20 flex items-center justify-center">
                            <span class="material-icons text-2xl text-indigo-600 dark:text-indigo-400">rv_hookup</span>
                        </div>
                    </div>
                    <p class="mt-3 text-sm text-indigo-600 dark:text-indigo-400 font-medium flex items-center gap-1">
                        <span class="material-icons text-md">circle</span>
                        Across {{ locations.length }} locations
                    </p>
                </div>

                <!-- Available -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-md font-medium text-gray-500 dark:text-gray-400">Available</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white mt-2">{{ stats.available }}</p>
                        </div>
                        <div class="h-12 w-12 rounded-lg bg-green-100 dark:bg-green-900/20 flex items-center justify-center">
                            <span class="material-icons text-2xl text-green-600 dark:text-green-400">check_circle</span>
                        </div>
                    </div>
                    <p class="mt-3 text-sm text-green-600 dark:text-green-400 font-medium flex items-center gap-1">
                        <span class="material-icons text-md">arrow_upward</span>
                        Ready to deploy
                    </p>
                </div>

                <!-- Maintenance / Out of Service -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-md font-medium text-gray-500 dark:text-gray-400">Needs Attention</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white mt-2">{{ stats.maintenance + stats.out_of_service }}</p>
                        </div>
                        <div class="h-12 w-12 rounded-lg bg-orange-100 dark:bg-orange-900/20 flex items-center justify-center">
                            <span class="material-icons text-2xl text-orange-600 dark:text-orange-400">build</span>
                        </div>
                    </div>
                    <p class="mt-3 text-sm text-orange-600 dark:text-orange-400 font-medium flex items-center gap-1">
                        <span class="material-icons text-md">warning</span>
                        {{ stats.maintenance }} in maintenance · {{ stats.out_of_service }} out of service
                    </p>
                </div>

            </div>

            <!-- ── Fleet by Location ── -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4" v-if="locations.length">
                <div
                    v-for="loc in locations"
                    :key="loc.id"
                    class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-5"
                >
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <div class="h-9 w-9 rounded-lg bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                                <span class="material-icons text-xl text-gray-500 dark:text-gray-400">warehouse</span>
                            </div>
                            <div>
                                <p class="text-md font-semibold text-gray-900 dark:text-white leading-tight">{{ loc.display_name }}</p>
                                <p class="text-sm text-gray-400 dark:text-gray-500">{{ loc.city ?? loc.address ?? '' }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-4 text-sm">
                        <div class="flex items-center gap-1.5 text-gray-600 dark:text-gray-300">
                            <span class="material-icons text-md text-blue-500">local_shipping</span>
                            <span class="font-medium">{{ loc.truck_count ?? 0 }}</span> trucks
                        </div>
                        <div class="flex items-center gap-1.5 text-gray-600 dark:text-gray-300">
                            <span class="material-icons text-md text-indigo-500">rv_hookup</span>
                            <span class="font-medium">{{ loc.trailer_count ?? 0 }}</span> trailers
                        </div>
                    </div>
                    <!-- Mini availability bar -->
                    <div class="mt-3">
                        <div class="flex justify-between text-sm text-gray-400 dark:text-gray-500 mb-1">
                            <span>Availability</span>
                            <span>{{ loc.available_count ?? 0 }} / {{ (loc.truck_count ?? 0) + (loc.trailer_count ?? 0) }}</span>
                        </div>
                        <div class="h-1.5 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                            <div
                                class="h-full bg-green-500 rounded-full transition-all"
                                :style="{ width: ((loc.truck_count ?? 0) + (loc.trailer_count ?? 0)) > 0 ? (((loc.available_count ?? 0) / ((loc.truck_count ?? 0) + (loc.trailer_count ?? 0))) * 100) + '%' : '0%' }"
                            ></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Fleet Table ── -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">

                <!-- Toolbar -->
                <div class="flex flex-col sm:flex-row gap-4 items-start sm:items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">

                    <!-- Tabs -->
                    <div class="flex items-center gap-1 bg-gray-100 dark:bg-gray-700/60 rounded-lg p-1">
                        <button
                            type="button"
                            @click="setTab('trucks')"
                            :class="[
                                'flex items-center gap-1.5 px-4 py-1.5 rounded-md text-sm font-medium transition-all',
                                activeTab === 'trucks'
                                    ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-sm'
                                    : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200'
                            ]"
                        >
                            <span class="material-icons text-md">local_shipping</span>
                            Trucks
                            <span class="ml-0.5 px-1.5 py-0.5 text-[10px] font-bold rounded-full bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300">
                                {{ stats.total_trucks }}
                            </span>
                        </button>
                        <button
                            type="button"
                            @click="setTab('trailers')"
                            :class="[
                                'flex items-center gap-1.5 px-4 py-1.5 rounded-md text-sm font-medium transition-all',
                                activeTab === 'trailers'
                                    ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-sm'
                                    : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200'
                            ]"
                        >
                            <span class="material-icons text-md">rv_hookup</span>
                            Trailers
                            <span class="ml-0.5 px-1.5 py-0.5 text-[10px] font-bold rounded-full bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300">
                                {{ stats.total_trailers }}
                            </span>
                        </button>
                    </div>

                    <!-- Filters row -->
                    <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">

                        <!-- Search -->
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <span class="material-icons text-lg text-gray-400">search</span>
                            </div>
                            <input
                                v-model="searchQuery"
                                type="text"
                                :placeholder="`Search ${activeTab}...`"
                                class="block pl-10 pr-4 py-2 text-md rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full sm:w-56"
                            />
                        </div>

                        <!-- Location filter -->
                        <div class="w-full sm:w-44">
                            <select
                                v-model="selectedLocationId"
                                class="block w-full py-2 px-3 text-md rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500"
                                @change="applyFilters"
                            >
                                <option value="">All locations</option>
                                <option v-for="loc in locations" :key="loc.id" :value="String(loc.id)">
                                    {{ loc.display_name }}
                                </option>
                            </select>
                        </div>

                        <!-- Status filter -->
                        <div class="w-full sm:w-44">
                            <select
                                v-model="selectedStatus"
                                class="block w-full py-2 px-3 text-md rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500"
                                @change="applyFilters"
                            >
                                <option value="">All statuses</option>
                                <option v-for="(cfg, key) in STATUS" :key="key" :value="key">{{ cfg.label }}</option>
                            </select>
                        </div>

                        <!-- Add button -->
                        <Link
                            :href="activeTab === 'trucks' ? route('fleet.trucks.create') : route('fleet.trailers.create')"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-medium text-md flex-shrink-0"
                        >
                            <span class="material-icons text-lg">add</span>
                            <span>Add {{ activeTab === 'trucks' ? 'Truck' : 'Trailer' }}</span>
                        </Link>

                    </div>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-6 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Unit #</th>
                                <th class="px-6 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    {{ activeTab === 'trucks' ? 'Make / Model' : 'Type / Capacity' }}
                                </th>
                                <th class="px-6 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Year</th>
                                <th class="px-6 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Location</th>
                                <!-- <th v-if="activeTab === 'trucks'" class="px-6 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Assigned Driver</th>
                                <th v-if="activeTab === 'trailers'" class="px-6 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Assigned Truck</th> -->
                                <th class="px-6 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Last Service</th>
                                <th class="px-6 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-right text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <tr
                                v-for="item in activeData"
                                :key="item.id"
                                class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
                            >
                                <!-- Unit # -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <div class="h-8 w-8 rounded-lg flex items-center justify-center flex-shrink-0"
                                            :class="activeTab === 'trucks' ? 'bg-blue-100 dark:bg-blue-900/30' : 'bg-indigo-100 dark:bg-indigo-900/30'"
                                        >
                                            <span class="material-icons text-md"
                                                :class="activeTab === 'trucks' ? 'text-blue-600 dark:text-blue-400' : 'text-indigo-600 dark:text-indigo-400'"
                                            >{{ activeTab === 'trucks' ? 'local_shipping' : 'rv_hookup' }}</span>
                                        </div>
                                        <span class="text-md font-mono font-semibold text-gray-900 dark:text-white">{{ item.unit_number ?? item.display_name }}</span>
                                    </div>
                                </td>

                                <!-- Make/Model or Type/Capacity -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <p class="text-md font-medium text-gray-800 dark:text-gray-200">{{ item.make ?? item.trailer_type ?? '—' }}</p>
                                    <p class="text-sm text-gray-400 dark:text-gray-500">{{ item.model ?? item.capacity ?? '' }}</p>
                                </td>

                                <!-- Year -->
                                <td class="px-6 py-4 whitespace-nowrap text-md text-gray-500 dark:text-gray-400">
                                    {{ item.year ?? '—' }}
                                </td>

                                <!-- Location -->
                                <td class="px-6 py-4 whitespace-nowrap text-md text-gray-500 dark:text-gray-400">
                                    <div class="flex items-center gap-1.5">
                                        <span class="material-icons text-md text-gray-400">warehouse</span>
                                        {{ getLocationName(item) }}
                                    </div>
                                </td>

                                <!-- Assigned Driver / Truck -->
                                <!-- <td class="px-6 py-4 whitespace-nowrap text-md text-gray-500 dark:text-gray-400">
                                    <template v-if="activeTab === 'trucks'">
                                        <div v-if="item.driver" class="flex items-center gap-1.5">
                                            <span class="material-icons text-md text-gray-400">person</span>
                                            {{ item.driver?.display_name ?? item.driver?.name }}
                                        </div>
                                        <span v-else class="text-gray-300 dark:text-gray-600">Unassigned</span>
                                    </template>
                                    <template v-else>
                                        <div v-if="item.truck" class="flex items-center gap-1.5">
                                            <span class="material-icons text-md text-gray-400">local_shipping</span>
                                            {{ item.truck?.unit_number ?? item.truck?.display_name }}
                                        </div>
                                        <span v-else class="text-gray-300 dark:text-gray-600">Unattached</span>
                                    </template>
                                </td> -->

                                <!-- Last service -->
                                <td class="px-6 py-4 whitespace-nowrap text-md text-gray-500 dark:text-gray-400">
                                    {{ formatDate(item.last_maintenance_at) }}
                                </td>

                                <!-- Status -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span :class="['inline-flex items-center gap-1 px-2 py-1 rounded text-sm font-medium', getStatus(item.status).bg]">
                                        <span class="material-icons text-sm">{{ getStatus(item.status).icon }}</span>
                                        {{ getStatus(item.status).label }}
                                    </span>
                                </td>

                                <!-- Actions -->
                                <td class="px-6 py-4 whitespace-nowrap text-right text-md">
                                    <div class="flex items-center justify-end gap-3">
                                        <Link
                                            :href="route('fleet.show', item.id)"
                                            class="text-blue-600 dark:text-blue-400 hover:underline font-medium"
                                        >View</Link>
                                        <Link
                                            :href="route('fleet.edit', item.id)"
                                            class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200"
                                        >
                                            <span class="material-icons text-md">edit</span>
                                        </Link>
                                    </div>
                                </td>
                            </tr>

                            <!-- Empty state -->
                            <tr v-if="!activeData.length">
                                <td :colspan="activeTab === 'trucks' ? 9 : 8" class="px-6 py-16">
                                    <div class="flex flex-col items-center justify-center text-center">
                                        <div class="h-16 w-16 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center mb-4">
                                            <span class="material-icons text-4xl text-gray-400 dark:text-gray-500">
                                                {{ activeTab === 'trucks' ? 'local_shipping' : 'rv_hookup' }}
                                            </span>
                                        </div>
                                        <p class="text-base font-medium text-gray-900 dark:text-white">No {{ activeTab }} found</p>
                                        <p class="mt-1 text-sm text-gray-400 dark:text-gray-500">Try adjusting your filters or add a new {{ activeTab === 'trucks' ? 'truck' : 'trailer' }}.</p>
                                        <Link
                                            :href="activeTab === 'trucks' ? route('fleet.trucks.create') : route('fleet.trailers.create')"
                                            class="mt-4 inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-medium text-md"
                                        >
                                            <span class="material-icons text-lg">add</span>
                                            Add {{ activeTab === 'trucks' ? 'Truck' : 'Trailer' }}
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div
                    v-if="activeData.length && activePagination?.total > 0"
                    class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between"
                >
                    <div class="text-md text-gray-500 dark:text-gray-400">
                        Showing
                        {{ (activePagination.current_page - 1) * activePagination.per_page + 1 }}–{{
                            Math.min(activePagination.current_page * activePagination.per_page, activePagination.total)
                        }}
                        of {{ activePagination.total }} results
                    </div>
                    <div class="flex gap-2">
                        <Link
                            v-if="activePagination.prev_page_url"
                            :href="activePagination.prev_page_url"
                            class="px-3 py-1.5 text-md font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600"
                        >Previous</Link>
                        <button v-else disabled class="px-3 py-1.5 text-md font-medium text-gray-400 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg cursor-not-allowed opacity-50">Previous</button>

                        <Link
                            v-if="activePagination.next_page_url"
                            :href="activePagination.next_page_url"
                            class="px-3 py-1.5 text-md font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600"
                        >Next</Link>
                        <button v-else disabled class="px-3 py-1.5 text-md font-medium text-gray-400 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg cursor-not-allowed opacity-50">Next</button>
                    </div>
                </div>

            </div>
        </div>
    </TenantLayout>
</template>