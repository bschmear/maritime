<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import ServiceTicketForm from '@/Components/Tenant/ServiceTicketForm.vue';
import ServiceTicketPreview from '@/Components/Tenant/ServiceTicketPreview.vue';
import Sublist from '@/Components/Tenant/Sublist.vue';
import { computed, ref } from 'vue';

const props = defineProps({
    record: {
        type: Object,
        required: true,
    },
    fieldsSchema: {
        type: Object,
        default: () => ({}),
    },
    formSchema: {
        type: Object,
        default: null,
    },
    enumOptions: {
        type: Object,
        default: () => ({}),
    },
    account: {
        type: Object,
        default: null,
    },
    timezones: {
        type: Array,
        default: () => [],
    },
    workOrders: {
        type: Array,
        default: () => [],
    },
});

const logoUrl = computed(() => props.account?.logo_url ?? null);

const breadcrumbItems = computed(() => {
    return [
        { label: 'Home', href: route('dashboard') },
        { label: 'Service Tickets', href: route('servicetickets.index') },
        { label: props.record.service_ticket_number || 'View' },
    ];
});

const getEnumLabel = (fieldKey, value) => {
    const fieldDef = props.fieldsSchema[fieldKey];
    if (fieldDef && fieldDef.enum) {
        const options = props.enumOptions[fieldDef.enum] || [];
        const option = options.find(opt => opt.id === value || opt.value === value);
        return option ? option.name : value;
    }
    return value;
};

const formatDateTime = (value) => {
    if (!value) return '—';
    try {
        let date;
        if (typeof value === 'string') {
            date = new Date(value);
        } else if (value instanceof Date) {
            date = value;
        } else {
            return '—';
        }
        if (isNaN(date.getTime())) return '—';
        return new Intl.DateTimeFormat('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            hour12: true,
        }).format(date);
    } catch (error) {
        console.warn('Date formatting error:', error, value);
        return '—';
    }
};

// Quick action state
const selectedStatus = ref(props.record.status);
const statusChanged = ref(false);
const updatingStatus = ref(false);
const approving = ref(false);

// Preview state
const showPreview = ref(false);

const isSublistVisible = (sub) => {
    if (!sub?.conditional || typeof sub.conditional !== 'object') {
        return true;
    }
    const { key, value, operator = 'equals' } = sub.conditional;
    const current = props.record[key];
    const boolCurrent = current === true || current === 1;
    switch (operator) {
        case 'equals':
        case 'eq':
            return typeof value === 'boolean' ? boolCurrent === value : current == value;
        case 'not_equals':
        case 'neq':
            return typeof value === 'boolean' ? boolCurrent !== value : current != value;
        default:
            return typeof value === 'boolean' ? boolCurrent === value : current == value;
    }
};

// Only Images belong in Sublist here — service items and revisions are covered elsewhere on the ticket UI.
const ALLOWED_SERVICE_TICKET_SUBLIST_DOMAINS = new Set(['InventoryImage']);

const visibleSublists = computed(() =>
    (props.formSchema?.sublists || [])
        .filter(isSublistVisible)
        .filter((sub) => ALLOWED_SERVICE_TICKET_SUBLIST_DOMAINS.has(sub?.domain)),
);

// Status options from enum
const statusOptions = computed(() => {
    return props.enumOptions['App\\Enums\\ServiceTicket\\Status'] || [];
});

const updateStatus = async () => {
    if (selectedStatus.value === props.record.status) {
        statusChanged.value = false;
        return;
    }

    updatingStatus.value = true;
    try {
        await router.patch(route('servicetickets.update', props.record.id), {
            status: selectedStatus.value
        }, {
            preserveState: true,
            preserveScroll: true,
        });
        statusChanged.value = false;
    } catch (error) {
        console.error('Failed to update status:', error);
    } finally {
        updatingStatus.value = false;
    }
};

