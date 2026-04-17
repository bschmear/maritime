<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import Modal from '@/Components/Modal.vue';
import DeliveryForm from '@/Components/Tenant/DeliveryForm.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    deliveries: { type: Object, default: () => ({ data: [] }) },
    todayDeliveries: { type: Array, default: () => [] },
    upcomingDeliveries: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({ scheduled: 0, en_route: 0, delivered: 0, cancelled: 0 }) },
    filters: { type: Object, default: () => ({}) },
    fieldsSchema: { type: Object, default: () => ({}) },
    enumOptions:  { type: Object, default: () => ({}) },
});

const showCreateModal  = ref(false);
const showSuccessModal = ref(false);
const createdRecordId = ref(null);

const openCreateModal = () => {
    showCreateModal.value = true;
};

const onDeliverySaved = (id) => {
    showCreateModal.value = false;
    createdRecordId.value = id;
    showSuccessModal.value = true;
};

const viewCreatedRecord = () => {
    if (createdRecordId.value) {
        router.visit(route('deliveries.show', createdRecordId.value));
    }
};

const closeSuccessModal = () => {
    showSuccessModal.value = false;
    createdRecordId.value = null;
    router.reload();
};

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Deliveries' },
]);

const getCustomerName = (d) => d.customer?.display_name ?? d.customer?.name ?? '—';
const getAssetName = (d) => d.asset_unit?.display_name ?? d.asset ?? '—';
const getTechnicianName = (d) => d.technician?.display_name ?? d.technician ?? '—';

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
    scheduled:  { label: 'Scheduled',  bg: 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300',   icon: 'schedule',         dot: 'bg-blue-500' },
    en_route:   { label: 'En Route',   bg: 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-300', icon: 'local_shipping', dot: 'bg-yellow-500' },
    delivered:  { label: 'Delivered',  bg: 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300',  icon: 'check_circle',     dot: 'bg-green-500' },
    cancelled:  { label: 'Cancelled',  bg: 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300',          icon: 'cancel',           dot: 'bg-red-500' },
    rescheduled:{ label: 'Rescheduled',bg: 'bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300',icon: 'event_repeat',    dot: 'bg-purple-500' },
};

const getStatus = (s) => statusConfig[s] || statusConfig.scheduled;

const selectedStatus = ref('all');
const searchQuery = ref('');
const viewMode = ref('timeline');
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
                    <div class="p-6 space-y-0">
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

                                <div class="grid grid-cols-2 gap-x-4 gap-y-1.5 text-sm text-gray-500 dark:text-gray-400">
                                    <div class="flex items-center gap-1.5">
                                        <span class="material-icons text-md text-gray-400">directions_boat</span>
                                        <span class="truncate">{{ getAssetName(delivery) }}</span>
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <span class="material-icons text-md text-gray-400">engineering</span>
                                        <span>{{ getTechnicianName(delivery) }}</span>
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
                                    <p class="text-sm text-gray-400 dark:text-gray-500 truncate">{{ getAssetName(delivery) }}</p>
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

                        <!-- Status filter -->
                        <select
                            v-model="selectedStatus"
                            class="block py-2 px-3 text-md rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        >
                            <option value="all">All Statuses</option>
                            <option value="scheduled">Scheduled</option>
                            <option value="en_route">En Route</option>
                            <option value="delivered">Delivered</option>
                            <option value="cancelled">Cancelled</option>
                            <option value="rescheduled">Rescheduled</option>
                        </select>
                    </div>

                    <!-- New Delivery -->
                    <button
                        @click="openCreateModal"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-medium text-md flex-shrink-0"
                    >
                        <span class="material-icons text-lg">add</span>
                        <span>New Delivery</span>
                    </button>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-6 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Delivery #</th>
                                <th class="px-6 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Customer</th>
                                <th class="px-6 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Asset / Vessel</th>
                                <th class="px-6 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Technician</th>
                                <th class="px-6 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Scheduled</th>
                                <th class="px-6 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-center text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Signed</th>
                                <th class="px-6 py-3 text-right text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="delivery in (deliveries?.data ?? [])" :key="delivery.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap text-md font-mono text-gray-900 dark:text-white">{{ delivery.display_name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-md text-gray-800 dark:text-gray-200">{{ getCustomerName(delivery) }}</td>
                                <td class="px-6 py-4 text-md text-gray-500 dark:text-gray-400 max-w-[180px] truncate">{{ getAssetName(delivery) }}</td>
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

        <!-- ── Create Delivery Modal ───────────────────────────── -->
        <Modal :show="showCreateModal" @close="showCreateModal = false" max-width="4xl">
            <div class="p-6">
                <div class="flex items-center justify-between mb-5">
                    <div class="flex items-center gap-3">
                        <div class="h-9 w-9 rounded-lg bg-blue-100 dark:bg-blue-900/20 flex items-center justify-center">
                            <span class="material-icons text-blue-600 dark:text-blue-400">add_location_alt</span>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-gray-900 dark:text-white">New Delivery</h2>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Schedule a vessel delivery</p>
                        </div>
                    </div>
                    <button @click="showCreateModal = false"
                        class="h-8 w-8 rounded-lg flex items-center justify-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                        <span class="material-icons">close</span>
                    </button>
                </div>

                <DeliveryForm
                    v-if="showCreateModal"
                    :fields-schema="fieldsSchema"
                    :enum-options="enumOptions"
                    @saved="onDeliverySaved"
                    @cancelled="showCreateModal = false"
                />
            </div>
        </Modal>

        <!-- ── Success Modal ───────────────────────────────────── -->
        <Modal :show="showSuccessModal" @close="closeSuccessModal" max-width="sm">
            <div class="p-6 text-center">
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/20">
                    <span class="material-icons text-3xl text-green-600 dark:text-green-400">check_circle</span>
                </div>
                <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-1">Delivery Created</h3>
                <p class="text-md text-gray-500 dark:text-gray-400 mb-6">The delivery has been scheduled successfully.</p>
                <div class="flex justify-center gap-3">
                    <button @click="closeSuccessModal"
                        class="px-4 py-2 text-md font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        Back to List
                    </button>
                    <button @click="viewCreatedRecord"
                        class="px-4 py-2 text-md font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors">
                        View Delivery
                    </button>
                </div>
            </div>
        </Modal>

    </TenantLayout>
</template>
