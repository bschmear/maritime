<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import WorkOrderCalendar from '@/Components/Tenant/WorkOrderCalendar.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

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
});

const breadcrumbItems = computed(() => {
    return [
        { label: 'Home', href: route('dashboard') },
        { label: props.pluralTitle },
    ];
});

// Get URL parameters for initial filter state
const urlParams = new URLSearchParams(window.location.search);
const selectedUser = ref(urlParams.get('user') || props.currentUser.id);
const selectedStatus = ref(urlParams.get('status') || 'all');
const selectedPriority = ref(urlParams.get('priority') || 'all');
const viewMode = ref('cards'); // 'cards', 'table', or 'calendar'

const filterWorkOrders = () => {
    router.get(route('workorders.index'), {
        user: selectedUser.value,
        status: selectedStatus.value,
        priority: selectedPriority.value,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

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

            <!-- Status Filter -->
            <div class="w-full">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Status
                </label>
                <select
                    v-model="selectedStatus"
                    @change="filterWorkOrders"
                    class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >
                    <option value="all">All Statuses</option>
                    <option v-for="status in enumOptions['App\\Enums\\WorkOrder\\Status']" :key="status.id" :value="status.id">
                        {{ status.name }}
                    </option>
                </select>
            </div>

            <!-- Priority Filter -->
            <div class="w-full">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Priority
                </label>
                <select
                    v-model="selectedPriority"
                    @change="filterWorkOrders"
                    class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >
                    <option value="all">All Priorities</option>
                    <option v-for="priority in enumOptions['App\\Enums\\WorkOrder\\Priority']" :key="priority.id" :value="priority.id">
                        {{ priority.name }}
                    </option>
                </select>
            </div>
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
                        'px-3 py-2 text-sm font-medium transition-colors',
                        viewMode === 'calendar'
                            ? 'bg-blue-600 text-white'
                            : 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600'
                    ]"
                >
                    <span class="material-icons text-base">calendar_view_month</span>
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
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-xs font-mono text-gray-500 dark:text-gray-400">
                                        WO-{{ workOrder.work_order_number }}
                                    </span>
                                    <span
                                        v-if="isOverdue(workOrder.due_at)"
                                        class="inline-flex items-center gap-1 px-2 py-0.5 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300 rounded text-xs font-medium"
                                    >
                                        <span class="material-icons text-xs">warning</span>
                                        Overdue
                                    </span>
                                </div>
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white truncate group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">
                                    {{ workOrder.display_name }}
                                </h3>
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
                            <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                                <div class="flex items-center gap-1">
                                    <span class="material-icons text-sm">schedule</span>
                                    <span class="font-medium">Scheduled:</span>
                                </div>
                                <span>{{ formatDate(workOrder.scheduled_start_at) }}</span>
                            </div>
                            <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                                <div class="flex items-center gap-1">
                                    <span class="material-icons text-sm">event</span>
                                    <span class="font-medium">Due:</span>
                                </div>
                                <span>{{ formatDate(workOrder.due_at) }}</span>
                            </div>
                            <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
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
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">WO #</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Title</th>
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
                                    {{ workOrder.work_order_number }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900 dark:text-white max-w-xs truncate">
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

                <!-- Pagination would go here -->
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