const approveTicket = async () => {
    approving.value = true;
    try {
        await router.patch(route('servicetickets.update', props.record.id), {
            approved: true,
            status: 4 // Approved status
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    } catch (error) {
        console.error('Failed to approve ticket:', error);
    } finally {
        approving.value = false;
    }
};

const deleteTicket = () => {
    if (confirm('Are you sure you want to delete this service ticket?')) {
        router.delete(route('servicetickets.destroy', props.record.id));
    }
};

const openPreview = () => {
    showPreview.value = true;
};

const closePreview = () => {
    showPreview.value = false;
};

// Check if the service ticket has been approved/signed and cannot be edited
const isLocked = computed(() => {
    return props.record.approved || props.record.signed_at || props.record.customer_signature;
});

const linkedTransaction = computed(() => {
    if (!props.record.transaction_id) return null;
    return {
        id: props.record.transaction_id,
        title: props.record.transaction?.title || `Deal #${props.record.transaction?.sequence || props.record.transaction_id}`,
    };
});
</script>

<template>
    <Head :title="`Service Ticket - ${record.display_name || record.uuid}`" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <h2 class="min-w-0 flex-1 truncate text-lg font-semibold leading-tight text-gray-800 md:text-xl dark:text-gray-200">
                        Service Ticket Details
                    </h2>

                    <div class="flex shrink-0 flex-wrap items-center justify-end gap-1 md:gap-2">
                        <Link :href="route('servicetickets.index')">
                            <button
                                type="button"
                                aria-label="Back to service tickets"
                                class="inline-flex items-center justify-center gap-0 rounded-lg border border-gray-300 bg-white p-2 text-md font-medium text-gray-700 transition-colors hover:bg-gray-50 md:gap-1.5 md:px-4 md:py-2.5 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                            >
                                <span class="material-icons text-xl leading-none md:text-md">arrow_back</span>
                                <span class="hidden md:inline">Back to List</span>
                            </button>
                        </Link>
                        <Link v-if="workOrders.length > 0" :href="route('workorders.show', workOrders[0].id)">
                            <button
                                type="button"
                                aria-label="View work order"
                                class="inline-flex items-center justify-center gap-0 whitespace-nowrap rounded-lg bg-amber-600 p-2 text-md font-medium text-white transition-colors hover:bg-amber-700 md:gap-1.5 md:px-4 md:py-2.5"
                            >
                                <span class="material-icons text-xl leading-none md:text-md">assignment</span>
                                <span class="hidden md:inline">View Work Order</span>
                            </button>
                        </Link>
                        <Link v-else :href="route('workorders.create') + '?service_ticket_id=' + record.id">
                            <button
                                type="button"
                                aria-label="Create work order"
                                class="inline-flex items-center justify-center gap-0 whitespace-nowrap rounded-lg bg-amber-600 p-2 text-md font-medium text-white transition-colors hover:bg-amber-700 md:gap-1.5 md:px-4 md:py-2.5"
                            >
                                <span class="material-icons text-xl leading-none md:text-md">add_circle</span>
                                <span class="hidden md:inline">Create Work Order</span>
                            </button>
                        </Link>
                        <button
                            type="button"
                            aria-label="Customer preview"
                            class="inline-flex items-center justify-center gap-0 whitespace-nowrap rounded-lg bg-purple-600 p-2 text-md font-medium text-white transition-colors hover:bg-purple-700 md:gap-1.5 md:px-4 md:py-2.5"
                            @click="openPreview"
                        >
                            <span class="material-icons text-xl leading-none md:text-md">visibility</span>
                            <span class="hidden md:inline">Customer Preview</span>
                        </button>
                        <Link v-if="!isLocked" :href="route('servicetickets.edit', record.id)">
                            <button
                                type="button"
                                aria-label="Edit service ticket"
                                class="inline-flex items-center justify-center gap-0 rounded-lg bg-blue-600 p-2 text-md font-medium text-white transition-colors hover:bg-blue-700 md:gap-1.5 md:px-4 md:py-2.5"
                            >
                                <span class="material-icons text-xl leading-none md:text-md">edit</span>
                                <span class="hidden md:inline">Edit</span>
                            </button>
                        </Link>
                        <button
                            v-else
                            type="button"
                            disabled
                            aria-label="Ticket is locked"
                            class="inline-flex cursor-not-allowed items-center justify-center gap-0 rounded-lg bg-gray-200 p-2 text-md font-medium text-gray-400 md:gap-1.5 md:px-4 md:py-2.5"
                        >
                            <span class="material-icons text-xl leading-none md:text-md">lock</span>
                            <span class="hidden md:inline">Locked</span>
                        </button>
                        <button
                            type="button"
                            aria-label="Delete service ticket"
                            class="inline-flex items-center justify-center gap-0 rounded-lg bg-red-600 p-2 text-md font-medium text-white transition-colors hover:bg-red-700 md:gap-1.5 md:px-3 md:py-2.5"
                            @click="deleteTicket"
                        >
                            <span class="material-icons text-xl leading-none">delete_forever</span>
                            <span class="hidden md:inline">Delete</span>
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <div class="border-t  border-gray-200 dark:border-gray-700  top-0 z-10 shadow-md full-w-margin bg-white dark:bg-gray-900 mb-4 dark:mb-0">
            <div class="w-full px-4 py-4 sm:py-5">
                <div class="flex flex-col lg:flex-row lg:items-center gap-4 lg:gap-6">
                    <div class="flex flex-col sm:flex-row sm:flex-wrap items-stretch sm:items-center gap-3 sm:gap-4 flex-1">
                        <div class="flex flex-col sm:flex-row sm:items-center gap-2 md:gap-3 bg-gray-50 dark:bg-gray-700/50 px-4 py-3 sm:py-2.5 rounded-lg">
                            <span class="text-md font-semibold text-gray-700 dark:text-gray-300 whitespace-nowrap">Status:</span>
                            <div class="flex items-center gap-2">
                                <select
                                    v-model="selectedStatus"
                                    class="text-md px-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all flex-1 sm:flex-none"
                                    @change="statusChanged = true"
                                >
                                    <option v-for="status in statusOptions" :key="status.id" :value="status.id">
                                        {{ status.name }}
                                    </option>
                                </select>
                                <button
                                    v-if="statusChanged"
                                    @click="updateStatus"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 text-white rounded-md text-md font-medium hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all shadow-sm whitespace-nowrap"
                                    :disabled="updatingStatus"
                                >
                                    <span v-if="updatingStatus" class="material-icons text-md animate-spin">refresh</span>
                                    <span v-else class="material-icons text-md">save</span>
                                    Update
                                </button>
                            </div>
                        </div>
                        <div class="flex items-center justify-between sm:justify-start gap-3 bg-gray-50 dark:bg-gray-700/50 px-4 py-3 sm:py-2.5 rounded-lg">
                            <span class="text-md font-semibold text-gray-700 dark:text-gray-300 whitespace-nowrap">Expedite:</span>
                            <span v-if="record.expedite" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-300 rounded-md text-md font-semibold shadow-sm whitespace-nowrap">
                                <span class="material-icons text-lg">priority_high</span>
                                Yes
                            </span>
                            <span v-else class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-md text-md font-medium whitespace-nowrap">
                                No
                            </span>
                        </div>
                        <Link
                            v-if="linkedTransaction"
                            :href="route('transactions.show', linkedTransaction.id)"
                            class="flex items-center justify-between sm:justify-start gap-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 px-4 py-3 sm:py-2.5 rounded-lg hover:bg-blue-100 dark:hover:bg-blue-900/30 transition-colors group"
                        >
                            <span class="material-icons text-lg text-blue-500 dark:text-blue-400">handshake</span>
                            <span class="text-md font-semibold text-blue-700 dark:text-blue-300 whitespace-nowrap group-hover:underline">{{ linkedTransaction.title }}</span>
                        </Link>
                        <div class="flex flex-col sm:flex-row sm:items-center gap-2 md:gap-3 bg-gray-50 dark:bg-gray-700/50 px-4 py-3 sm:py-2.5 rounded-lg">
                            <span class="text-md font-semibold text-gray-700 dark:text-gray-300 whitespace-nowrap">Approved:</span>
                            <div class="flex items-center gap-2">
                                <span v-if="record.approved" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300 rounded-md text-md font-semibold shadow-sm whitespace-nowrap">
                                    <span class="material-icons text-lg">check_circle</span>
                                    Approved
                                </span>
                                <button
                                    v-if="!record.approved"
                                    @click="approveTicket"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-green-600 text-white rounded-md text-md font-medium hover:bg-green-700 focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-all shadow-sm whitespace-nowrap"
                                    :disabled="approving"
                                >
                                    <span v-if="approving" class="material-icons text-md animate-spin">refresh</span>
                                    <span v-else class="material-icons text-md">check_circle</span>
                                    Approve
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="flex flex-row sm:gap-4 lg:gap-6 bg-gray-50 dark:bg-gray-700/50 px-4 py-3 sm:py-2.5 rounded-lg">
                        <div class="flex flex-col flex-1 sm:flex-none">
                            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Created</span>
                            <span class="text-sm sm:text-md font-semibold text-gray-700 dark:text-gray-200">{{ formatDateTime(record.created_at) }}</span>
                        </div>
                        <div class="border-l border-gray-300 dark:border-gray-600 sm:hidden"></div>
                        <div class="flex flex-col flex-1 sm:flex-none">
                            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Updated</span>
                            <span class="text-sm sm:text-md font-semibold text-gray-700 dark:text-gray-200">{{ formatDateTime(record.updated_at) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="w-full space-y-4 md:space-y-6 !pt-0 !mt-0">
            <ServiceTicketForm
                :record="record"
                :form-schema="formSchema"
                :fields-schema="fieldsSchema"
                :enum-options="enumOptions"
                :account="account"
                :timezones="timezones"
                mode="show"
            />
        </div>

        <div
            v-if="visibleSublists.length > 0 && formSchema"
            class="w-full space-y-4 md:space-y-6"
        >
            <Sublist
                :key="`service-ticket-sublist-${record?.id || 'new'}`"
                :parent-record="record"
                parent-domain="ServiceTicket"
                :sublists="visibleSublists"
            />
        </div>

        <!-- Preview Modal -->
        <Teleport to="body">
            <div v-if="showPreview" class="service-ticket-preview-overlay fixed inset-0 z-[100] overflow-y-auto">
                <ServiceTicketPreview
                    :record="record"
                    :account="account"
                    :logo-url="logoUrl"
                    :enum-options="enumOptions"
                    @close="closePreview"
                />
            </div>
        </Teleport>
    </TenantLayout>
</template>
