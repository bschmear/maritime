<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Table from '@/Components/Tenant/Table.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';

const VIEW_STORAGE_KEY = 'maritime.servicetickets.view';

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
});

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Service Tickets' },
]);

const routeRecordType = computed(() => 'servicetickets');

const viewMode = ref('table');

onMounted(() => {
    const saved = localStorage.getItem(VIEW_STORAGE_KEY);
    if (saved === 'cards' || saved === 'table') {
        viewMode.value = saved;
    } else {
        viewMode.value = 'cards';
    }
});

watch(viewMode, (v) => {
    localStorage.setItem(VIEW_STORAGE_KEY, v);
});

const getStatusLabel = (statusId) => {
    const status = props.enumOptions['App\\Enums\\ServiceTicket\\Status']?.find((s) => s.id === statusId);
    return status?.name || 'Unknown';
};

const getStatusBgClass = (statusId) => {
    const status = props.enumOptions['App\\Enums\\ServiceTicket\\Status']?.find((s) => s.id === statusId);
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
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200 mt-4">
                    Service Tickets
                </h2>
            </div>
        </template>

        <Table
            :records="records"
            :schema="schema"
            :form-schema="formSchema"
            :fields-schema="fieldsSchema"
            :enum-options="enumOptions"
            :record-type="routeRecordType"
            record-title="Service Ticket"
            plural-title="Service Tickets"
            :create-modal="false"
            :stats="stats"
            :result-layout="viewMode === 'cards' ? 'grid' : 'table'"
        >
            <template #headerActions>
                <div
                    class="flex rounded-lg border border-gray-200 dark:border-gray-600 overflow-hidden"
                    role="group"
                    aria-label="Result layout"
                >
                    <button
                        type="button"
                        :class="[
                            'px-2.5 py-2 text-sm font-medium transition-colors',
                            viewMode === 'cards'
                                ? 'bg-primary-600 text-white'
                                : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700',
                        ]"
                        title="Card view"
                        @click="viewMode = 'cards'"
                    >
                        <span class="material-icons text-base align-middle">grid_view</span>
                    </button>
                    <button
                        type="button"
                        :class="[
                            'px-2.5 py-2 text-sm font-medium transition-colors border-l border-gray-200 dark:border-gray-600',
                            viewMode === 'table'
                                ? 'bg-primary-600 text-white'
                                : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700',
                        ]"
                        title="Table view"
                        @click="viewMode = 'table'"
                    >
                        <span class="material-icons text-base align-middle">view_list</span>
                    </button>
                </div>
            </template>

            <template #grid>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <a
                        v-for="ticket in records.data"
                        :key="ticket.id"
                        :href="route('servicetickets.show', ticket.id)"
                        class="group bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 hover:shadow-md hover:border-gray-300 dark:hover:border-gray-600 transition-all"
                    >
                        <div class="p-4">
                            <div class="flex items-start justify-between mb-3">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="text-xs font-mono text-gray-500 dark:text-gray-400">
                                            {{ ticket.service_ticket_number || '—' }}
                                        </span>
                                        <span
                                            v-if="ticket.expedite"
                                            class="inline-flex items-center gap-1 px-2 py-0.5 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300 rounded text-xs font-medium"
                                        >
                                            <span class="material-icons text-xs">priority_high</span>
                                            Expedite
                                        </span>
                                    </div>
                                    <h3
                                        class="text-sm font-semibold text-gray-900 dark:text-white truncate group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors"
                                    >
                                        {{ ticket.service_ticket_number }}
                                    </h3>
                                </div>
                                <span
                                    class="material-icons text-gray-400 group-hover:translate-x-1 transition-transform"
                                >
                                    chevron_right
                                </span>
                            </div>

                            <div class="flex flex-wrap gap-2 mb-3">
                                <span
                                    :class="['inline-flex items-center px-2 py-1 rounded text-xs font-medium', getStatusBgClass(ticket.status)]"
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

                            <div v-if="ticket.repair_description" class="mb-3">
                                <p class="text-xs text-gray-500 dark:text-gray-400 line-clamp-2">
                                    {{ ticket.repair_description }}
                                </p>
                            </div>

                            <div
                                class="pt-3 border-t border-gray-200 dark:border-gray-700 space-y-2"
                            >
                                <div
                                    class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400"
                                >
                                    <div class="flex items-center gap-1">
                                        <span class="material-icons text-sm">attach_money</span>
                                        <span class="font-medium">Estimate:</span>
                                    </div>
                                    <span class="font-semibold text-gray-900 dark:text-white">
                                        {{ formatCurrency(ticket.estimated_total) }}
                                    </span>
                                </div>
                                <div
                                    v-if="ticket.pickup_delivery_requested_at"
                                    class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400"
                                >
                                    <div class="flex items-center gap-1">
                                        <span class="material-icons text-sm">local_shipping</span>
                                        <span class="font-medium">Pickup/Delivery:</span>
                                    </div>
                                    <span>{{ formatDate(ticket.pickup_delivery_requested_at) }}</span>
                                </div>
                                <div
                                    class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400"
                                >
                                    <div class="flex items-center gap-1">
                                        <span class="material-icons text-sm">event</span>
                                        <span class="font-medium">Created:</span>
                                    </div>
                                    <span>{{ formatDate(ticket.created_at) }}</span>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            </template>
        </Table>
    </TenantLayout>
</template>
