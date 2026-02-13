<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
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
    stats: {
        type: Object,
        default: () => ({}),
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const breadcrumbItems = computed(() => {
    return [
        { label: 'Home', href: route('dashboard') },
        { label: 'Service Tickets' },
    ];
});

// Filter state
const urlParams = new URLSearchParams(window.location.search);
const selectedStatus = ref(urlParams.get('status') || 'all');
const selectedApproved = ref(urlParams.get('approved') || 'all');
const searchQuery = ref(urlParams.get('search') || '');
const viewMode = ref('cards');

const filterTickets = () => {
    const params = {};
    if (selectedStatus.value !== 'all') params.status = selectedStatus.value;
    if (selectedApproved.value !== 'all') params.approved = selectedApproved.value;
    if (searchQuery.value.trim()) params.search = searchQuery.value.trim();

    router.get(route('servicetickets.index'), params, {
        preserveState: true,
        preserveScroll: true,
    });
};

const getStatusLabel = (statusId) => {
    const status = props.enumOptions['App\\Enums\\ServiceTicket\\Status']?.find(s => s.id === statusId);
    return status?.name || 'Unknown';
};

const getStatusBgClass = (statusId) => {
    const status = props.enumOptions['App\\Enums\\ServiceTicket\\Status']?.find(s => s.id === statusId);
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

const formatCurrency = (value) => {
    if (value === null || value === undefined) return '$0.00';
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(value);
};
</script>

<template>
    <Head title="Service Tickets" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
            </div>
        </template>

        <div class="space-y-6">
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <!-- Open -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Open Tickets</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white mt-2">{{ stats.open || 0 }}</p>
                        </div>
                        <div class="h-12 w-12 rounded-lg bg-blue-100 dark:bg-blue-900/20 flex items-center justify-center">
                            <span class="material-icons text-2xl text-blue-600 dark:text-blue-400">receipt_long</span>
                        </div>
                    </div>
                </div>

                <!-- Approved -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Approved</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white mt-2">{{ stats.approved || 0 }}</p>
                        </div>
                        <div class="h-12 w-12 rounded-lg bg-green-100 dark:bg-green-900/20 flex items-center justify-center">
                            <span class="material-icons text-2xl text-green-600 dark:text-green-400">verified</span>
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

                <!-- Needs Reauth -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Needs Reauthorization</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white mt-2">{{ stats.needs_reauth || 0 }}</p>
                        </div>
                        <div class="h-12 w-12 rounded-lg bg-amber-100 dark:bg-amber-900/20 flex items-center justify-center">
                            <span class="material-icons text-2xl text-amber-600 dark:text-amber-400">warning</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters & Actions -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex flex-col gap-4">
                    <!-- Filters Row -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <!-- Search -->
                        <div class="w-full">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Search</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                    <span class="material-icons text-base text-gray-400">search</span>
                                </div>
                                <input
                                    v-model="searchQuery"
                                    @keyup.enter="filterTickets"
                                    type="text"
                                    placeholder="Search tickets..."
                                    class="block w-full pl-10 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                />
                            </div>
                        </div>

                        <!-- Status Filter -->
                        <div class="w-full">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
                            <select
                                v-model="selectedStatus"
                                @change="filterTickets"
                                class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            >
                                <option value="all">All Statuses</option>
                                <option
                                    v-for="status in enumOptions['App\\Enums\\ServiceTicket\\Status']"
                                    :key="status.id"
                                    :value="status.id"
                                >
                                    {{ status.name }}
                                </option>
                            </select>
                        </div>

                        <!-- Approved Filter -->
                        <div class="w-full">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Approval</label>
                            <select
                                v-model="selectedApproved"
                                @change="filterTickets"
                                class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            >
                                <option value="all">All</option>
                                <option value="1">Approved</option>
                                <option value="0">Pending</option>
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
                                    'px-3 py-2 text-sm font-medium transition-colors',
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
                        </div>

                        <!-- Create Button -->
                        <a
                            :href="route('servicetickets.create')"
                            class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-medium text-sm"
                        >
                            <span class="material-icons text-base">add</span>
                            <span>New Service Ticket</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Card View -->
            <div v-if="viewMode === 'cards'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <a
                    v-for="ticket in records.data"
                    :key="ticket.id"
                    :href="route('servicetickets.show', ticket.id)"
                    class="group bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 hover:shadow-md hover:border-gray-300 dark:hover:border-gray-600 transition-all"
                >
                    <div class="p-4">
                        <!-- Header -->
                        <div class="flex items-start justify-between mb-3">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-xs font-mono text-gray-500 dark:text-gray-400">
                                        {{ ticket.uuid ? ticket.uuid.substring(0, 8) : ticket.id }}
                                    </span>
                                    <span
                                        v-if="ticket.expedite"
                                        class="inline-flex items-center gap-1 px-2 py-0.5 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300 rounded text-xs font-medium"
                                    >
                                        <span class="material-icons text-xs">priority_high</span>
                                        Expedite
                                    </span>
                                </div>
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white truncate group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">
                                    {{ ticket.display_name }}
                                </h3>
                            </div>
                            <span class="material-icons text-gray-400 group-hover:translate-x-1 transition-transform">
                                chevron_right
                            </span>
                        </div>

                        <!-- Status & Approval -->
                        <div class="flex gap-2 mb-3">
                            <span
                                :class="[
                                    'inline-flex items-center px-2 py-1 rounded text-xs font-medium',
                                    getStatusBgClass(ticket.status)
                                ]"
                            >
                                {{ getStatusLabel(ticket.status) }}
                            </span>
                            <span
                                v-if="ticket.approved"
                                class="inline-flex items-center gap-1 px-2 py-1 rounded text-xs font-medium bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300"
                            >
                                <span class="material-icons text-xs">check_circle</span>
                                Approved
                            </span>
                            <span
                                v-if="ticket.requires_reauthorization"
                                class="inline-flex items-center gap-1 px-2 py-1 rounded text-xs font-medium bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300"
                            >
                                <span class="material-icons text-xs">warning</span>
                                Reauth
                            </span>
                        </div>

                        <!-- Customer & Location -->
                        <div class="space-y-2 mb-3">
                            <div class="flex items-start gap-2 text-sm">
                                <span class="material-icons text-base text-gray-400">person</span>
                                <span class="text-gray-600 dark:text-gray-300 truncate">
                                    {{ ticket.customer?.display_name || '—' }}
                                </span>
                            </div>
                            <div v-if="ticket.asset_unit" class="flex items-start gap-2 text-sm">
                                <span class="material-icons text-base text-gray-400">directions_boat</span>
                                <span class="text-gray-600 dark:text-gray-300 truncate">
                                    {{ ticket.asset_unit?.display_name || '—' }}
                                </span>
                            </div>
                            <div v-if="ticket.location" class="flex items-start gap-2 text-sm">
                                <span class="material-icons text-base text-gray-400">location_on</span>
                                <span class="text-gray-600 dark:text-gray-300 truncate">
                                    {{ ticket.location?.display_name || '—' }}
                                </span>
                            </div>
                        </div>

                        <!-- Repair Description Preview -->
                        <div v-if="ticket.repair_description" class="mb-3">
                            <p class="text-xs text-gray-500 dark:text-gray-400 line-clamp-2">
                                {{ ticket.repair_description }}
                            </p>
                        </div>

                        <!-- Footer Info -->
                        <div class="pt-3 border-t border-gray-200 dark:border-gray-700 space-y-2">
                            <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                                <div class="flex items-center gap-1">
                                    <span class="material-icons text-sm">attach_money</span>
                                    <span class="font-medium">Estimate:</span>
                                </div>
                                <span class="font-semibold text-gray-900 dark:text-white">
                                    {{ formatCurrency(ticket.estimated_total) }}
                                </span>
                            </div>
                            <div v-if="ticket.pickup_delivery_requested_at" class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                                <div class="flex items-center gap-1">
                                    <span class="material-icons text-sm">local_shipping</span>
                                    <span class="font-medium">Pickup/Delivery:</span>
                                </div>
                                <span>{{ formatDate(ticket.pickup_delivery_requested_at) }}</span>
                            </div>
                            <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                                <div class="flex items-center gap-1">
                                    <span class="material-icons text-sm">event</span>
                                    <span class="font-medium">Created:</span>
                                </div>
                                <span>{{ formatDate(ticket.created_at) }}</span>
                            </div>
                        </div>
                    </div>
                </a>

                <!-- Empty State -->
                <div v-if="records.data.length === 0" class="col-span-full">
                    <div class="text-center py-12 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                        <span class="material-icons text-6xl text-gray-400 dark:text-gray-500 mb-4">receipt_long</span>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No service tickets found</h3>
                        <p class="text-gray-500 dark:text-gray-400 mb-4">Get started by creating your first service ticket.</p>
                        <a
                            :href="route('servicetickets.create')"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                        >
                            <span class="material-icons text-base">add</span>
                            Create Service Ticket
                        </a>
                    </div>
                </div>
            </div>

            <!-- Table View -->
            <div v-else-if="viewMode === 'table'" class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Ticket #</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Customer</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Approved</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Expedite</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Estimated Total</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Created</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <tr
                                v-for="ticket in records.data"
                                :key="ticket.id"
                                class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                            >
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-900 dark:text-white">
                                    {{ ticket.uuid ? ticket.uuid.substring(0, 8) : ticket.id }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900 dark:text-white max-w-xs truncate">
                                    {{ ticket.display_name }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ ticket.customer?.display_name || '—' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span
                                        :class="[
                                            'inline-flex items-center px-2 py-1 rounded text-xs font-medium',
                                            getStatusBgClass(ticket.status)
                                        ]"
                                    >
                                        {{ getStatusLabel(ticket.status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span v-if="ticket.approved" class="inline-flex items-center gap-1 text-green-600 dark:text-green-400">
                                        <span class="material-icons text-sm">check_circle</span>
                                    </span>
                                    <span v-else class="text-gray-400 dark:text-gray-500">—</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span v-if="ticket.expedite" class="inline-flex items-center gap-1 text-red-600 dark:text-red-400">
                                        <span class="material-icons text-sm">priority_high</span>
                                    </span>
                                    <span v-else class="text-gray-400 dark:text-gray-500">—</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900 dark:text-white font-medium">
                                    {{ formatCurrency(ticket.estimated_total) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ formatDate(ticket.created_at) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <a
                                        :href="route('servicetickets.show', ticket.id)"
                                        class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300"
                                    >
                                        View
                                    </a>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="records.last_page > 1" class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        Showing {{ records.from }} to {{ records.to }} of {{ records.total }} results
                    </div>
                    <div class="flex gap-2">
                        <a
                            v-if="records.prev_page_url"
                            :href="records.prev_page_url"
                            class="px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600"
                        >
                            Previous
                        </a>
                        <a
                            v-if="records.next_page_url"
                            :href="records.next_page_url"
                            class="px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600"
                        >
                            Next
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </TenantLayout>
</template>